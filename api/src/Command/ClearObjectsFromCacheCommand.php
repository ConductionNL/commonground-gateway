<?php

// src/Command/ConfigureClustersCommand.php

namespace App\Command;

use App\Entity\CollectionEntity;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Service\FunctionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
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
    private FunctionService $functionService;
    private EntityManagerInterface $entityManager;

    public function __construct(FunctionService $functionService, EntityManagerInterface $entityManager, string $name = null)
    {
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

    /**
     * Use QuestionHelper helper to ask the user of this command what type of entity he/she wants to clear objects for from the cache.
     *
     * @param QuestionHelper $helper The QuestionHelper
     *
     * @return string The chosen type
     */
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

    /**
     * If user of this command chose for a type and not AllObjects, ask for id's of objects of the chosen (entity) type.
     * Will also count the amount of id's given and use this to start the SymfonyStyle $io progress.
     *
     * @param SymfonyStyle   $io     The SymfonyStyle $io
     * @param QuestionHelper $helper The QuestionHelper
     * @param string         $type   The chosen entity type to remove objects for from the cache.
     *
     * @return array An array of uuid's the user of this command has given.
     */
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

    /**
     * If user of this command chose AllObjects as type, ask one last time if he/she really wants to remove all objects from cache.
     * If answered with yes/true, then get all ObjectEntities from db, count the amount of objects and use this to start the SymfonyStyle $io progress.
     * If answered with no, return null.
     *
     * @param SymfonyStyle   $io     The SymfonyStyle $io
     * @param QuestionHelper $helper The QuestionHelper
     *
     * @return array|null An array of all objectEntities or null.
     */
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

    /**
     * Handles removing ObjectEntity responses from cache for all $entityName $ids.
     * Will loop through the list of $ids, all entities of the chosen/given $entityName type and remove all ObjectEntities connected from cache.
     *
     * @param SymfonyStyle $io            The SymfonyStyle $io
     * @param array        $ids           An array of uuid's the user of this command has given.
     * @param string       $entityName    The chosen entity type to remove objects for from the cache, but with the correct entity name.
     * @param string       $ioSectionText A text used in the $io->section for each $id (per foreach loop of all $ids)
     *
     * @throws InvalidArgumentException
     *
     * @return int The amount of errors encountered when trying to remove objects from cache. (per $entityName type)
     */
    private function handleSwitchType(SymfonyStyle $io, array $ids, string $entityName, string $ioSectionText): int
    {
        $errorCount = 0;
        foreach ($ids as $id) {
            $io->newLine();
            $io->section("Removing {$ioSectionText} with id: {$id}");
            if (!Uuid::isValid($id)) {
                $io->error($id.' is not a valid uuid!');
                $errorCount++;
                $io->progressAdvance();
                continue;
            }
            $object = $this->entityManager->getRepository('App:'.$entityName)->find($id);
            if ($object instanceof ObjectEntity) {
                $this->removeObjectFromCache($io, $object);
            } elseif ($object instanceof Entity) {
                $this->removeEntityObjectsFromCache($io, $object);
            } elseif ($object instanceof CollectionEntity) {
                $this->removeCollectionObjectsFromCache($io, $object);
            } else {
                $io->error("Could not find an {$entityName} with this id: {$id}");
                $errorCount++;
            }
            $io->progressAdvance();
        }

        return $errorCount;
    }

    /**
     * Will remove all saved responses for the given $objectEntity from cache. (incl parent objects).
     *
     * @param SymfonyStyle $io           The SymfonyStyle $io
     * @param ObjectEntity $objectEntity The ObjectEntity to remove all saved responses for from cache. (incl parent objects)
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    private function removeObjectFromCache(SymfonyStyle $io, ObjectEntity $objectEntity)
    {
        // todo: add try catch to catch errors?

        $this->functionService->removeResultFromCache($objectEntity, $io);
        $io->text("Successfully removed Object with id: {$objectEntity->getId()->toString()} (of Entity type: {$objectEntity->getEntity()->getName()}) from cache");
    }

    /**
     * Will remove all saved responses for all objectEntities connected to the given $entity from cache. (incl parent objects).
     *
     * @param SymfonyStyle $io     The SymfonyStyle $io
     * @param Entity       $entity The Entity to remove saved responses of all connected objectEntities for from cache. (incl parent objects)
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    private function removeEntityObjectsFromCache(SymfonyStyle $io, Entity $entity)
    {
        foreach ($entity->getObjectEntities() as $objectEntity) {
            $this->removeObjectFromCache($io, $objectEntity);
        }
        $io->text("Successfully removed all Objects from cache for the Entity with id: {$entity->getId()->toString()} (and name: {$entity->getName()})");
    }

    /**
     * Will remove all saved responses for all objectEntities connected to the given $collection from cache. (incl parent objects).
     *
     * @param SymfonyStyle     $io         The SymfonyStyle $io
     * @param CollectionEntity $collection The Collection to remove saved responses of all connected objectEntities for from cache. (incl parent objects)
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    private function removeCollectionObjectsFromCache(SymfonyStyle $io, CollectionEntity $collection)
    {
        foreach ($collection->getEntities() as $entity) {
            $this->removeEntityObjectsFromCache($io, $entity);
        }
        $io->text("Successfully removed all Objects from cache for the Collection with id: {$collection->getId()->toString()} (and name: {$collection->getName()})");
    }

    /**
     * Will remove all saved responses for all $objectEntities from cache.
     *
     * @param SymfonyStyle $io             The SymfonyStyle $io
     * @param array        $objectEntities An array of all objectEntities.
     *
     * @throws InvalidArgumentException
     *
     * @return int The amount of errors encountered when trying to remove objects from cache. todo For now, default 0
     */
    private function removeAllObjectsFromCache(SymfonyStyle $io, array $objectEntities): int
    {
        $io->newLine();
        foreach ($objectEntities as $objectEntity) {
            $io->text('');
            $io->section("Removing Object from cache with id: {$objectEntity->getId()->toString()}");
            $this->removeObjectFromCache($io, $objectEntity);
            $io->progressAdvance();
        }

        return 0;
    }

    /**
     * Determine the response of executing this command. Response depends on te amount of errors in percentage.
     * If more than 20% failed will return Failure = 1. Else returns Succes = 0.
     * Will also send a final message with SymfonyStyle $io as user feedback, depending on the failure rate this will be a Success, Warning or Error message.
     *
     * @param SymfonyStyle $io         The SymfonyStyle $io
     * @param string       $type       The chosen entity type to remove objects for from the cache.
     * @param int          $errorCount The amount of errors encountered when trying to remove objects from cache (per entity $type).
     * @param int          $total      The total amount of entity $type objects we are removing saved objectEntities responses for from cache.
     *
     * @return int 0 or 1 depending on succes rate of removing saved objectEntities responses for from cache. Success = 0, Failure = 1
     */
    private function handleExecuteResponse(SymfonyStyle $io, string $type, int $errorCount, int $total): int
    {
        $errors = $total == 0 ? 0 : (round($errorCount / $total * 100) == 0 && $errorCount > 0 ? 1 : round($errorCount / $total * 100));
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
