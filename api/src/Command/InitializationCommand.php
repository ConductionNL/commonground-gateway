<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Entity\Application;
use App\Entity\Cronjob;
use App\Entity\Organization;
use App\Entity\SecurityGroup;
use App\Entity\User;
use App\Event\ActionEvent;
use Cron\CronExpression;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use CommonGateway\CoreBundle\Service\InstallationService;

class InitializationCommand extends Command
{
    private InputInterface $input;
    private OutputInterface $output;
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'commongateway:initialize';
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private SessionInterface $session;
    private ParameterBagInterface $params;
    private InstallationService $installationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        SessionInterface $session,
        InstallationService $installationService
    ) {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->session = $session;
        $this->installationService = $installationService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Facilitates the initialization of the gateway and checks configuration')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command is supposed to be run whenever a gateway initilizes to make sure there is enough basic configuration to actually start the gateway');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $io = new SymfonyStyle($input, $output);

        $io->title('Check if we have the needed objects');


        // Handling Organizations
        $io->section("Looking for an organisation");
        if(!$organization = $this->entityManager->getRepository('App:Organization')->findOneBy([])){
            $io->info('No organization found, creating a new one');
            $organization = New Organization();
            $organization->setName("Default Organization");
            $organization->setDescription("Created during auto configuration");

            $this->entityManager->persist($organization);
        }
        else{
            $io->info('Organization found continuing');
        }

        // Handling Applications
        $io->section("Looking for an application");
        if(!$application = $this->entityManager->getRepository('App:Application')->findOneBy([])){
            $io->info('No application found, creating a new one');
            $application = New Application();
            $application->setName("Default Application");
            $application->setDescription("Created during auto configuration");
            $application->setDomains(["http://localhost/"]);
            $application->setOrganization($organization);

            $this->entityManager->persist($application);
        }
        else{
            $io->info('Application found continuing');
        }

        // Handling user groups
        $io->section("Looking for an security group");
        if(!$securityGroupAdmin = $this->entityManager->getRepository('App:SecurityGroup')->findOneBy([])){
            $io->info('No securityGroup found, creating an anonymous, user and admin one');

            $securityGroupAnonymous = New SecurityGroup();
            $securityGroupAnonymous->setName("Default Anonymous");
            $securityGroupAnonymous->setDescription("Created during auto configuration");

            $this->entityManager->persist($securityGroupAnonymous);

            $securityGroupUser = New SecurityGroup();
            $securityGroupUser->setName("Default User");
            $securityGroupUser->setDescription("Created during auto configuration");
            $securityGroupUser->setParent($securityGroupAnonymous);

            $this->entityManager->persist($securityGroupUser);

            $securityGroupAdmin = New SecurityGroup();
            $securityGroupAdmin->setName("Default Admin");
            $securityGroupAdmin->setDescription("Created during auto configuration");
            $securityGroupAdmin->setParent($securityGroupUser);
            $securityGroupAdmin->setScopes([
                "admin.DELETE"
                ]
            );

            $this->entityManager->persist($securityGroupAdmin);

        }
        else{
            $io->info('Security group found continuing');
        }

        // Handling users
        $io->section("Looking for an user");
        if(!$user = $this->entityManager->getRepository('App:User')->findOneBy([])){
            $io->info('No User found, creating a new one');
            $user = New User();
            $user->setName("Default User");
            $user->setDescription("Created during auto configuration");
            $user->setEmail("no-reply@test.com");
            $user->setPassword("!ChangeMe!");
            $user->addSecurityGroup($securityGroupAdmin);
            $user->addApplication($application);
            $user->setOrganisation($organization);

            $this->entityManager->persist($user);
        }
        else{
            $io->info('User found continuing');
        }

        $this->entityManager->flush();

        // Checking for dev env
        $io->section("Checking environment");
        $io->info('Environment is '. getenv("APP_ENV"));

        // In dev we also want to run the installer
        if( getenv("APP_ENV") == "dev"){
            $this->installationService->setStyle(new SymfonyStyle($input, $output));
            $this->installationService->composerupdate();
        }


        $io->success('Successfully finished setting basic configuration');

        return Command::SUCCESS;
    }
}
