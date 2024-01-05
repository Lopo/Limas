<?php

namespace Limas\Command\User;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\User;
use Limas\Entity\UserPreference;
use Limas\Service\UserService;
use Nette\Utils\Random;
use Nette\Utils\Strings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;


#[AsCommand(
	name: 'limas:user:create',
	description: 'Create user account'
)]
class CreateCommand
	extends Command
{
	private $roles = ['user', 'admin', 'super_admin'];


	public function __construct(
		private readonly UserPasswordHasherInterface $hasher,
		private readonly EntityManagerInterface      $manager,
		private readonly UserService                 $userService
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('--role', null, InputOption::VALUE_REQUIRED, 'one of [' . implode(', ', $this->roles) . ']', 'user', function (CompletionInput $input) {
				$curVal = $input->getCompletionValue();
				$sug = [];
				foreach ($this->roles as $pos) {
					if (str_starts_with($pos, $curVal)) {
						$sug[] = $pos;
					}
				}
				return $sug;
			})
			->addArgument('username', InputArgument::REQUIRED)
			->addArgument('email', InputArgument::REQUIRED)
			->addArgument('password', InputArgument::OPTIONAL, 'if not set, will generate random')
			->setHidden();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$username = $input->getArgument('username');
		$email = $input->getArgument('email');

		$rUser = $this->manager->getRepository(User::class);
		if (null !== $rUser->findOneBy(['username' => $username])) {
			$io->error('Given username already in DB');
			return Command::FAILURE;
		}
		$viols = Validation::createValidator()->validate($email, new Email);
		if ($viols->count() > 0) {
			$io->error($viols->get(0));
			return Command::INVALID;
		}
		if (null !== $rUser->findOneBy(['email' => $email])) {
			$io->error('Given email already in DB');
			return Command::FAILURE;
		}

		if ($input->hasOption('role')) {
			$role = $input->getOption('role');
			if (!in_array($role, $this->roles, true)) {
				$io->error("Invalid role '$role', allowed one of [" . implode(', ', $this->roles) . ']');
				return Command::INVALID;
			}
			$role = 'ROLE_' . Strings::upper($role);
		} else {
			$role = 'ROLE_USER';
		}
		$password = $input->hasArgument('password')
			? $input->getArgument('password')
			: Random::generate(10, '0-9a-z');

		$io->comment('Creating user account');
		$admin = (new User($input->getArgument('username'), $this->userService->getBuiltinProvider()))
			->setRoles([$role])
			->setEmail($input->getArgument('email'));
		$admin->setPassword($this->hasher->hashPassword($admin, $password));
		$this->manager->persist($admin);
		$this->manager->persist((new UserPreference($admin, 'limas.tipoftheday.showtips', 'true')));

		$this->manager->flush();

		if (!$input->hasArgument('password')) {
			$io->note("Generated password: '$password'");
		}
		$io->success('account created');
		return Command::SUCCESS;
	}
}
