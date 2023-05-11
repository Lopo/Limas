<?php

namespace Limas\Command;

use Limas\Kernel;
use Limas\Service\ReflectionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;


#[AsCommand(
	name: 'limas:extjs:models',
	description: 'Generate models.js for ExtJS',
)]
class GenerateModelsCommand
	extends Command
{
	public function __construct(
		private readonly ReflectionService $reflectionService,
		private readonly Kernel            $kernel,
		private readonly Environment       $twig
	)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$content = '';
		try {
			foreach ($this->reflectionService->getAssetEntities() as $entity) {
				$content .= $this->twig->render('reflection/model.js.twig', $entity);
			}
			(new Filesystem)->dumpFile($this->kernel->getProjectDir() . '/public/js/models.js', $content);
		} catch (\Exception $e) {
			$io->error($e->getMessage());
			return self::FAILURE;
		}
		return self::SUCCESS;
	}
}
