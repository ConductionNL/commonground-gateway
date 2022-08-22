<?php

// src/Command/ConfigureClustersCommand.php

namespace App\Command;

use App\Entity\CollectionEntity;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Service\FunctionService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearObjectsFromCacheCommand extends Command
{
    private InputInterface $input;
    private OutputInterface $output;
    private CacheInterface $cache;
    private FunctionService $functionService;
    private EntityManagerInterface $entityManager;

    public function __construct(CacheInterface $cache, FunctionService $functionService, EntityManagerInterface $entityManager, string $name = null)
    {
        $this->cache = $cache;
        $this->functionService = $functionService;
        $this->entityManager = $entityManager;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cache:clear:objects')
            // the short description shown while running "php bin/console list"
            ->setDescription('Resets cache for saved object responses')
            ->setHelp('This command will remove all stored responses for the given objects from the cache (or all objects for a specific entity or collection, or just all objects that exist), useful if, for example, an entity is changed.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $helper = $this->getHelper('question');
        $type = $this->askForType($helper);
        $io = new SymfonyStyle($input, $output);

        if ($type !== 'AllObjects') {
            $ids = $this->getIdsAndStartProgress($io, $helper, $type);
            $total = count($ids);
        }

        $this->functionService->removeResultFromCache = [];
        switch ($type) {
            case 'Object':
                $errorCount = $this->handleSwitchType($io, $ids ?? [], 'ObjectEntity', 'Object from cache');
                break;
            case 'Entity':
                $errorCount = $this->handleSwitchType($io, $ids ?? [], 'Entity', 'Objects from cache for Entity');
                break;
            case 'Collection':
                $errorCount = $this->handleSwitchType($io, $ids ?? [], 'CollectionEntity', 'Objects from cache for Collection');
                break;
            case 'AllObjects':
                $objectEntities = $this->getAllObjectsAndStartProgress($io, $helper);
                if ($objectEntities === null) {
                    return Command::SUCCESS;
                }
                $total = count($objectEntities);

                $errorCount = $this->removeAllObjectsFromCache($io, $objectEntities);
                break;
            default:
                return Command::INVALID;
        }
        $io->progressFinish();

        return $this->handleExecuteResponse($io, $type, $errorCount, $total ?? 0);
    }

    private function askForType(QuestionHelper $helper): string
    {
        $question = new ChoiceQuestion(
            'What type of entity are you going to give id\'s for? (Object, Entity, Collection or AllObjects. Default = Object. AllObjects will remove all objects from cache)',
            ['Object', 'Entity', 'Collection', 'AllObjects'],
            '0'
        );
        $question->setErrorMessage('Type %s is invalid.');
        $type = $helper->ask($this->input, $this->output, $question);
        $this->output->writeln('You have just selected: '.$type);

        return $type;
    }

    private function getIdsAndStartProgress(SymfonyStyle $io, QuestionHelper $helper, string $type): array
    {
        $question = new Question('Now please give one or more uuids for Type '.$type.' (Use Enter to start adding one more id and Ctrl+D to stop adding more)', 'NO UUID INPUT');
        $question->setMultiline(true);
        $ids = $helper->ask($this->input, $this->output, $question);
        $ids = explode(PHP_EOL, $ids);
        $total = count($ids);

        $io->title('Clear Objects from cache');
        $io->section('Removing Objects from cache for Type: '.$type.' ('.$total.' id\'s given)');
        $io->progressStart($total);
        $io->newLine();

        return $ids;
    }

    private function getAllObjectsAndStartProgress(SymfonyStyle $io, QuestionHelper $helper): ?array
    {
        $question = new ConfirmationQuestion('Are you sure you want to remove all objects from cache? (y/n) (default = n)', false);

        if (!$helper->ask($this->input, $this->output, $question)) {
            return null;
        }
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
        $total = count($objectEntities);

        $io->title('Clear Objects from cache');
        $io->section('Removing all Objects from cache ('.$total.' objects found)');
        $io->progressStart($total);
        $io->newLine();

        return $objectEntities;
    }

    private function handleSwitchType(SymfonyStyle $io, array $ids, string $entityName, string $ioSectionText): int
    {
        $errorCount = 0;
        foreach ($ids as $id) {
            $io->newLine();
            $io->section("Removing {$ioSectionText} with id: {$id}");
            if (!Uuid::isValid($id)) {
                $io->error($id. ' is not a valid uuid!');
                $errorCount++;
                $io->progressAdvance();
                continue;
            }
            $object = $this->entityManager->getRepository('App:'.$entityName)->find($id);
            if ($object instanceof ObjectEntity) {
                $this->removeObjectFromCache($io, $object, $id);
            } elseif ($object instanceof Entity) {
                $this->removeEntityObjectsFromCache($io, $object, $id);
            } elseif ($object instanceof CollectionEntity) {
                $this->removeCollectionObjectsFromCache($io, $object, $id);
            } else {
                $io->error("Could not find an {$entityName} with this id: {$id}");
                $errorCount++;
            }
            $io->progressAdvance();
        }

        return $errorCount;
    }

    private function removeObjectFromCache(SymfonyStyle $io, ObjectEntity $objectEntity, string $id)
    {
        $this->functionService->removeResultFromCache($objectEntity);
        $io->text("Successfully removed Object with id: {$id} (of Entity type: {$objectEntity->getEntity()->getName()}) from cache");
    }

    private function removeEntityObjectsFromCache(SymfonyStyle $io, Entity $entity, string $id)
    {
        foreach ($entity->getObjectEntities() as $objectEntity) {
            $this->removeObjectFromCache($io, $objectEntity, $objectEntity->getId()->toString());
        }
        $io->text("Successfully removed all Objects from cache for the Entity with id: {$id} (and name: {$entity->getName()})");
    }

    private function removeCollectionObjectsFromCache(SymfonyStyle $io, CollectionEntity $collection, string $id)
    {
        foreach ($collection->getEntities() as $entity) {
            $this->removeEntityObjectsFromCache($io, $entity, $entity->getId()->toString());
        }
        $io->text("Successfully removed all Objects from cache for the Collection with id: {$id} (and name: {$collection->getName()})");
    }

    private function removeAllObjectsFromCache(SymfonyStyle $io, array $objectEntities): int
    {
        $io->newLine();
        foreach ($objectEntities as $objectEntity) {
            $id = $objectEntity->getId()->toString();
            $io->text('');
            $io->section("Removing Object from cache with id: {$id}");
            $this->removeObjectFromCache($io, $objectEntity, $id);
            $io->progressAdvance();
        }

        return 0;
    }

    private function handleExecuteResponse(SymfonyStyle $io, string $type, int $errorCount, int $total): int
    {
        $errors = round($errorCount / $total * 100) == 0 && $errorCount > 0 ? 1 : round($errorCount / $total * 100);
        $typeString = $type !== 'Object' && $type !== 'AllObjects' ? 'for Type '.$type : '';
        if ($errors == 0) {
            $io->success("All Objects $typeString have been removed from cache");
        } elseif ($errors < 20) {
            $io->warning("Some Objects $typeString could not be removed from cache. Failure rate (per $type) is $errors%");
        } else {
            $io->error("A lot of Objects $typeString could not be removed from cache. Failure rate (per $type) is $errors%");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
