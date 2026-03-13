<?php

declare(strict_types=1);

namespace App\Tools\Persist;

use App\Tools\AST\AstClassMutationPlan;
use App\Tools\AST\AstFacade;
use App\Tools\Schemas\Pipeline\GenerationResultSchema;
use App\Tools\Schemas\Pipeline\PhpPersistInstructionSchema;
use App\Tools\Schemas\Pipeline\PersistResultSchema;

final class ArtifactPersister
{
    public function __construct(private readonly ?AstFacade $astFacade = null)
    {
    }

    public function persist(GenerationResultSchema $result, string $xmlOutputPath): PersistResultSchema
    {
        $this->ensureDirectory($xmlOutputPath);

        $xmlFiles = [];
        $phpFiles = [];
        foreach ($result->artifacts as $artifact) {
            if ($artifact->xml !== null && $artifact->xmlFilename !== null) {
                $xmlFile = $xmlOutputPath . '/' . $artifact->xmlFilename;
                file_put_contents($xmlFile, $artifact->xml);
                $xmlFiles[] = $xmlFile;
            }

            if ($artifact->phpInstruction !== null) {
                $phpFiles[] = $this->persistPhpInstruction($artifact->phpInstruction);
                continue;
            }
        }

        $reportPath = $xmlOutputPath . '/mismatch-report.txt';
        file_put_contents($reportPath, $result->report);

        return new PersistResultSchema($xmlFiles, $phpFiles, $reportPath);
    }

    public function clean(string $xmlOutputPath, string $phpOutputPath): void
    {
        $this->removeDirectoryContents($xmlOutputPath);
        if ($phpOutputPath !== '') {
            $this->removeDirectoryContents($phpOutputPath);
        }
    }

    /**
     * @param array<string, string> $classFiles
     */
    public function cleanGeneratedAstMembers(array $classFiles): void
    {
        $ast = $this->astFacade ?? new AstFacade();
        foreach ($classFiles as $className => $filePath) {
            $ast->cleanupGeneratedMembers($filePath, (string) $className);
        }
    }

    private function ensureDirectory(string $path): void
    {
        if ($path === '') {
            return;
        }

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Failed to create directory: %s', $path));
        }
    }

    private function removeDirectoryContents(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

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
                $this->removeDirectoryContents($full);
                rmdir($full);
                continue;
            }

            unlink($full);
        }
    }

    private function persistPhpInstruction(PhpPersistInstructionSchema $instruction): string
    {
        $targetPath = $instruction->targetPath;
        if ($targetPath === '') {
            throw new \RuntimeException('PHP persist instruction targetPath is empty');
        }

        $ast = $this->astFacade ?? new AstFacade();
        $plan = new AstClassMutationPlan(
            targetPath: $instruction->targetPath,
            className: $instruction->className,
            nodes: $instruction->astNodes,
            headerComments: $instruction->headerComments,
        );

        $ast->applyMutationPlan($plan);
        return $targetPath;
    }
}
