<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OrmGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tools:orm:clean', description: 'Clean generated Doctrine XML and PHP accessors')]
final class CleanCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to YAML config', 'config.yaml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = (string) $input->getOption('config');
            $service = new OrmGeneratorService(getcwd());
            $service->clean($config);

            $output->writeln('Generated artifacts were cleaned.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Clean failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
