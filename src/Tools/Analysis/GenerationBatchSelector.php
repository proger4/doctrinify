<?php

declare(strict_types=1);

namespace App\Tools\Analysis;

use App\Tools\Schemas\Pipeline\AnalysisBatchSchema;

final class GenerationBatchSelector
{
    /**
     * @param array<int, AnalysisBatchSchema> $batches
     * @param array<string, string> $classFiles
     * @return array<int, AnalysisBatchSchema>
     */
    public function select(array $batches, array $classFiles): array
    {
        $selectedByShortName = [];

        foreach ($batches as $batch) {
            if ($batch->analyzedModel->model->isAbstract || $batch->table === null || $batch->analyzedModel->resolvedTable === null) {
                continue;
            }

            $shortKey = strtolower($this->shortClassName($batch->className));
            if (!isset($selectedByShortName[$shortKey])) {
                $selectedByShortName[$shortKey] = $batch;
                continue;
            }

            $current = $selectedByShortName[$shortKey];
            if ($this->prefer($batch, $current, $classFiles)) {
                $selectedByShortName[$shortKey] = $batch;
            }
        }

        $selected = array_values($selectedByShortName);
        usort(
            $selected,
            static fn (AnalysisBatchSchema $a, AnalysisBatchSchema $b): int => strcmp($a->className, $b->className)
        );

        return $selected;
    }

    /**
     * @param array<string, string> $classFiles
     */
    private function prefer(AnalysisBatchSchema $candidate, AnalysisBatchSchema $current, array $classFiles): bool
    {
        $candidateIsBase = $this->isBaseClass($candidate->className, $classFiles[$candidate->className] ?? null);
        $currentIsBase = $this->isBaseClass($current->className, $classFiles[$current->className] ?? null);

        if ($candidateIsBase !== $currentIsBase) {
            return !$candidateIsBase;
        }

        return strlen($candidate->className) < strlen($current->className);
    }

    private function isBaseClass(string $className, ?string $filePath): bool
    {
        if (stripos($className, '\\_base\\') !== false || stripos($className, '\\base\\') !== false) {
            return true;
        }

        if (is_string($filePath) && $filePath !== '') {
            $normalized = str_replace('\\', '/', strtolower($filePath));
            if (str_contains($normalized, '/_base/') || str_contains($normalized, '/base/')) {
                return true;
            }
        }

        return false;
    }

    private function shortClassName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return end($parts) ?: $fqn;
    }
}

