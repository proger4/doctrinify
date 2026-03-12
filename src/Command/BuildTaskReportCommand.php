<?php

declare(strict_types=1);

namespace Doctrinify\Command;

use Doctrinify\Tools\Config\ConfigLoader;
use Doctrinify\Tools\Tasks\Reporting\TaskReportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tools:report:build', description: 'Build local HTML report from task artifacts')]
final class BuildTaskReportCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to YAML config', 'config.yaml')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Task set name', 'models_ai_analysis')
            ->addOption('open', null, InputOption::VALUE_NONE, 'Open report in browser')
            ->addOption('no-open', null, InputOption::VALUE_NONE, 'Do not open report in browser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $projectRoot = getcwd() ?: '.';
            $configPath = (string) $input->getOption('config');
            $name = strtolower((string) $input->getOption('name'));
            $aiConfig = $this->loadAiConfig($projectRoot, $configPath);

            $storageRoot = (string) ($aiConfig['task_storage_root'] ?? 'var/tasks');
            $defaultOpen = (bool) ($aiConfig['default_report_open'] ?? true);
            $shouldOpen = $input->getOption('no-open') ? false : ((bool) $input->getOption('open') || $defaultOpen);

            $service = new TaskReportService($projectRoot);
            $result = $service->build($name, $storageRoot, $shouldOpen);

            $output->writeln(sprintf('Report built for: %s', $name));
            $output->writeln(sprintf('Results included: %d', $result['count']));
            $output->writeln(sprintf('Report path: %s', $result['report_path']));
            if ($shouldOpen && !$result['opened']) {
                $output->writeln('Browser open failed, use report path above.');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Report build failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadAiConfig(string $projectRoot, string $configPath): array
    {
        $loader = new ConfigLoader();
        $config = $loader->load($projectRoot, $configPath);

        if (!is_array($config['tools'] ?? null)) {
            return [];
        }

        $tools = $config['tools'];
        if (!is_array($tools['ai'] ?? null)) {
            return [];
        }

        /** @var array<string, mixed> $ai */
        $ai = $tools['ai'];
        return $ai;
    }
}
