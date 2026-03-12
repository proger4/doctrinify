<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Execution;

use Doctrinify\Tools\Tasks\Contracts\TaskInputDto;

final class TaskInputBuilder
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @param list<string> $inputNames
     * @param array<string, TaskInputDto> $workbookInputs
     * @return array<string, string>
     */
    public function build(array $inputNames, array $workbookInputs, int $maxInputLength): array
    {
        $inputs = [];
        foreach ($inputNames as $inputName) {
            $dto = $workbookInputs[$inputName] ?? null;
            if ($dto === null) {
                continue;
            }

            $content = $this->resolveContent($dto->sourceRef);
            $content = trim($content);

            if ($maxInputLength > 0 && strlen($content) > $maxInputLength) {
                $content = substr($content, 0, $maxInputLength) . "\n...[truncated]";
            }

            if ($dto->policy !== '') {
                $content = "[POLICY] {$dto->policy}\n\n" . $content;
            }

            $inputs[$dto->name] = $content;
        }

        return $inputs;
    }

    private function resolveContent(string $sourceRef): string
    {
        $sourceRef = trim($sourceRef);
        if ($sourceRef === '') {
            return '';
        }

        if (str_starts_with($sourceRef, 'sandbox:')) {
            return $this->resolveSandboxRef($sourceRef);
        }

        $path = $this->resolvePath($sourceRef);
        if (is_file($path)) {
            return (string) file_get_contents($path);
        }

        return $sourceRef;
    }

    private function resolveSandboxRef(string $ref): string
    {
        if ($ref === 'sandbox:schema') {
            return $this->readRequiredFile('tests/_data/mock/database/schema.sql');
        }

        if ($ref === 'sandbox:mismatch-report') {
            return $this->readRequiredGeneratedFile('generated/doctrine/mismatch-report.txt');
        }

        if (preg_match('/^sandbox:model:(.+)$/', $ref, $m) === 1) {
            $name = trim($m[1]);
            if (!str_ends_with($name, '.php')) {
                $name .= '.php';
            }
            return $this->readRequiredFile('tests/_data/mock/models/' . $name);
        }

        if (preg_match('/^sandbox:generated:xml:(.+)$/', $ref, $m) === 1) {
            $name = trim($m[1]);
            if (!str_ends_with($name, '.orm.xml')) {
                $name .= '.orm.xml';
            }
            return $this->readRequiredGeneratedFile('generated/doctrine/' . $name);
        }

        if (preg_match('/^sandbox:generated:php:(.+)$/', $ref, $m) === 1) {
            $name = trim($m[1]);
            if (!str_ends_with($name, '.php')) {
                $name .= '.php';
            }
            return $this->readRequiredGeneratedFile('generated/classes/' . $name);
        }

        if (preg_match('/^sandbox:file:(.+)$/', $ref, $m) === 1) {
            return $this->readRequiredFile(trim($m[1]));
        }

        throw new \RuntimeException(sprintf('Unsupported sandbox source ref: %s', $ref));
    }

    private function readRequiredFile(string $relativePath): string
    {
        $path = $this->resolvePath($relativePath);
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Sandbox input file not found: %s', $path));
        }

        return (string) file_get_contents($path);
    }

    private function readRequiredGeneratedFile(string $relativePath): string
    {
        $path = $this->resolvePath($relativePath);
        if (!is_file($path)) {
            throw new \RuntimeException(
                sprintf(
                    'Generated artifact not found: %s. Run tools:orm:generate before tools:ai:task-execute.',
                    $path
                )
            );
        }

        return (string) file_get_contents($path);
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
