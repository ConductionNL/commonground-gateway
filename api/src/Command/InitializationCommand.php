<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Entity\Application;
use App\Entity\Organization;
use App\Entity\SecurityGroup;
use App\Entity\User;
use CommonGateway\CoreBundle\Service\InstallationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class InitializationCommand extends Command
{
    private InputInterface $input;
    private OutputInterface $output;
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'commongateway:initialize';
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private SessionInterface $session;
    private ParameterBagInterface $parameterBag;
    private InstallationService $installationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        SessionInterface $session,
        InstallationService $installationService,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->session = $session;
        $this->installationService = $installationService;
        $this->parameterBag = $parameterBag;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('bundle', 'b', InputOption::VALUE_OPTIONAL, 'The bundle that you want to install (install only that bundle)')
            ->addOption('data', 'd', InputOption::VALUE_OPTIONAL, 'Load (example) data set(s) from the bundle', false)
            ->addOption('skip-schema', 'sa', InputOption::VALUE_OPTIONAL, 'Don\'t update schema\'s during upgrade', false)
            ->addOption('skip-script', 'sp', InputOption::VALUE_OPTIONAL, 'Don\'t execute installation scripts during upgrade', false)
            ->addOption('unsafe', 'u', InputOption::VALUE_OPTIONAL, 'Delete data that is not pressent in the test data', false)
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

        $config = [];
        $config['bundle'] = $input->getOption('bundle');
        $config['data'] = $input->getOption('data');
        $config['skip-schema'] = $input->getOption('skip-schema');
        $config['skip-script'] = $input->getOption('skip-script');
        $config['unsafe'] = $input->getOption('unsafe');

        $io->title('Check if we have the needed objects');

        // Handling Organizations
        $io->section('Looking for an organization');
        if (!$organization = $this->entityManager->getRepository('App:Organization')->findOneBy([])) {
            $io->info('No organization found, creating a new one');
            $organization = new Organization();
            $organization->setName('Default Organization');

            // Set default id to this id for now (backwards compatibility)
            $id = 'a1c8e0b6-2f78-480d-a9fb-9792142f4761';
            // Create the entity
            $this->entityManager->persist($organization);
            $this->entityManager->flush();
            $this->entityManager->refresh($organization);
            // Reset the id
            $organization->setId($id);
            $this->entityManager->persist($organization);
            $this->entityManager->flush();
            $organization = $this->entityManager->getRepository('App:Organization')->findOneBy(['id' => $id]);

            $organization->setDescription('Created during auto configuration');

            $this->entityManager->persist($organization);
        } else {
            $io->info('Organization found, continuing....');
        }

        // Handling Applications
        $io->section('Looking for an application');
        if (!$application = $this->entityManager->getRepository('App:Application')->findOneBy([])) {
            $io->info('No application found, creating a new one');
            $application = new Application();
            $application->setName('Default Application');
            $application->setDescription('Created during auto configuration');
            $domains = ['localhost'];
            $parsedAppUrl = parse_url($this->parameterBag->get('app_url'));
            if (isset($parsedAppUrl['host']) && !empty($parsedAppUrl['host']) && $parsedAppUrl['host'] !== 'localhost') {
                $domains[] = $parsedAppUrl['host'];
            }
            $application->setDomains($domains);
            $application->setOrganization($organization);

            $this->entityManager->persist($application);
        } else {
            $io->info('Application found, continuing....');
        }

        // Handling user groups
        $io->section('Looking for a security group');
        if (!$securityGroupAdmin = $this->entityManager->getRepository('App:SecurityGroup')->findOneBy([])) {
            $io->info('No securityGroup found, creating an anonymous, user and admin one');

            $securityGroupAnonymous = new SecurityGroup();
            $securityGroupAnonymous->setName('Default Anonymous');
            $securityGroupAnonymous->setDescription('Created during auto configuration');

            $this->entityManager->persist($securityGroupAnonymous);

            $securityGroupUser = new SecurityGroup();
            $securityGroupUser->setName('Default User');
            $securityGroupUser->setDescription('Created during auto configuration');
            $securityGroupUser->setParent($securityGroupAnonymous);

            $this->entityManager->persist($securityGroupUser);

            $securityGroupAdmin = new SecurityGroup();
            $securityGroupAdmin->setName('Default Admin');
            $securityGroupAdmin->setDescription('Created during auto configuration');
            $securityGroupAdmin->setParent($securityGroupUser);
            $securityGroupAdmin->setScopes(
                [
                    'admin.GET',
                    'admin.POST',
                    'admin.PUT',
                    'admin.DELETE',
                ]
            );

            $this->entityManager->persist($securityGroupAdmin);
        } else {
            $io->info('Security group found, continuing....');
        }

        // Handling users
        $io->section('Looking for an user');
        if (!$user = $this->entityManager->getRepository('App:User')->findOneBy([])) {
            $io->info('No User found, creating a new one');
            $user = new User();
            $user->setName('Default User');
            $user->setDescription('Created during auto configuration');
            $user->setEmail('no-reply@test.com');
            $user->setPassword('!ChangeMe!');
            $user->addSecurityGroup($securityGroupAdmin);
            $user->addApplication($application);
            $user->setOrganization($organization);

            $this->entityManager->persist($user);
        } else {
            $io->info('User found, continuing....');
        }

        $this->entityManager->flush();

        // Checking for dev env
        //$io->section("Checking environment");
        //$io->info('Environment is '. getenv("APP_ENV"));

        // In dev we also want to run the installer
        //if( getenv("APP_ENV") == "dev"){
        $io->section('Running installer');
        $this->installationService->setStyle(new SymfonyStyle($input, $output));
        $this->installationService->composerupdate($config);
        //}

        $io->success('Successfully finished setting basic configuration');

        return Command::SUCCESS;
    }
}
