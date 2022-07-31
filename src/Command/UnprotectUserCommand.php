<?php

namespace Limas\Command;

use Limas\Repository\UserRepository;
use Limas\Service\UserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


#[AsCommand(
	name: 'limas:user:unprotect',
	description: 'Unprotects a given user against changes'
)]
class UnprotectUserCommand
	extends Command
{
	public function __construct(
		private readonly UserService    $userService,
		private readonly UserRepository $userRepository
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addArgument('username', InputArgument::REQUIRED, 'The username to unprotect against changes');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$username = $input->getArgument('username');

		if (null === $this->userRepository->findOneBy(['username' => $username])) {
			$io->error(sprintf('User %s not found', $username));
			return Command::FAILURE;
		}

		$this->userService->unprotect($this->userService->getUser($username, $this->userService->getBuiltinProvider(), true));

		$io->success(sprintf('User %s unprotected against changes', $username));

		return Command::SUCCESS;
	}
}
