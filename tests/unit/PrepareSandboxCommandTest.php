<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Doctrinify\Command\PrepareSandboxCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PrepareSandboxCommandTest extends Unit
{
    private string $projectRoot;
    private string $sourceDir;
    private string $targetDir;

    protected function _before(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->sourceDir = $this->projectRoot . '/tests/_output/sandbox_prepare/source';
        $this->targetDir = $this->projectRoot . '/tests/_output/sandbox_prepare/target';

        $this->resetDirectory($this->sourceDir);
        $this->resetDirectory($this->targetDir);

        file_put_contents($this->sourceDir . '/User.php', "<?php\nclass User {}\n");
        file_put_contents($this->sourceDir . '/Order.php', "<?php\nclass Order {}\n");
        file_put_contents($this->sourceDir . '/README.txt', 'not a model');
        file_put_contents($this->targetDir . '/Old.php', "<?php\nclass Old {}\n");
    }

    public function testPrepareSandboxCommandCopiesPhpModelsOnly(): void
    {
        $tester = new CommandTester(new PrepareSandboxCommand());
        $code = $tester->execute([
            '--source' => $this->sourceDir,
            '--target' => $this->targetDir,
            '--clean' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $code);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Sandbox models synced: 2', $output);
        $this->assertFileExists($this->targetDir . '/User.php');
        $this->assertFileExists($this->targetDir . '/Order.php');
        $this->assertFileDoesNotExist($this->targetDir . '/README.txt');
        $this->assertFileDoesNotExist($this->targetDir . '/Old.php');
    }

    private function resetDirectory(string $path): void
    {
        if (is_dir($path)) {
            $this->deleteRecursive($path);
        }

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException('Cannot create directory: ' . $path);
        }
    }

    private function deleteRecursive(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            if (is_dir($full)) {
                $this->deleteRecursive($full);
                rmdir($full);
                continue;
            }

            unlink($full);
        }
    }
}
