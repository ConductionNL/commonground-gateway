<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Entity\Application;
use App\Entity\Organization;
use App\Entity\SecurityGroup;
use App\Entity\User;
use App\Event\ActionEvent;
use CommonGateway\CoreBundle\Service\InstallationService;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
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
    private Logger $logger;
    private UserPasswordHasherInterface $hasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        RequestStack $requestStack,
        InstallationService $installationService,
        ParameterBagInterface $parameterBag,
        UserPasswordHasherInterface $hasher
    ) {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->session = $requestStack->getSession();
        $this->installationService = $installationService;
        $this->parameterBag = $parameterBag;
        $this->hasher = $hasher;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('bundle', 'b', InputOption::VALUE_OPTIONAL, 'The bundle that you want to install (install only that bundle)')
            ->addOption('data', 'd', InputOption::VALUE_OPTIONAL, 'Load (example) data set(s) from the bundle', false)
            ->addOption('skip-schema', 'sa', InputOption::VALUE_OPTIONAL, 'Don\'t update schema\'s during upgrade', false)
            ->addOption('skip-script', 'sp', InputOption::VALUE_OPTIONAL, 'Don\'t execute installation scripts during upgrade', false)
            ->addOption('unsafe', 'u', InputOption::VALUE_OPTIONAL, 'Delete data that is not present in the test data', false)
            // the short description shown while running "php bin/console list"
            ->setDescription('Facilitates the initialization of the gateway and checks configuration')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command is supposed to be run whenever a gateway initializes to make sure there is enough basic configuration to actually start the gateway');
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

        // Throw the event
        $io->info('Trowing commongateway.pre.initialization event');
        $event = new ActionEvent('commongateway.pre.initialization', []);
        $this->eventDispatcher->dispatch($event, 'commongateway.pre.initialization');

        $io->title('Check if we have the needed objects');

        // Handling Organizations
        $io->section('Looking for an organization');
        if (!$organization = $this->entityManager->getRepository('App:Organization')->findOneBy([])) {
            $io->info('No organization found, creating a new one');
            $organization = new Organization();
            $organization->setName('Default Organization');
            $organization->setReference('https://docs.commongateway.nl/organization/default.organization.json');

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
            $application->setReference('https://docs.commongateway.nl/application/default.application.json');
            $application->setDescription('Created during auto configuration');
            $domains = ['localhost'];
            $parsedAppUrl = parse_url($this->parameterBag->get('app_url'));
            if (isset($parsedAppUrl['host']) && !empty($parsedAppUrl['host']) && $parsedAppUrl['host'] !== 'localhost') {
                $domains[] = $parsedAppUrl['host'];
            }
            $application->setDomains($domains);
            $application->setOrganization($organization);
//            $application->setSecret('LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tCk1JSUV3QUlCQURBTkJna3Foa2lHOXcwQkFRRUZBQVNDQktvd2dnU21BZ0VBQW9JQkFRRFNQU1lacUs3cHdMTWQKd0VqSThkeTA2aVUzQUxESHFOMnBvbmJEb0tIN09KcFVpa0hkT09NVVhNYUhGOWwyb0ZBQmV1VnkrMjJlVHZTQwp3SGdFeUlkTlBEOHhnaWcrUVFlLzBEZlNIaWxGK01ZNGJYbzBUdW0wbDB1MFRDMDNqSUMvUHdiWmJkd3AyczdmClo5TVh1dzdZdnpLS2dzSTdXWWhlSGxGazlkQXc4OUg4SjVUbkY2N050YkdWRkdYTUk5ekpSejZ6N3JRUzJGMjEKcmIyanNhMU4yWkliZW5rbmFrQjdmTEk2VU1mVk5DTXBVZHdERUN6TXNTUTQ4L2dvMzdJYjhzbElGZ2NTUWcrNgp4U1JleG1BT0lBUDIxSitnVjZubGhiMGQ2V2VCcUliQ1hoYkhoY0RGc3NiVGlyTms5UElCcm1FdktGU1prdVV2Cks4Z0hzTituQWdNQkFBRUNnZ0VCQUlFZ1JaSms1R2wxalkyc1dBZnpaUmRJNkdxTDVnZjdVNG1vMjBEMEhBanMKanYxMW5WWitaaHBQa1MvUUdpU2QrZ1d1c2RhWlRvNTQ5L3lHc2pCZDZad3FjTFc3dDNQbEJSbHVqWnBrSS8xeAorbTBWOElUSUl3cGtFbjgrZWxjdjJMT2R4bHNzK3BoS1o5MFhLN1BibEJiVDkvclNyUEUrNEY3T1NEZTJNcFNkClRvVi9XQis1SC90eFM1TVpIRHM3OTA3RFpLemdkdUZyVHBPRUZQeUp3MnoxbWNRbzhnK2ZTbm90Q1ZncUFFRFcKcXFSNHpmK05WTUdId3A3WC9Lc3k1eWdkZnZ3QXpaVEVaUmFzVHVnN1JDdHhyVUp0d2lEMk9rdmNoMzNXYzA1VQoyVnVqdmk5ODhuY1hWeVZOOG8wbnU4aUNRV25veUM5SnJDRnlTU000cjJFQ2dZRUEraWJJb2E2aHlGNkNIcFYxCk9uYmIwT2ZleVhMS01vTkNDN2FYdXFHUVBsTG52K3FXUFJqRXdWR3Mvb3NrS2hFRWhsSEdvamc1dkhSQ1g2T3AKSTlJSis4U201d08xSlJFUFFNdmdza0ZWR3NlZVF5UXNYajJmRmp5cDZ4RGhHMlFzNlFpZHRuZEN6L1hOZWxBQQowNmVkbHl0L0I3Z1d1dWtoYWQ1SjhrT3UzeVVDZ1lFQTF5ZDZmYkhtNFQyMmkxQXZYYTBaSG8rME1oZUVGYTg0ClpaZXVtSmNKaTd3NnFjR016ejkwNno5V0lPT3ZvTk9WZWdVOHMvb2labUZiMXZ3bmE2SnJRZWpid0ZJckN0TlIKYlF3SmsrVkIrY0RJckxtTjhuclBacVM0THY0Y3hOOWlMYWtVMlpid1Jrb1NQSldlaUM2SWt5SWNEYzVxTVpCZwpubTJxSVlxQW45c0NnWUVBbEZwNTlFRlFHemZKYlgvdjFTdDJjKzkvbGZNbzdVb2cyamVBeHFOWW0wMnB1WXpUCmF3cU1iYVlWdGFRcFgzVldQSjYwOGJIc3M5SXpKdXMxdlZPc3JnN1RlUUFlNXd1MkF4U21mckQyV3ZwMTVwWEcKWm1HZlBwM2RtOVlYMnBuUGRLaXlkK3RFeVhhYVZPYXJodHJLUUVRQWcwQnU0b3l1VDA0UWhzZ1RKcTBDZ1lFQQpuWmRTRmkwdmduM1VibGh1U1R3WHNSWHJFK0c3b3JKMEtaMmZpaTdmRkJYc0Zoa3B6VWVhbVJFTVFnemp3SFlaCi80VkVnRU5QM1JPazFHUmZiMnhKQ2I3STd5YUFWbTZRTHNKcFpZVy8vSEtqeWpnamE1OWV1TDBnRjNPVG1QUlMKRWtYTmVzOGU4UzBpREhRKzZWckVPSmo4V1hSK3ZnMFZhQlhGVHNvSENvOENnWUVBOWhpRVlVTXZFNTdQMytmSgptZmZSMytwamJ6WDF0dWY2K0FqcGFrTjNodDFhWUJmaXJzRTlDTHpZdVBqSW4zdU1DRWZvdVFkMGc3c01UcGJnCmxBVXlkaWNPMFNvdDNmSHBLV0F5R3laOVRWYlhIaGUrb1FJYUN1VWFaanlQSFFPWWhHTzZwV0ZDam9aV01JZUwKZjBGcVg0UFExZEJPd3drNDl2Vm16YTJIY1RzPQotLS0tLUVORCBQUklWQVRFIEtFWS0tLS0tCg=='); // todo genreate
//            $application->setPublic('LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUQ4VENDQXRtZ0F3SUJBZ0lVVTlMMXFhdGI5WndXTGd4Rlg1VHB0bjNiQytjd0RRWUpLb1pJaHZjTkFRRUwKQlFBd2dZY3hDekFKQmdOVkJBWVRBazVNTVJZd0ZBWURWUVFJREExT2IyOXlaQ0JJYjJ4c1lXNWtNUkl3RUFZRApWUVFIREFsQmJYTjBaWEprWVcweEV6QVJCZ05WQkFvTUNrTnZibVIxWTNScGIyNHhFakFRQmdOVkJBTU1DV3h2ClkyRnNhRzl6ZERFak1DRUdDU3FHU0liM0RRRUpBUllVY205aVpYSjBRR052Ym1SMVkzUnBiMjR1Ym13d0hoY04KTWpFd09UQXlNRGMxTWpVd1doY05Nakl3T1RBeU1EYzFNalV3V2pDQmh6RUxNQWtHQTFVRUJoTUNUa3d4RmpBVQpCZ05WQkFnTURVNXZiM0prSUVodmJHeGhibVF4RWpBUUJnTlZCQWNNQ1VGdGMzUmxjbVJoYlRFVE1CRUdBMVVFCkNnd0tRMjl1WkhWamRHbHZiakVTTUJBR0ExVUVBd3dKYkc5allXeG9iM04wTVNNd0lRWUpLb1pJaHZjTkFRa0IKRmhSeWIySmxjblJBWTI5dVpIVmpkR2x2Ymk1dWJEQ0NBU0l3RFFZSktvWklodmNOQVFFQkJRQURnZ0VQQURDQwpBUW9DZ2dFQkFOSTlKaG1vcnVuQXN4M0FTTWp4M0xUcUpUY0FzTWVvM2FtaWRzT2dvZnM0bWxTS1FkMDQ0eFJjCnhvY1gyWGFnVUFGNjVYTDdiWjVPOUlMQWVBVEloMDA4UHpHQ0tENUJCNy9RTjlJZUtVWDR4amh0ZWpSTzZiU1gKUzdSTUxUZU1nTDgvQnRsdDNDbmF6dDluMHhlN0R0aS9Nb3FDd2p0WmlGNGVVV1QxMEREejBmd25sT2NYcnMyMQpzWlVVWmN3ajNNbEhQclB1dEJMWVhiV3R2YU94clUzWmtodDZlU2RxUUh0OHNqcFF4OVUwSXlsUjNBTVFMTXl4CkpEanorQ2pmc2h2eXlVZ1dCeEpDRDdyRkpGN0dZQTRnQS9iVW42QlhxZVdGdlIzcFo0R29oc0plRnNlRndNV3kKeHRPS3MyVDA4Z0d1WVM4b1ZKbVM1UzhyeUFldzM2Y0NBd0VBQWFOVE1GRXdIUVlEVlIwT0JCWUVGRlRRbENzNwo3c1RzTXF1V3dCUjFORXZndWRYVk1COEdBMVVkSXdRWU1CYUFGRlRRbENzNzdzVHNNcXVXd0JSMU5Fdmd1ZFhWCk1BOEdBMVVkRXdFQi93UUZNQU1CQWY4d0RRWUpLb1pJaHZjTkFRRUxCUUFEZ2dFQkFJV3ZpZldiV3FQNklUd20KS1d2YW9Lc1JIc1BzZ0dxYUF2OXlkZ24xSVp2WFQzZGg3eXpOdHFJRXNDcHpvc2c0Zis3Rlo2bG5GZHI2RFZVbgpmMGh3bUV4ems3d1lYdjBtd2xCTHg1alJmb0tKbDA2SFdkVHVFYUJwR2JYR1Y3VHZjQ3dEb1hYenlYYkljT1FqCnFMeGM0RlE5dlZXazRQM0Y1dDZ6dFh3TWVYWDhZeVYwaGN4cXRaVHNsL25ZVDFvc2pKUlBlWDBRRXFXTjJKZ1QKTGFwckNwK0ZOOXI2WlRwb3EybXVwNmcxTE9HaW1md1k3VDR4elhEaUNlMk1FMi93azBuczBpZXFmeFpwQk5mMApaYitZTHBPekVIYmNlS3dqSEJxT016VlJTZy93bW9sN05VWEF5YkZUNlMwaU5EQWlVSUJ6Q2xUZDhPQ0ZlYXB6CmdwYmROaDA9Ci0tLS0tRU5EIENFUlRJRklDQVRFLS0tLS0='); // todo genreate
//            $application->setPublicKey('LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUQ4VENDQXRtZ0F3SUJBZ0lVVTlMMXFhdGI5WndXTGd4Rlg1VHB0bjNiQytjd0RRWUpLb1pJaHZjTkFRRUwKQlFBd2dZY3hDekFKQmdOVkJBWVRBazVNTVJZd0ZBWURWUVFJREExT2IyOXlaQ0JJYjJ4c1lXNWtNUkl3RUFZRApWUVFIREFsQmJYTjBaWEprWVcweEV6QVJCZ05WQkFvTUNrTnZibVIxWTNScGIyNHhFakFRQmdOVkJBTU1DV3h2ClkyRnNhRzl6ZERFak1DRUdDU3FHU0liM0RRRUpBUllVY205aVpYSjBRR052Ym1SMVkzUnBiMjR1Ym13d0hoY04KTWpFd09UQXlNRGMxTWpVd1doY05Nakl3T1RBeU1EYzFNalV3V2pDQmh6RUxNQWtHQTFVRUJoTUNUa3d4RmpBVQpCZ05WQkFnTURVNXZiM0prSUVodmJHeGhibVF4RWpBUUJnTlZCQWNNQ1VGdGMzUmxjbVJoYlRFVE1CRUdBMVVFCkNnd0tRMjl1WkhWamRHbHZiakVTTUJBR0ExVUVBd3dKYkc5allXeG9iM04wTVNNd0lRWUpLb1pJaHZjTkFRa0IKRmhSeWIySmxjblJBWTI5dVpIVmpkR2x2Ymk1dWJEQ0NBU0l3RFFZSktvWklodmNOQVFFQkJRQURnZ0VQQURDQwpBUW9DZ2dFQkFOSTlKaG1vcnVuQXN4M0FTTWp4M0xUcUpUY0FzTWVvM2FtaWRzT2dvZnM0bWxTS1FkMDQ0eFJjCnhvY1gyWGFnVUFGNjVYTDdiWjVPOUlMQWVBVEloMDA4UHpHQ0tENUJCNy9RTjlJZUtVWDR4amh0ZWpSTzZiU1gKUzdSTUxUZU1nTDgvQnRsdDNDbmF6dDluMHhlN0R0aS9Nb3FDd2p0WmlGNGVVV1QxMEREejBmd25sT2NYcnMyMQpzWlVVWmN3ajNNbEhQclB1dEJMWVhiV3R2YU94clUzWmtodDZlU2RxUUh0OHNqcFF4OVUwSXlsUjNBTVFMTXl4CkpEanorQ2pmc2h2eXlVZ1dCeEpDRDdyRkpGN0dZQTRnQS9iVW42QlhxZVdGdlIzcFo0R29oc0plRnNlRndNV3kKeHRPS3MyVDA4Z0d1WVM4b1ZKbVM1UzhyeUFldzM2Y0NBd0VBQWFOVE1GRXdIUVlEVlIwT0JCWUVGRlRRbENzNwo3c1RzTXF1V3dCUjFORXZndWRYVk1COEdBMVVkSXdRWU1CYUFGRlRRbENzNzdzVHNNcXVXd0JSMU5Fdmd1ZFhWCk1BOEdBMVVkRXdFQi93UUZNQU1CQWY4d0RRWUpLb1pJaHZjTkFRRUxCUUFEZ2dFQkFJV3ZpZldiV3FQNklUd20KS1d2YW9Lc1JIc1BzZ0dxYUF2OXlkZ24xSVp2WFQzZGg3eXpOdHFJRXNDcHpvc2c0Zis3Rlo2bG5GZHI2RFZVbgpmMGh3bUV4ems3d1lYdjBtd2xCTHg1alJmb0tKbDA2SFdkVHVFYUJwR2JYR1Y3VHZjQ3dEb1hYenlYYkljT1FqCnFMeGM0RlE5dlZXazRQM0Y1dDZ6dFh3TWVYWDhZeVYwaGN4cXRaVHNsL25ZVDFvc2pKUlBlWDBRRXFXTjJKZ1QKTGFwckNwK0ZOOXI2WlRwb3EybXVwNmcxTE9HaW1md1k3VDR4elhEaUNlMk1FMi93azBuczBpZXFmeFpwQk5mMApaYitZTHBPekVIYmNlS3dqSEJxT016VlJTZy93bW9sN05VWEF5YkZUNlMwaU5EQWlVSUJ6Q2xUZDhPQ0ZlYXB6CmdwYmROaDA9Ci0tLS0tRU5EIENFUlRJRklDQVRFLS0tLS0='); // todo genreate

            $application->setPublicKey('-----BEGIN CERTIFICATE-----
MIID8TCCAtmgAwIBAgIUU9L1qatb9ZwWLgxFX5Tptn3bC+cwDQYJKoZIhvcNAQEL
BQAwgYcxCzAJBgNVBAYTAk5MMRYwFAYDVQQIDA1Ob29yZCBIb2xsYW5kMRIwEAYD
VQQHDAlBbXN0ZXJkYW0xEzARBgNVBAoMCkNvbmR1Y3Rpb24xEjAQBgNVBAMMCWxv
Y2FsaG9zdDEjMCEGCSqGSIb3DQEJARYUcm9iZXJ0QGNvbmR1Y3Rpb24ubmwwHhcN
MjEwOTAyMDc1MjUwWhcNMjIwOTAyMDc1MjUwWjCBhzELMAkGA1UEBhMCTkwxFjAU
BgNVBAgMDU5vb3JkIEhvbGxhbmQxEjAQBgNVBAcMCUFtc3RlcmRhbTETMBEGA1UE
CgwKQ29uZHVjdGlvbjESMBAGA1UEAwwJbG9jYWxob3N0MSMwIQYJKoZIhvcNAQkB
FhRyb2JlcnRAY29uZHVjdGlvbi5ubDCCASIwDQYJKoZIhvcNAQEBBQADggEPADCC
AQoCggEBANI9JhmorunAsx3ASMjx3LTqJTcAsMeo3amidsOgofs4mlSKQd044xRc
xocX2XagUAF65XL7bZ5O9ILAeATIh008PzGCKD5BB7/QN9IeKUX4xjhtejRO6bSX
S7RMLTeMgL8/Btlt3Cnazt9n0xe7Dti/MoqCwjtZiF4eUWT10DDz0fwnlOcXrs21
sZUUZcwj3MlHPrPutBLYXbWtvaOxrU3Zkht6eSdqQHt8sjpQx9U0IylR3AMQLMyx
JDjz+CjfshvyyUgWBxJCD7rFJF7GYA4gA/bUn6BXqeWFvR3pZ4GohsJeFseFwMWy
xtOKs2T08gGuYS8oVJmS5S8ryAew36cCAwEAAaNTMFEwHQYDVR0OBBYEFFTQlCs7
7sTsMquWwBR1NEvgudXVMB8GA1UdIwQYMBaAFFTQlCs77sTsMquWwBR1NEvgudXV
MA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAIWvifWbWqP6ITwm
KWvaoKsRHsPsgGqaAv9ydgn1IZvXT3dh7yzNtqIEsCpzosg4f+7FZ6lnFdr6DVUn
f0hwmExzk7wYXv0mwlBLx5jRfoKJl06HWdTuEaBpGbXGV7TvcCwDoXXzyXbIcOQj
qLxc4FQ9vVWk4P3F5t6ztXwMeXX8YyV0hcxqtZTsl/nYT1osjJRPeX0QEqWN2JgT
LaprCp+FN9r6ZTpoq2mup6g1LOGimfwY7T4xzXDiCe2ME2/wk0ns0ieqfxZpBNf0
Zb+YLpOzEHbceKwjHBqOMzVRSg/wmol7NUXAybFT6S0iNDAiUIBzClTd8OCFeapz
gpbdNh0=
-----END CERTIFICATE-----');

            $application->setPrivateKey('-----BEGIN PRIVATE KEY-----
MIIEwAIBADANBgkqhkiG9w0BAQEFAASCBKowggSmAgEAAoIBAQDSPSYZqK7pwLMd
wEjI8dy06iU3ALDHqN2ponbDoKH7OJpUikHdOOMUXMaHF9l2oFABeuVy+22eTvSC
wHgEyIdNPD8xgig+QQe/0DfSHilF+MY4bXo0Tum0l0u0TC03jIC/PwbZbdwp2s7f
Z9MXuw7YvzKKgsI7WYheHlFk9dAw89H8J5TnF67NtbGVFGXMI9zJRz6z7rQS2F21
rb2jsa1N2ZIbenknakB7fLI6UMfVNCMpUdwDECzMsSQ48/go37Ib8slIFgcSQg+6
xSRexmAOIAP21J+gV6nlhb0d6WeBqIbCXhbHhcDFssbTirNk9PIBrmEvKFSZkuUv
K8gHsN+nAgMBAAECggEBAIEgRZJk5Gl1jY2sWAfzZRdI6GqL5gf7U4mo20D0HAjs
jv11nVZ+ZhpPkS/QGiSd+gWusdaZTo549/yGsjBd6ZwqcLW7t3PlBRlujZpkI/1x
+m0V8ITIIwpkEn8+elcv2LOdxlss+phKZ90XK7PblBbT9/rSrPE+4F7OSDe2MpSd
ToV/WB+5H/txS5MZHDs7907DZKzgduFrTpOEFPyJw2z1mcQo8g+fSnotCVgqAEDW
qqR4zf+NVMGHwp7X/Ksy5ygdfvwAzZTEZRasTug7RCtxrUJtwiD2Okvch33Wc05U
2Vujvi988ncXVyVN8o0nu8iCQWnoyC9JrCFySSM4r2ECgYEA+ibIoa6hyF6CHpV1
Onbb0OfeyXLKMoNCC7aXuqGQPlLnv+qWPRjEwVGs/oskKhEEhlHGojg5vHRCX6Op
I9IJ+8Sm5wO1JREPQMvgskFVGseeQyQsXj2fFjyp6xDhG2Qs6QidtndCz/XNelAA
06edlyt/B7gWuukhad5J8kOu3yUCgYEA1yd6fbHm4T22i1AvXa0ZHo+0MheEFa84
ZZeumJcJi7w6qcGMzz906z9WIOOvoNOVegU8s/oiZmFb1vwna6JrQejbwFIrCtNR
bQwJk+VB+cDIrLmN8nrPZqS4Lv4cxN9iLakU2ZbwRkoSPJWeiC6IkyIcDc5qMZBg
nm2qIYqAn9sCgYEAlFp59EFQGzfJbX/v1St2c+9/lfMo7Uog2jeAxqNYm02puYzT
awqMbaYVtaQpX3VWPJ608bHss9IzJus1vVOsrg7TeQAe5wu2AxSmfrD2Wvp15pXG
ZmGfPp3dm9YX2pnPdKiyd+tEyXaaVOarhtrKQEQAg0Bu4oyuT04QhsgTJq0CgYEA
nZdSFi0vgn3UblhuSTwXsRXrE+G7orJ0KZ2fii7fFBXsFhkpzUeamREMQgzjwHYZ
/4VEgENP3ROk1GRfb2xJCb7I7yaAVm6QLsJpZYW//HKjyjgja59euL0gF3OTmPRS
EkXNes8e8S0iDHQ+6VrEOJj8WXR+vg0VaBXFTsoHCo8CgYEA9hiEYUMvE57P3+fJ
mffR3+pjbzX1tuf6+AjpakN3ht1aYBfirsE9CLzYuPjIn3uMCEfouQd0g7sMTpbg
lAUydicO0Sot3fHpKWAyGyZ9TVbXHhe+oQIaCuUaZjyPHQOYhGO6pWFCjoZWMIeL
f0FqX4PQ1dBOwwk49vVmza2HcTs=
-----END PRIVATE KEY-----
');

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
            $securityGroupAnonymous->setReference('https://docs.commongateway.nl/securityGroup/default.anonymous.securityGroup.json');
            $securityGroupAnonymous->setDescription('Created during auto configuration');
            $securityGroupAnonymous->setAnonymous(true);

            $this->entityManager->persist($securityGroupAnonymous);

            $securityGroupUser = new SecurityGroup();
            $securityGroupUser->setName('Default User');
            $securityGroupUser->setReference('https://docs.commongateway.nl/securityGroup/default.user.securityGroup.json');
            $securityGroupUser->setDescription('Created during auto configuration');
            $securityGroupUser->setParent($securityGroupAnonymous);

            $this->entityManager->persist($securityGroupUser);

            $securityGroupAdmin = new SecurityGroup();
            $securityGroupAdmin->setName('Default Admin');
            $securityGroupAdmin->setReference('https://docs.commongateway.nl/securityGroup/default.admin.securityGroup.json');
            $securityGroupAdmin->setDescription('Created during auto configuration');
            $securityGroupAdmin->setParent($securityGroupUser);
            $securityGroupAdmin->setScopes(
                [
                    'user',
                    'group.admin',
                    'admin.GET',
                    'admin.POST',
                    'admin.PATCH',
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
            $io->info('No User found, creating a default and APIKEY one');

            $user = new User();
            $user->setName('Default User');
            $user->setReference('https://docs.commongateway.nl/user/default.user.json');
            $user->setDescription('Created during auto configuration');
            $user->setEmail('no-reply@test.com');
            $user->setPassword($this->hasher->hashPassword($user, '!ChangeMe!'));
            $user->addSecurityGroup($securityGroupAdmin);
            $user->addApplication($application);
            $user->setOrganization($organization);

            $this->entityManager->persist($user);

            $apikeyUser = new User();
            $apikeyUser->setName('APIKEY_USER');
            $apikeyUser->setReference('https://docs.commongateway.nl/user/default.apikey.user.json');
            $apikeyUser->setDescription('Created during auto configuration');
            $apikeyUser->setEmail('apikey@test.com');
            $apikeyUser->setPassword($this->hasher->hashPassword($apikeyUser, '!ChangeMe!'));
            $apikeyUser->addSecurityGroup($securityGroupAdmin);
            $apikeyUser->addApplication($application);
            $apikeyUser->setOrganization($organization);

            $this->entityManager->persist($apikeyUser);
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
        $this->installationService->update($config, $io);
        //}

        $io->success('Successfully finished setting basic configuration');

        // todo: actualy throw it
        $io->info('Trowing commongateway.post.initialization event');

        // Throw the event
        $event = new ActionEvent('commongateway.post.initialization', []);
        $this->eventDispatcher->dispatch($event, 'commongateway.post.initialization');

        return Command::SUCCESS;
    }
}
