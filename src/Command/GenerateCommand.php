<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OrmGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tools:orm:generate', description: 'Generate Doctrine XML and PHP accessors from Yii-style models')]
final class GenerateCommand extends Command
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
            $result = $service->generate($config);

            $output->writeln(sprintf('Generated XML files: %d', count($result['xml_files'])));
            $output->writeln(sprintf('Generated PHP files: %d', count($result['php_files'])));
            $output->writeln(sprintf('Mismatch report: %s', $result['mismatch_report']));
            foreach ($result['warnings'] ?? [] as $warning) {
                $output->writeln(sprintf('<comment>Warning: %s</comment>', (string) $warning));
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Generation failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
