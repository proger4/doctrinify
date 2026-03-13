<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OrmGeneratorService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('tools:orm:generate');
        $this->setDescription('Generate Doctrine XML and PHP accessors from Yii-style models');
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
            $result = $service->generate($config);

            $output->writeln(sprintf('Generated XML files: %d', count($result['xml_files'])));
            $output->writeln(sprintf('Generated PHP files: %d', count($result['php_files'])));
            $output->writeln(sprintf('Mismatch report: %s', $result['mismatch_report']));
            foreach ($result['warnings'] as $warning) {
                $output->writeln(sprintf('<comment>Warning: %s</comment>', (string) $warning));
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Generation failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
