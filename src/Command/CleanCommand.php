<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OrmGeneratorService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CleanCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('tools:orm:clean');
        $this->setDescription('Clean generated Doctrine XML and PHP accessors');
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to YAML config', 'config.yaml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $configOption = $input->getOption('config');
            if (!is_string($configOption) || $configOption === '') {
                throw new \RuntimeException('Option "config" must be a non-empty string');
            }

            $projectRoot = getcwd() ?: '.';
            $service = new OrmGeneratorService($projectRoot);
            $config = $configOption;
            $service->clean($config);

            $output->writeln('Generated artifacts were cleaned.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Clean failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
