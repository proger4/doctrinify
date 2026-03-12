<?php

declare(strict_types=1);

namespace Doctrinify\Command;

use Doctrinify\Tools\Config\ConfigLoader;
use Doctrinify\Tools\Tasks\Execution\TaskRunService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tools:ai:task-execute', description: 'Execute AI diagnostic tasks from xlsx task sheet')]
final class AiTaskExecuteCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to YAML config', 'config.yaml')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to xlsx task file')
            ->addOption('task-set', null, InputOption::VALUE_REQUIRED, 'Task set name')
            ->addOption('task-id', null, InputOption::VALUE_REQUIRED, 'Single TASK_ID filter')
            ->addOption('cat', null, InputOption::VALUE_REQUIRED, 'Category filter override');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $projectRoot = getcwd() ?: '.';
            $configPath = (string) $input->getOption('config');
            $taskSet = strtolower((string) $input->getOption('task-set'));
            $taskId = $this->nullable($input->getOption('task-id'));
            $cat = $this->nullable($input->getOption('cat'));

            $aiConfig = $this->loadAiConfig($projectRoot, $configPath);
            $xlsxFile = (string) ($input->getOption('file') ?: ($aiConfig['xlsx_task_file'] ?? 'src/Tools/Tasks/test.xlsx'));
            $storageRoot = (string) ($aiConfig['task_storage_root'] ?? 'var/tasks');

            $runOptions = [
                'task_storage_root' => $storageRoot,
                'weak_model_mode' => (bool) ($aiConfig['weak_model_mode'] ?? true),
                'max_input_length' => (int) ($aiConfig['max_input_length'] ?? 12000),
            ];

            $service = new TaskRunService($projectRoot);
            $result = $service->run($xlsxFile, $taskSet, $taskId, $cat, $runOptions);

            $output->writeln(sprintf('Task set: %s', $result['task_set']));
            $output->writeln(sprintf('Executed tasks: %d', $result['executed']));
            $output->writeln(sprintf('Skipped done tasks: %d', $result['skipped_done']));
            if ($result['task_set'] === 'mixed') {
                $output->writeln(sprintf('Artifacts root: %s/<task_category>', $result['storage_root']));
            } else {
                $output->writeln(sprintf('Artifacts root: %s/%s', $result['storage_root'], $result['task_set']));
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>AI task execution failed: %s</error>', $e->getMessage()));
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

    private function nullable(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
