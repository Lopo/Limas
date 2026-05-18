<?php

namespace Limas\Command\User;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\User;
use Limas\Service\UserService;
use Nette\Utils\Random;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


#[AsCommand(
	name: 'limas:user:password',
	description: 'Set password for a user'
)]
class PasswordCommand
	extends Command
{
	public function __construct(
		private readonly UserPasswordHasherInterface $hasher,
		private readonly EntityManagerInterface      $entityManager,
		private readonly UserService                 $userService
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addArgument('username', InputArgument::REQUIRED, 'The username to set password for')
			->addArgument('password', InputArgument::OPTIONAL, 'New password (if not set, will generate random)')
			->addOption('provider', 'p', InputOption::VALUE_REQUIRED, 'Provider type (e.g., Builtin, LDAP). Required if username exists with multiple providers.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$username = $input->getArgument('username');
		$providerType = $input->getOption('provider');

		$users = $this->entityManager->getRepository(User::class)->findBy(['username' => $username]);

		if (count($users) === 0) {
			$io->error(sprintf('User %s not found', $username));
			return Command::FAILURE;
		}

		if (count($users) > 1 && $providerType === null) {
			$providers = array_map(fn(User $u) => $u->getProvider()->getType(), $users);
			$io->error(sprintf(
				'Multiple users found with username "%s" (providers: %s). Use --provider to specify which one.',
				$username,
				implode(', ', $providers)
			));
			return Command::FAILURE;
		}

		if ($providerType !== null) {
			$provider = $this->userService->getProviderByType($providerType);
			$user = $this->entityManager->getRepository(User::class)->findOneBy([
				'username' => $username,
				'provider' => $provider
			]);
			if ($user === null) {
				$io->error(sprintf('User %s with provider %s not found', $username, $providerType));
				return Command::FAILURE;
			}
		} else {
			$user = $users[0];
		}

		$password = $input->getArgument('password') ?? Random::generate(10, '0-9a-z');

		$user->setPassword($this->hasher->hashPassword($user, $password));
		$this->entityManager->flush();

		if (!$input->getArgument('password')) {
			$io->note("Generated password: '$password'");
		}
		$io->success(sprintf('Password for user %s (%s) has been updated', $username, $user->getProvider()->getType()));

		return Command::SUCCESS;
	}
}
