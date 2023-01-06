<?php

// src/Command/ConfigureClustersCommand.php

namespace App\Command;

use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearAnonymousCacheCommand extends Command
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache, string $name = null)
    {
        $this->cache = $cache;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cache:clear:anonymous:scopes')
            // the short description shown while running "php bin/console list"
            ->setDescription('Resets cache for anonymous scopes')
            ->setHelp('This command will remove all anonymous scopes from the cache, useful if these scopes get changed.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Clear anonymous scopes');
        $io->section('Removing anonymous scopes from cache');
        $io->progressStart(1);
        $io->text('');

        if ($this->cache->invalidateTags(['anonymousScopes']) && $this->cache->invalidateTags(['anonymousOrg'])) {
            $io->progressFinish();
            $io->success('Anonymous scopes have been cleared from cache');

            return Command::SUCCESS;
        }
        $io->error('Failed to clear anonymous scopes from cache');

        return Command::FAILURE;
    }
}
