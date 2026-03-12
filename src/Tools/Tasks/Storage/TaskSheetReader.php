<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Storage;

use Doctrinify\Tools\Tasks\Contracts\PromptDefinition;
use Doctrinify\Tools\Tasks\Contracts\TaskDefinition;
use Doctrinify\Tools\Tasks\Contracts\TaskInputDto;
use Doctrinify\Tools\Tasks\Contracts\TaskWorkbookDto;

final class TaskSheetReader
{
    public function read(string $xlsxPath): TaskWorkbookDto
    {
        if (!is_file($xlsxPath)) {
            throw new \RuntimeException(sprintf('Task file not found: %s', $xlsxPath));
        }

        $zip = new \ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            throw new \RuntimeException(sprintf('Cannot open xlsx: %s', $xlsxPath));
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheets = $this->readAllSheets($zip, $sharedStrings);
        } finally {
            $zip->close();
        }

        $tasksRows = $sheets['TASKS'] ?? [];
        $promptsRows = $sheets['PROMPTS'] ?? [];
        $artifactsRows = $sheets['ARTIFACTS'] ?? [];
        $inputsRows = $sheets['INPUTS'] ?? [];
        $doneRows = $sheets['DONE_ARTIFACTS'] ?? [];

        $tasks = [];
        foreach ($tasksRows as $row) {
            $taskId = trim((string) ($row['TASK_ID'] ?? ''));
            if ($taskId === '') {
                continue;
            }

            $tasks[] = new TaskDefinition(
                taskId: $taskId,
                name: trim((string) ($row['NAME'] ?? $taskId)),
                category: strtolower(trim((string) ($row['CAT'] ?? 'general'))),
                done: strtoupper(trim((string) ($row['DONE'] ?? 'N'))) === 'Y',
                description: trim((string) ($row['DESCRIPTIONS'] ?? '')),
                artifactName: trim((string) ($row['ARTIFACT_NAME'] ?? '')),
                resultArtifact: trim((string) ($row['RESULT_ARTIFACT'] ?? '')),
            );
        }

        $prompts = [];
        foreach ($promptsRows as $row) {
            $name = trim((string) ($row['NAME'] ?? ''));
            if ($name === '') {
                continue;
            }

            $prompts[$name] = new PromptDefinition(
                name: $name,
                body: trim((string) ($row['PROMPT_BODY'] ?? '')),
            );
        }

        $artifacts = [];
        foreach ($artifactsRows as $row) {
            $name = trim((string) ($row['NAME'] ?? ''));
            if ($name === '') {
                continue;
            }

            $inputs = array_values(array_filter(array_map(
                static fn (string $v): string => trim($v),
                preg_split('/[;,]+/', (string) ($row['INPUTS'] ?? '')) ?: []
            ), static fn (string $v): bool => $v !== ''));

            $artifacts[$name] = [
                'name' => $name,
                'inputs' => $inputs,
                'prompt_name' => trim((string) ($row['PROMPT_NAME'] ?? '')),
            ];
        }

        $inputs = [];
        foreach ($inputsRows as $row) {
            $name = trim((string) ($row['NAME'] ?? ''));
            if ($name === '') {
                continue;
            }

            $sourceRef = trim((string) ($row['SOURCE_REF'] ?? ''));
            $inputs[$name] = new TaskInputDto(
                name: $name,
                sourceRef: $sourceRef,
                policy: trim((string) ($row['POLICY'] ?? '')),
                content: '',
            );
        }

        return new TaskWorkbookDto(
            tasks: $tasks,
            prompts: $prompts,
            artifacts: $artifacts,
            inputs: $inputs,
            doneArtifacts: $doneRows,
        );
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    private function readAllSheets(\ZipArchive $zip, array $sharedStrings): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            throw new \RuntimeException('Invalid xlsx: workbook parts are missing.');
        }

        $sheetRidByName = [];
        $workbook = simplexml_load_string($workbookXml);
        if (!$workbook instanceof \SimpleXMLElement) {
            throw new \RuntimeException('Invalid workbook.xml');
        }

        foreach ($workbook->sheets->sheet as $sheet) {
            $name = (string) $sheet['name'];
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if ($name !== '' && $rid !== '') {
                $sheetRidByName[$name] = $rid;
            }
        }

        $ridToTarget = [];
        $rels = simplexml_load_string($relsXml);
        if (!$rels instanceof \SimpleXMLElement) {
            throw new \RuntimeException('Invalid workbook.xml.rels');
        }

        foreach ($rels->Relationship as $rel) {
            $rid = (string) $rel['Id'];
            $target = (string) $rel['Target'];
            if ($rid !== '' && $target !== '') {
                $ridToTarget[$rid] = 'xl/' . ltrim($target, '/');
            }
        }

        $out = [];
        foreach ($sheetRidByName as $name => $rid) {
            $target = $ridToTarget[$rid] ?? null;
            if ($target === null) {
                continue;
            }

            $sheetXml = $zip->getFromName($target);
            if (!is_string($sheetXml)) {
                continue;
            }

            $out[$name] = $this->parseSheetRows($sheetXml, $sharedStrings);
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml)) {
            return [];
        }

        $doc = simplexml_load_string($xml);
        if (!$doc instanceof \SimpleXMLElement) {
            return [];
        }

        $values = [];
        foreach ($doc->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string) $si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
            }
            $values[] = $text;
        }

        return $values;
    }

    /**
     * @param list<string> $sharedStrings
     * @return list<array<string, string>>
     */
    private function parseSheetRows(string $sheetXml, array $sharedStrings): array
    {
        $sheet = simplexml_load_string($sheetXml);
        if (!$sheet instanceof \SimpleXMLElement || !isset($sheet->sheetData)) {
            return [];
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $rowNode) {
            $row = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $ref) ?: '';
                if ($column === '') {
                    continue;
                }

                $row[$column] = $this->readCellValue($cell, $sharedStrings);
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            return [];
        }

        $headerRow = array_shift($rows) ?: [];
        if ($headerRow === []) {
            return [];
        }

        $headers = [];
        foreach ($headerRow as $column => $title) {
            $headers[$column] = strtoupper(trim($title));
        }

        $out = [];
        foreach ($rows as $row) {
            $assoc = [];
            foreach ($headers as $column => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = trim((string) ($row[$column] ?? ''));
            }

            if ($assoc !== []) {
                $out[] = $assoc;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function readCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr' && isset($cell->is->t)) {
            return (string) $cell->is->t;
        }

        if ($type === 's') {
            $index = (int) ((string) $cell->v);
            return $sharedStrings[$index] ?? '';
        }

        if (isset($cell->v)) {
            return (string) $cell->v;
        }

        return '';
    }
}
