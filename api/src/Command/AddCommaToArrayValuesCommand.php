<?php

// src/Command/ConfigureClustersCommand.php

namespace App\Command;

use App\Entity\Value;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AddCommaToArrayValuesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager, string $name = null)
    {
        $this->entityManager = $entityManager;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('gateway:values:addCommaToArray')
            // the short description shown while running "php bin/console list"
            ->setDescription('Add starting comma to stringValues of multiple=true values')
            ->setHelp('This command wil get all values with an attribute with multiple = true. And update all the stringValues of these values so they start with a comma');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Value update tool');
        $io->text('Getting all values with an attribute with multiple = true and no starting comma.');

        $values = $this->entityManager->getRepository('App:Value')->findMultipleValues();
        if (is_countable($values)) {
            $total = count($values);
            $io->text("Found {$total} values");
            $io->progressStart($total);
            $io->newLine();
            $io->newLine();
        } else {
            $io->warning('Values is not countable');
            $io->error('A lot of values could not be updated. Failure rate is 100%');

            return Command::FAILURE;
        }

        $errorCount = $this->loop($io, $values);

        $this->entityManager->flush();
        $io->progressFinish();

        $errors = $total == 0 ? 0 : (round($errorCount / $total * 100) == 0 && $errorCount > 0 ? 1 : round($errorCount / $total * 100));

        return $this->handleCommandResult($io, $errors);
    }

    /**
     * Loop through values and update stringValue so it starts with a comma.
     *
     * @param SymfonyStyle $io
     * @param array        $values
     *
     * @return int The errorCount
     */
    private function loop(SymfonyStyle $io, array $values): int
    {
        $errorCount = 0;
        foreach ($values as $value) {
            try {
                $io->text("Updating value with id: {$value->getId()->toString()}");
                $value->setStringValue(','.$value->getStringValue());
            } catch (Exception $exception) {
                $io->warning("Catched Exception for Value with id {$value->getId()->toString()}: {$exception}");
                $errorCount++;
            }
            $io->progressAdvance();
        }

        return $errorCount;
    }

    /**
     * Handles result for this command depending on the amount of $errors.
     *
     * @param SymfonyStyle $io
     * @param int          $errors
     *
     * @return int Return value for this command, 0 or 1. Command::SUCCESS or Command::FAILURE
     */
    private function handleCommandResult(SymfonyStyle $io, int $errors): int
    {
        if ($errors == 0) {
            $io->success('All values have been updated');
        } elseif ($errors < 20) {
            $io->warning("Some values could not be updated. Failure rate is $errors%");
        } else {
            $io->error("A lot of values could not be updated. Failure rate is $errors%");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
