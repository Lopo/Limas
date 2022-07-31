<?php

namespace Limas\Command;

use Limas\Entity\User;
use Limas\Entity\UserPreference;
use Limas\Entity\UserProvider;
use Limas\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineMigrations\Version00000000000001;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;


#[AsCommand(
	name: 'app:install',
	description: 'Install Limas application'
)]
class InstallCommand
	extends Command
{
	public function __construct(
		private readonly Kernel                      $kernel,
		private readonly UserPasswordHasherInterface $hasher,
		private readonly EntityManagerInterface      $manager
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('--config', null, InputOption::VALUE_REQUIRED, 'config file')
			->setHidden();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		if ('' === ($cfg = trim($input->getOption('config')))) {
			$io->error('Config option missing');
			return self::FAILURE;
		}
		if ($cfg[0] !== '/') {
			$cfg = $this->kernel->getProjectDir() . '/' . $cfg;
		}
		$containerBuilder = new ContainerBuilder;
		if (!$containerBuilder->fileExists($cfg, false)) {
			$io->error('Config file not found');
			return self::FAILURE;
		}
		try {
			$yaml = Yaml::parseFile($cfg);
		} catch (ParseException $ex) {
			$io->error('Error parsing config file: ' . $ex->getMessage());
			return self::FAILURE;
		}

		if ($io->ask('Are you sure to install Limas app ? (yes|no)', 'no') !== 'yes') {
			$io->error('Cancelled');
			return Command::SUCCESS;
		}

		$io->comment('Creating SQL DB tables');
		$cInput = new ArrayInput([
			'command' => 'doctrine:migration:execute',
			'versions' => [Version00000000000001::class]
		]);
		$cInput->setInteractive(false);
		if (0 !== ($rCode = $this->getApplication()->find('doctrine:migration:execute')->run($cInput, new NullOutput))) {
			return $rCode;
		}

		$io->comment('Loading data fixtures');
		$this->getApplication()->find('doctrine:fixtures:load')
			->run(new ArrayInput([
				'command' => 'doctrine:fixtures:load',
				'--append' => true,
				'--no-interaction' => true,
				'--group' => ['install']
			]), new NullOutput);
		if (isset($yaml['fixtures'])) {
			foreach ($yaml['fixtures'] as $name => $load) {
				$load = (bool)$load;
				if (!$load || $name === 'install') {
					continue;
				}
				$io->note($name);
				$this->getApplication()->find('doctrine:fixtures:load')
					->run(new ArrayInput([
						'command' => 'doctrine:fixtures:load',
						'--append' => true,
						'--no-interaction' => true,
						'--group' => [$name]
					]), new NullOutput);
			}
		}

		$io->note('Creating SuperAdmin account');
		$admin = (new User('admin'))
			->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'])
			->setEmail($yaml['superadmin']['email'])
			->setProvider($this->manager->find(UserProvider::class, 1));
		$admin->setPassword($this->hasher->hashPassword($admin, $yaml['superadmin']['password']));
		$this->manager->persist($admin);
		$this->manager->persist((new UserPreference($admin, 'limas.tipoftheday.showtips', 'true')));

		$this->manager->flush();

		$io->note('Generating JWT keypair');
		$this->getApplication()->find('lexik:jwt:generate-keypair')
			->run(new ArrayInput([
				'command' => 'lexik:jwt:generate-keypair',
				'--no-interaction' => true
			]), new NullOutput);

		$io->success('Limas app installed');
		return Command::SUCCESS;
	}
}
