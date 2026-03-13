<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use App\Command\CleanCommand;
use App\Command\GenerateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandPipelineTest extends Unit
{
    private string $projectRoot;
    private string $configPath;
    private string $modelsSourceDir;
    private string $modelsRuntimeDir;
    private string $xmlOutputDir;

    protected function _before(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->configPath = $this->projectRoot . '/tests/_data/mock/config.yaml';
        $this->modelsSourceDir = $this->projectRoot . '/tests/_data/mock/models';
        $this->modelsRuntimeDir = $this->projectRoot . '/tests/_output/sandbox/models';
        $this->xmlOutputDir = $this->projectRoot . '/tests/_output/generated/doctrine';
        $this->resetDirectory($this->modelsRuntimeDir);
        $this->copyDir($this->modelsSourceDir, $this->modelsRuntimeDir);

        $this->runClean();
    }

    public function testGenerateCommandBuildsArtifactsAndMismatchReport(): void
    {
        $command = new GenerateCommand();
        $tester = new CommandTester($command);
        $code = $tester->execute(['--config' => $this->configPath]);
        $output = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Generated XML files:', $output);
        $this->assertStringContainsString('Generated PHP files:', $output);
        $this->assertStringContainsString('Mismatch report:', $output);

        $xmlFiles = glob($this->xmlOutputDir . '/*.orm.xml') ?: [];
        $phpFiles = glob($this->modelsRuntimeDir . '/*.php') ?: [];
        $this->assertCount(9, $xmlFiles);
        $this->assertCount(10, $phpFiles);
        $reportPath = $this->xmlOutputDir . '/mismatch-report.txt';
        $this->assertFileExists($reportPath);
        $report = (string) file_get_contents($reportPath);
        $this->assertStringContainsString('relation `categoriesWithSql` has SQL modifiers', $report);
    }

    public function testCleanCommandRemovesOnlyGeneratedOutput(): void
    {
        $this->runGenerate();
        $sentinel = $this->projectRoot . '/tests/_output/sentinel.txt';
        file_put_contents($sentinel, 'keep');

        $command = new CleanCommand();
        $tester = new CommandTester($command);
        $code = $tester->execute(['--config' => $this->configPath]);
        $output = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Generated artifacts were cleaned.', $output);

        $xmlFiles = glob($this->xmlOutputDir . '/*') ?: [];
        $phpFiles = glob($this->modelsRuntimeDir . '/*.php') ?: [];
        $this->assertCount(0, $xmlFiles);
        $this->assertCount(10, $phpFiles);
        $this->assertFileExists($sentinel);

        unlink($sentinel);
    }

    private function runGenerate(): void
    {
        $command = new GenerateCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--config' => $this->configPath]);
    }

    private function runClean(): void
    {
        $command = new CleanCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--config' => $this->configPath]);
    }

    private function resetDirectory(string $path): void
    {
        if (is_dir($path)) {
            $items = scandir($path);
            if ($items !== false) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $full = $path . '/' . $item;
                    if (is_file($full)) {
                        unlink($full);
                    }
                }
            }
        } else {
            mkdir($path, 0777, true);
        }
    }

    private function copyDir(string $from, string $to): void
    {
        if (!is_dir($to) && !mkdir($to, 0777, true) && !is_dir($to)) {
            throw new \RuntimeException('Cannot create runtime models dir: ' . $to);
        }

        $items = scandir($from);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $src = $from . '/' . $item;
            $dst = $to . '/' . $item;
            if (is_file($src)) {
                copy($src, $dst);
            }
        }
    }
}
