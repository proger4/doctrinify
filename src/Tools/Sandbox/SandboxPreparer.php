<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Sandbox;

final class SandboxPreparer
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function syncModels(string $sourceRelativePath, string $targetRelativePath, bool $cleanTarget): int
    {
        $source = $this->resolvePath($sourceRelativePath);
        $target = $this->resolvePath($targetRelativePath);

        if (!is_dir($source)) {
            throw new \RuntimeException(sprintf('Sandbox source path not found: %s', $source));
        }

        $this->ensureDir($target);
        if ($cleanTarget) {
            $this->cleanPhpFilesOnly($target);
        }

        $copied = 0;
        $items = scandir($source);
        if ($items === false) {
            throw new \RuntimeException(sprintf('Failed to read sandbox source path: %s', $source));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourceFile = $source . '/' . $item;
            if (!is_file($sourceFile) || strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }

            $targetFile = $target . '/' . $item;
            $content = file_get_contents($sourceFile);
            if ($content === false) {
                throw new \RuntimeException(sprintf('Failed to read model fixture: %s', $sourceFile));
            }

            if (file_put_contents($targetFile, $content) === false) {
                throw new \RuntimeException(sprintf('Failed to write sandbox model: %s', $targetFile));
            }

            $copied++;
        }

        return $copied;
    }

    private function cleanPhpFilesOnly(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (!is_file($fullPath) || strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }

            unlink($fullPath);
        }
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Failed to create sandbox path: %s', $path));
        }
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $this->projectRoot;
        }

        if ($path[0] === '/') {
            return $path;
        }

        return rtrim($this->projectRoot, '/') . '/' . ltrim($path, './');
    }
}
