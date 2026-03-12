<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Doctrinify\Command\CleanCommand;
use Doctrinify\Command\GenerateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandPipelineTest extends Unit
{
    private string $projectRoot;
    private string $configPath;
    private string $xmlOutputDir;
    private string $phpOutputDir;

    protected function _before(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->configPath = $this->projectRoot . '/tests/_data/mock/config.yaml';
        $this->xmlOutputDir = $this->projectRoot . '/tests/_output/generated/doctrine';
        $this->phpOutputDir = $this->projectRoot . '/tests/_output/generated/classes';

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
        $phpFiles = glob($this->phpOutputDir . '/*.php') ?: [];
        $this->assertCount(9, $xmlFiles);
        $this->assertCount(9, $phpFiles);
        $reportPath = $this->xmlOutputDir . '/mismatch-report.txt';
        $this->assertFileExists($reportPath);
        $report = (string) file_get_contents($reportPath);
        $this->assertStringContainsString('regeneration strategy: overwrite_all', $report);
        $this->assertStringContainsString('relation `categoriesWithSql` rejected', $report);
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
        $phpFiles = glob($this->phpOutputDir . '/*') ?: [];
        $this->assertCount(0, $xmlFiles);
        $this->assertCount(0, $phpFiles);
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
}
