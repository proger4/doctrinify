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
        $batchesByClass = [];
        $nonBaseBySignature = [];
        foreach ($batches as $batch) {
            $batchesByClass[$batch->className] = $batch;
            $resolvedTable = $batch->analyzedModel->resolvedTable;
            if (!is_string($resolvedTable) || $resolvedTable === '') {
                continue;
            }
            if ($this->isBaseClass($batch->className, $classFiles[$batch->className] ?? null)) {
                continue;
            }

            $signature = $this->classSignature($resolvedTable, $batch->className);
            $nonBaseBySignature[$signature] = true;
        }

        $selectedBySignature = [];

        foreach ($batches as $batch) {
            if ($batch->analyzedModel->model->isAbstract || $batch->table === null || $batch->analyzedModel->resolvedTable === null) {
                continue;
            }
            $signature = $this->classSignature($batch->analyzedModel->resolvedTable, $batch->className);
            $isBaseBatch = $this->isBaseClass($batch->className, $classFiles[$batch->className] ?? null);
            if ($isBaseBatch && isset($nonBaseBySignature[$signature])) {
                continue;
            }
            if ($this->shouldSkipBaseBatch($batch, $batchesByClass, $classFiles)) {
                continue;
            }

            if (!isset($selectedBySignature[$signature])) {
                $selectedBySignature[$signature] = $batch;
                continue;
            }

            $current = $selectedBySignature[$signature];
            if ($this->prefer($batch, $current, $classFiles)) {
                $selectedBySignature[$signature] = $batch;
            }
        }

        $selected = array_values($selectedBySignature);
        usort(
            $selected,
            static fn (AnalysisBatchSchema $a, AnalysisBatchSchema $b): int => strcmp($a->className, $b->className)
        );

        return $selected;
    }

    /**
     * @param array<string, AnalysisBatchSchema> $batchesByClass
     * @param array<string, string> $classFiles
     */
    private function shouldSkipBaseBatch(AnalysisBatchSchema $batch, array $batchesByClass, array $classFiles): bool
    {
        if (!$this->isBaseClass($batch->className, $classFiles[$batch->className] ?? null)) {
            return false;
        }

        foreach ($batch->hierarchyClasses as $candidateClass) {
            if ($candidateClass === $batch->className || !isset($batchesByClass[$candidateClass])) {
                continue;
            }

            $candidate = $batchesByClass[$candidateClass];
            if ($candidate->analyzedModel->model->isAbstract || $candidate->table === null || $candidate->analyzedModel->resolvedTable === null) {
                continue;
            }

            if ($this->isBaseClass($candidateClass, $classFiles[$candidateClass] ?? null)) {
                continue;
            }

            if (strtolower($candidate->analyzedModel->resolvedTable) === strtolower($batch->analyzedModel->resolvedTable)) {
                return true;
            }
        }

        return false;
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
        if (stripos($className, '\\_base\\') !== false) {
            return true;
        }

        if (is_string($filePath) && $filePath !== '') {
            $normalized = str_replace('\\', '/', strtolower($filePath));
            return preg_match('~(?:^|/)_base(?:/|$)~', $normalized) === 1;
        }

        return false;
    }

    private function classSignature(string $resolvedTable, string $className): string
    {
        return strtolower($resolvedTable . '|' . $this->shortClassName($className));
    }

    private function shortClassName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return end($parts) ?: $fqn;
    }
}
