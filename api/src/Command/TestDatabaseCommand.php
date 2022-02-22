<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Service\OasParserService;
use App\Service\StorageORMService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestDatabaseCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'test:database';
    protected StorageORMService $storageORMService;
    protected EntityManagerInterface $entityManager;

    public function __construct(StorageORMService $storageORMService, EntityManagerInterface $entityManager, string $url = '', string $gateway = '')
    {
        $this->storageORMService = $storageORMService;
        $this->url = $url;
        $this->gateway = $gateway;
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Tests the database setup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'weer']);

        $this->storageORMService->createResource($entity, []);

        return Command::SUCCESS;
    }
}
