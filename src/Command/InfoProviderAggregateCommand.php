<?php

namespace Limas\Command;

use Limas\Service\Integration\InfoProvider\Dto\FieldWithProvenance;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\InfoProviderAggregator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


#[AsCommand(
	name: 'limas:distributor:aggregate',
	description: 'Fan an MPN search across distributors. Use --merged for cross-source consensus + conflict view.'
)]
final class InfoProviderAggregateCommand
	extends Command
{
	private bool $dumpParams = false;


	public function __construct(
		private readonly InfoProviderAggregator $aggregator
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addArgument('mpn', InputArgument::REQUIRED, 'Manufacturer part number to look up.')
			->addOption('sources', 's', InputOption::VALUE_REQUIRED, 'Comma-separated subset of distributors (e.g. tme,digikey)')
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max raw results per distributor', '3')
			->addOption('merged', 'm', InputOption::VALUE_NONE, 'Group by (manufacturer, MPN) and merge across distributors')
			->addOption('params', 'p', InputOption::VALUE_NONE, 'When used with --merged, dump per-source parameters with parsed numeric/unit fields');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$mpn = $input->getArgument('mpn');
		$limit = (int)$input->getOption('limit');
		$sources = $input->getOption('sources');
		$this->dumpParams = $input->getOption('params') === true;
		$sourcesArr = is_string($sources) && $sources !== '' ? array_map('trim', explode(',', $sources)) : null;

		$io->title("Aggregating MPN '$mpn'" . ($input->getOption('merged') ? ' (merged view)' : ''));
		$labels = array_map(
			static fn(array $s) => sprintf('%s [%s]', $s['name'], implode(',', $s['capabilities'])),
			array_values(array_filter($this->aggregator->sourcesWithCapabilities(), static fn(array $s) => $s['configured']))
		);
		$io->writeln('Configured sources: ' . implode('  ', $labels));
		if ($sourcesArr !== null) {
			$io->writeln('Restricting to: ' . implode(', ', $sourcesArr));
		}

		return $input->getOption('merged')
			? $this->printMerged($io, $mpn, $sourcesArr, $limit)
			: $this->printRaw($io, $mpn, $sourcesArr, $limit);
	}

	private function printRaw(SymfonyStyle $io, string $mpn, ?array $sourcesArr, int $limit): int
	{
		$results = $this->aggregator->searchByMpn($mpn, $sourcesArr, $limit);

		foreach ($results as $source => $list) {
			$io->section($source);
			if (isset($list['error'])) {
				$io->error('Error: ' . $list['error']);
				continue;
			}
			if ($list === []) {
				$io->warning('No results');
				continue;
			}
			foreach ($list as $r) {
				/** @var InfoProviderSearchResult $r */
				$exact = $r->isExactMatch ? ' <fg=green>EXACT</>' : '';
				$io->writeln(sprintf('<info>%s</info>  %s — %s%s', $r->sourceSku, $r->manufacturerName, $r->manufacturerPartNumber, $exact));
				$io->writeln('  ' . ($r->description ?? '(no description)'));
				$lifecycle = $r->lifecycleStatus !== null ? $r->lifecycleStatus->value : '-';
				$io->writeln(sprintf('  pkg=%s  cat=%s  status=%s  stock=%s', $r->packageName ?? '-', $r->categoryName ?? '-', $lifecycle, $r->stock ?? '?'));
				$io->newLine();
			}
		}

		return Command::SUCCESS;
	}

	private function printMerged(SymfonyStyle $io, string $mpn, ?array $sourcesArr, int $limit): int
	{
		$candidates = $this->aggregator->searchByMpnAndMerge($mpn, $sourcesArr, $limit);

		if ($candidates === []) {
			$io->warning('No matching products across distributors');
			return Command::SUCCESS;
		}

		$io->writeln(sprintf("\nFound <info>%d</info> distinct candidate(s) across sources.\n", count($candidates)));

		foreach ($candidates as $i => $c) {
			$io->section(sprintf(
				'#%d  %s — %s   [sources: %s]%s',
				$i + 1,
				$c->manufacturerName->chosenValue ?? '?',
				$c->manufacturerPartNumber->chosenValue ?? '?',
				implode(', ', $c->contributingSources),
				$c->conflicts !== [] ? '  ⚠ conflicts: ' . implode(',', $c->conflicts) : ''
			));

			$this->printField($io, 'description', $c->description, truncate: 80);
			$this->printField($io, 'package', $c->packageName);
			$this->printField($io, 'datasheet', $c->datasheetUrl);
			$this->printField($io, 'image', $c->imageUrl);

			if ($this->dumpParams && $c->parameters !== []) {
				$io->writeln('  Parameters (parsed by Stage-2 value parser):');
				foreach ($c->parameters as $src => $params) {
					if ($params === []) {
						continue;
					}
					$io->writeln(sprintf('    [%s]', $src));
					foreach ($params as $p) {
						$bits = [];
						if ($p->qualifier !== null) {
							$bits[] = 'qual=' . $p->qualifier;
						}
						if ($p->numericValue !== null) {
							$bits[] = 'val=' . $p->numericValue;
						}
						if ($p->numericMin !== null) {
							$bits[] = 'min=' . $p->numericMin;
						}
						if ($p->numericMax !== null) {
							$bits[] = 'max=' . $p->numericMax;
						}
						if ($p->siPrefix !== null) {
							$bits[] = 'prefix=' . $p->siPrefix;
						}
						if ($p->unit !== null) {
							$bits[] = 'unit=' . $p->unit;
						}
						if ($p->valueText !== null) {
							$bits[] = 'text=' . $p->valueText;
						}
						$parsed = $bits === [] ? '<unparsed>' : implode(' ', $bits);
						$canon = $p->canonicalName !== null && $p->canonicalName !== $p->rawName
							? ' [canon: ' . $p->canonicalName . ']'
							: '';
						$io->writeln(sprintf('      %s%s = "%s"  → %s', $p->rawName, $canon, $p->rawValue, $parsed));
					}
				}
			}

			$io->writeln('  Per-distributor:');
			foreach ($c->providerSpecific as $src => $ds) {
				$prices = '';
				if ($ds->priceBreaks !== []) {
					$first = $ds->priceBreaks[0];
					$prices = sprintf(' price1=%.4f %s @ qty %d', $first->price, $ds->currency ?? '', $first->quantity);
				}
				$lifecycle = $ds->lifecycleStatus !== null ? $ds->lifecycleStatus->value : '-';
				$io->writeln(sprintf(
					'    [%s] sku=%s stock=%s status=%s cat=%s%s',
					$src,
					$ds->sourceSku,
					$ds->stock ?? '?',
					$lifecycle,
					$ds->categoryName ?? '-',
					$prices
				));
			}
			$io->newLine();
		}

		return Command::SUCCESS;
	}

	private function printField(SymfonyStyle $io, string $label, FieldWithProvenance $f, int $truncate = 200): void
	{
		$icon = match ($f->resolution) {
			FieldWithProvenance::RESOLUTION_CONSENSUS => '✓',
			FieldWithProvenance::RESOLUTION_MAJORITY => '◐',
			FieldWithProvenance::RESOLUTION_HIERARCHY => '⚠',
			default => ' '
		};
		$value = $f->chosenValue ?? '-';
		if (mb_strlen($value) > $truncate) {
			$value = mb_substr($value, 0, $truncate - 3) . '...';
		}
		$io->writeln(sprintf('  %s %s: %s', $icon, $label, $value));
		if ($f->isConflict) {
			foreach ($f->sourcesValues as $src => $v) {
				if ($v === null || $v === '') {
					continue;
				}
				$shown = mb_strlen($v) > 70 ? mb_substr($v, 0, 67) . '...' : $v;
				$io->writeln(sprintf('       %s: %s', $src, $shown));
			}
		}
	}
}
