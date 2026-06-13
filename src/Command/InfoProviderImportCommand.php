<?php

namespace Limas\Command;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\PartCategory;
use Limas\Entity\StorageLocation;
use Limas\Service\Integration\InfoProvider\AggregatorImporter;
use Limas\Service\Integration\InfoProvider\InfoProviderAggregator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


#[AsCommand(
	name: 'limas:distributor:import',
	description: 'Search MPN across configured info providers and create a Part from the picked candidate.'
)]
final class InfoProviderImportCommand
	extends Command
{
	public function __construct(
		private readonly InfoProviderAggregator $aggregator,
		private readonly AggregatorImporter     $importer,
		private readonly EntityManagerInterface $em
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addArgument('mpn', InputArgument::REQUIRED, 'Manufacturer part number to import.')
			->addOption('candidate', 'c', InputOption::VALUE_REQUIRED, '1-based index of the merged candidate to import', '1')
			->addOption('category', null, InputOption::VALUE_REQUIRED, 'PartCategory id (required when --yes)')
			->addOption('storage', null, InputOption::VALUE_REQUIRED, 'StorageLocation id (required when --yes)')
			->addOption('sources', 's', InputOption::VALUE_REQUIRED, 'Comma-separated subset of providers (e.g. tme,digikey)')
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max raw results per provider before merging', '5')
			->addOption('yes', 'y', InputOption::VALUE_NONE, 'Actually persist; without --yes the command only previews the picked candidate.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$mpn = $input->getArgument('mpn');
		$limit = (int)$input->getOption('limit');
		$pickedIdx = max(1, (int)$input->getOption('candidate'));
		$sources = $input->getOption('sources');
		$sourcesArr = is_string($sources) && $sources !== '' ? array_map('trim', explode(',', $sources)) : null;

		$io->title("Import preview for MPN '$mpn'");
		$candidates = $this->aggregator->searchByMpnAndMerge($mpn, $sourcesArr, $limit);
		if ($candidates === []) {
			$io->warning('No matching products across configured providers.');
			return Command::SUCCESS;
		}

		$io->section('Candidates');
		foreach ($candidates as $i => $c) {
			$marker = ($i + 1) === $pickedIdx ? '<info>→</info>' : ' ';
			$io->writeln(sprintf(
				'%s #%d  %s — %s  [sources: %s]%s',
				$marker,
				$i + 1,
				$c->manufacturerName->chosenValue ?? '?',
				$c->manufacturerPartNumber->chosenValue ?? '?',
				implode(', ', $c->contributingSources),
				$c->conflicts !== [] ? '  ⚠ ' . implode(',', $c->conflicts) : ''
			));
		}

		if ($pickedIdx > count($candidates)) {
			$io->error(sprintf('Picked --candidate=%d, but only %d candidates available.', $pickedIdx, count($candidates)));
			return Command::FAILURE;
		}
		$picked = $candidates[$pickedIdx - 1];

		$io->newLine();
		$io->writeln('Picked candidate (#' . $pickedIdx . '):');
		$io->writeln('  manufacturer = ' . ($picked->manufacturerName->chosenValue ?? '?'));
		$io->writeln('  mpn          = ' . ($picked->manufacturerPartNumber->chosenValue ?? '?'));
		$io->writeln('  description  = ' . ($picked->description->chosenValue ?? '(none)'));
		$io->writeln('  contributing = ' . implode(', ', $picked->contributingSources));
		$io->writeln('  parameters   = ' . array_sum(array_map('count', $picked->parameters)) . ' (raw, deduped to first-non-empty during import)');

		if (!$input->getOption('yes')) {
			$io->note('Dry-run preview only. Re-run with --yes plus --category=<id> --storage=<id> to persist.');
			return Command::SUCCESS;
		}

		$categoryId = (int)$input->getOption('category');
		$storageId = (int)$input->getOption('storage');
		if ($categoryId <= 0 || $storageId <= 0) {
			$io->error('--yes requires both --category=<PartCategory id> and --storage=<StorageLocation id>');
			return Command::FAILURE;
		}
		$category = $this->em->find(PartCategory::class, $categoryId);
		$storage = $this->em->find(StorageLocation::class, $storageId);
		if ($category === null) {
			$io->error("PartCategory #$categoryId not found.");
			return Command::FAILURE;
		}
		if ($storage === null) {
			$io->error("StorageLocation #$storageId not found.");
			return Command::FAILURE;
		}

		$part = $this->importer->import($picked, $category, $storage);
		$io->success(sprintf(
			'Created Part #%d "%s" with %d manufacturer link(s), %d distributor link(s), %d parameter(s).',
			$part->getId() ?? 0,
			$part->getName() ?? '?',
			count($part->getManufacturers()),
			count($part->getDistributors()),
			count($part->getParameters())
		));
		return Command::SUCCESS;
	}
}
