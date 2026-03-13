<?php

declare(strict_types=1);

namespace App\Command;

use App\Tools\Sandbox\SandboxPreparer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PrepareSandboxCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('tools:sandbox:prepare')
            ->setDescription('Sync sandbox/models from fixture source models')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Fixture source directory', 'tests/_data/mock/models')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Sandbox runtime directory', 'sandbox/models')
            ->addOption('clean', null, InputOption::VALUE_NONE, 'Remove existing PHP files in target before sync');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $projectRoot = getcwd() ?: '.';
            $sourceOption = $input->getOption('source');
            $targetOption = $input->getOption('target');
            if (!is_string($sourceOption) || $sourceOption === '') {
                throw new \RuntimeException('Option "source" must be a non-empty string');
            }
            if (!is_string($targetOption) || $targetOption === '') {
                throw new \RuntimeException('Option "target" must be a non-empty string');
            }
            $source = $sourceOption;
            $target = $targetOption;
            $clean = (bool) $input->getOption('clean');

            $preparer = new SandboxPreparer($projectRoot);
            $copied = $preparer->syncModels($source, $target, $clean);

            $output->writeln(sprintf('Sandbox models synced: %d', $copied));
            $output->writeln(sprintf('Source: %s', $source));
            $output->writeln(sprintf('Target: %s', $target));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Sandbox prepare failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
