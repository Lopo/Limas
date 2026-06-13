<?php

namespace Limas\Command;

use Limas\Service\Integration\InfoProvider\DigiKeyService;
use Limas\Service\Integration\InfoProvider\FarnellService;
use Limas\Service\Integration\InfoProvider\MouserService;
use Limas\Service\Integration\InfoProvider\TMEService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


#[AsCommand(
	name: 'limas:distributor:test',
	description: 'Test distributor API connections and search'
)]
class TestDistributorApiCommand
	extends Command
{
	public function __construct(
		private readonly ?MouserService  $mouserService = null,
		private readonly ?DigiKeyService $digiKeyService = null,
		private readonly ?TMEService     $tmeService = null,
		private readonly ?FarnellService $farnellService = null
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addArgument('distributor', InputArgument::REQUIRED, 'Distributor name (mouser, digikey, tme, arrow, farnell, octopart, lcsc)')
			->addArgument('query', InputArgument::REQUIRED, 'Search query or part number')
			->addOption('part-number', 'p', InputOption::VALUE_NONE, 'Search by exact part number instead of keyword')
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit results', 5);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$distributor = strtolower($input->getArgument('distributor'));
		$query = $input->getArgument('query');
		$byPartNumber = $input->getOption('part-number');
		$limit = (int)$input->getOption('limit');

		$io->title("Testing {$distributor} API");
		$io->text("Query: {$query}");
		$io->text($byPartNumber ? 'Searching by part number' : 'Searching by keyword');

		try {
			$results = match ($distributor) {
				'mouser' => $this->testMouser($query, $byPartNumber),
				'digikey' => $this->testDigiKey($query, $byPartNumber),
				'tme' => $this->testTME($query),
				'farnell' => $this->testFarnell($query, $byPartNumber),
				default => throw new \InvalidArgumentException("Unknown distributor: {$distributor}")
			};

			if ($results === []) {
				$io->warning('No results found');
				return Command::SUCCESS;
			}

			// Display results
			$io->section('Results');
			$rows = [];
			$count = 0;

			foreach ($results as $item) {
				if ($count >= $limit) {
					break;
				}
				$rows[] = [
					$item['mpn'] ?? 'N/A',
					$item['manufacturer'] ?? 'N/A',
					substr($item['title'] ?? 'N/A', 0, 50) . '...',
					$item['uid'] ?? 'N/A'
				];
				$count++;
			}

			$io->table(['MPN', 'Manufacturer', 'Description', 'Distributor ID'], $rows);
			$io->success("Found {$count} results");

		} catch (\Exception $e) {
			$io->error('API Error: ' . $e->getMessage());
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	private function testMouser(string $query, bool $byPartNumber): array
	{
		if ($this->mouserService === null) {
			throw new \RuntimeException('Mouser API not configured');
		}

		$response = $byPartNumber
			? $this->mouserService->searchByPartnumber($query)
			: $this->mouserService->searchByKeyword($query);

		$results = [];
		foreach ($response['SearchResults']['Parts'] ?? [] as $part) {
			$results[] = [
				'mpn' => $part['ManufacturerPartNumber'],
				'manufacturer' => $part['Manufacturer'],
				'title' => $part['Description'],
				'uid' => $part['MouserPartNumber']
			];
		}
		return $results;
	}

	private function testDigiKey(string $query, bool $byPartNumber): array
	{
		if ($this->digiKeyService === null) {
			throw new \RuntimeException('DigiKey API not configured');
		}

		if ($byPartNumber) {
			$product = $this->digiKeyService->searchByPartNumber($query);
			if ($product !== []) {
				return [[
					'mpn' => $product['ManufacturerProductNumber'] ?? '',
					'manufacturer' => $product['Manufacturer']['Name'] ?? '',
					'title' => $product['Description']['ProductDescription'] ?? '',
					'uid' => $product['ProductVariations'][0]['DigiKeyProductNumber'] ?? ''
				]];
			}
		} else {
			$response = $this->digiKeyService->searchByKeyword($query);
			$results = [];
			foreach ($response['Products'] ?? [] as $product) {
				$results[] = [
					'mpn' => $product['ManufacturerProductNumber'] ?? '',
					'manufacturer' => $product['Manufacturer']['Name'] ?? '',
					'title' => $product['Description']['ProductDescription'] ?? '',
					'uid' => $product['ProductVariations'][0]['DigiKeyProductNumber'] ?? ''
				];
			}
			return $results;
		}
		return [];
	}

	private function testTME(string $query): array
	{
		if ($this->tmeService === null) {
			throw new \RuntimeException('TME API not configured');
		}

		$response = $this->tmeService->searchByKeyword($query);
		$results = [];

		if (($response['status'] ?? null) === 'OK') {
			foreach ($response['data']['products']['elements'] ?? [] as $product) {
				$results[] = [
					'mpn' => $product['manufacturer_symbols'][0] ?? $product['symbol'] ?? '',
					'manufacturer' => $product['manufacturer']['name'] ?? '',
					'title' => $product['description'] ?? '',
					'uid' => $product['symbol'] ?? ''
				];
			}
		}
		return $results;
	}

	private function testFarnell(string $query, bool $byPartNumber): array
	{
		if ($this->farnellService === null) {
			throw new \RuntimeException('Farnell API not configured');
		}

		$response = $byPartNumber
			? $this->farnellService->searchByPartNumber($query, true)
			: $this->farnellService->searchByKeyword($query);

		$products = $response['keywordSearchReturn']['products']
			?? $response['premierFarnellPartNumberReturn']['products']
			?? $response['manufacturerPartNumberSearchReturn']['products']
			?? [];

		$results = [];
		foreach ($products as $product) {
			$results[] = [
				'mpn' => $product['translatedManufacturerPartNumber'] ?? $product['manufacturerPartNumber'] ?? '',
				'manufacturer' => $product['vendorName'] ?? $product['brandName'] ?? '',
				'title' => $product['displayName'] ?? '',
				'uid' => $product['sku'] ?? ''
			];
		}
		return $results;
	}
}
