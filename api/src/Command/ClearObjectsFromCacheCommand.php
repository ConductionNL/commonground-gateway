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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearObjectsFromCacheCommand extends Command
{
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
            ->setDescription('Resets cache for objects')
            ->setHelp('This command will remove all stored responses for the given objects from the cache (or all objects for a specific entity or collection, or just all objects that exist), useful if, for example, an entity is changed.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'What type of entity are you going to give id\'s for? (Object, Entity, Collection or AllObjects. Default = Object. AllObjects will remove all objects from cache)',
            ['Object', 'Entity', 'Collection', 'AllObjects'],
            '0'
        );
        $question->setErrorMessage('Type %s is invalid.');
        $type = $helper->ask($input, $output, $question);
        $output->writeln('You have just selected: '.$type);

        if ($type !== 'AllObjects') {
            $question = new Question('Now please give one or more uuids for Type '.$type.' (Use Enter to start adding one more id and Ctrl+D to stop adding more)', 'NO UUID INPUT');
            $question->setMultiline(true);
            $ids = $helper->ask($input, $output, $question);
            $ids = explode(PHP_EOL, $ids);
            $total = count($ids);

            $io = new SymfonyStyle($input, $output);
            $io->title('Clear Objects from cache');
            $io->section('Removing Objects from cache for Type: '.$type.' ('.$total.' id\'s given)');
            $io->progressStart($total);
            $io->text('');
        }

        switch ($type) {
            case 'Object':
                $errorCount = $this->handleTypeObject($io, $ids);
                break;
            case 'Entity':
                $errorCount = $this->handleTypeEntity($io, $ids);
                break;
            case 'Collection':
                $errorCount = $this->handleTypeCollection($io, $ids);
                break;
            case 'AllObjects':
                $question = new ConfirmationQuestion('Are you sure you want to remove all objects from cache? (y/n) (default = n)', false);

                if (!$helper->ask($input, $output, $question)) {
                    return Command::SUCCESS;
                }
                $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
                $total = count($objectEntities);

                $io = new SymfonyStyle($input, $output);
                $io->title('Clear Objects from cache');
                $io->section('Removing all Objects from cache ('.$total.' objects found)');
                $io->progressStart($total);
                $io->text('');

                $errorCount = $this->handleAllObjects($io, $objectEntities);
                break;
            default:
                return Command::INVALID;
        }
        $io->progressFinish();

        $errors = round($errorCount / $total * 100) == 0 && $errorCount > 0 ? 1 : round($errorCount / $total * 100);
        $typeString = $type !== 'Object' && $type !== 'AllObjects' ? 'for Type'.$type : '';
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

    private function handleTypeObject(SymfonyStyle $io, array $ids): int
    {
        $errorCount = 0;
        foreach ($ids as $id) {
            $io->text('');
            $io->section("Removing Object from cache with id: {$id}");
            if (!Uuid::isValid($id)) {
                $io->error($id.' is not a valid uuid!');
                $errorCount++;
                $io->progressAdvance();
                continue;
            }
            $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($id);
            if ($objectEntity instanceof ObjectEntity) {
                $this->removeObjectFromCache($io, $objectEntity, $id);
            } else {
                $io->error('Could not find an ObjectEntity with this id: '.$id);
                $errorCount++;
            }
            $io->progressAdvance();
        }

        return $errorCount;
    }

    private function removeObjectFromCache(SymfonyStyle $io, ObjectEntity $objectEntity, string $id)
    {
        $this->functionService->removeResultFromCache($objectEntity);
        $io->text("Successfully removed Object with id: {$id} from cache");
    }

    private function handleTypeEntity(SymfonyStyle $io, array $ids): int
    {
        $errorCount = 0;
        foreach ($ids as $id) {
            $io->text('');
            $io->section("Removing Objects from cache for Entity with id: {$id}");
            if (!Uuid::isValid($id)) {
                $io->error($id.' is not a valid uuid!');
                $errorCount++;
                $io->progressAdvance();
                continue;
            }
            $entity = $this->entityManager->getRepository('App:Entity')->find($id);
            if ($entity instanceof Entity) {
                $this->removeEntityObjectsFromCache($io, $entity, $id);
            } else {
                $io->error('Could not find an Entity with this id: '.$id);
                $errorCount++;
            }
            $io->progressAdvance();
        }

        return $errorCount;
    }

    private function removeEntityObjectsFromCache(SymfonyStyle $io, Entity $entity, string $id)
    {
        foreach ($entity->getObjectEntities() as $objectEntity) {
            $this->removeObjectFromCache($io, $objectEntity, $objectEntity->getId()->toString());
        }
        $io->text("Successfully removed all Objects from cache for the Entity with id: {$id}");
    }

    private function handleTypeCollection(SymfonyStyle $io, array $ids): int
    {
        $errorCount = 0;

        foreach ($ids as $id) {
            $io->text('');
            $io->section("Removing Objects from cache for Collection with id: {$id}");
            if (!Uuid::isValid($id)) {
                $io->error($id.' is not a valid uuid!');
                $errorCount++;
                $io->progressAdvance();
                continue;
            }
            $collection = $this->entityManager->getRepository('App:CollectionEntity')->find($id);
            if ($collection instanceof CollectionEntity) {
                $this->removeCollectionObjectsFromCache($io, $collection, $id);
            } else {
                $io->error('Could not find a Collection with this id: '.$id);
                $errorCount++;
            }
            $io->progressAdvance();
        }

        return $errorCount;
    }

    private function removeCollectionObjectsFromCache(SymfonyStyle $io, CollectionEntity $collection, string $id)
    {
        foreach ($collection->getEntities() as $entity) {
            $this->removeEntityObjectsFromCache($io, $entity, $entity->getId()->toString());
        }
        $io->text("Successfully removed all Objects from cache for the Collection with id: {$id}");
    }

    private function handleAllObjects(SymfonyStyle $io, array $objectEntities): int
    {
        foreach ($objectEntities as $objectEntity) {
            $id = $objectEntity->getId()->toString();
            $io->text('');
            $io->section("Removing Object from cache with id: {$id}");
            $this->removeObjectFromCache($io, $objectEntity, $id);
            $io->progressAdvance();
        }

        return 0;
    }
}
