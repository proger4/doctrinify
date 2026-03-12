<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Doctrinify\Command\AiTaskExecuteCommand;
use Doctrinify\Command\BuildTaskReportCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AiTaskLayerCommandTest extends Unit
{
    private string $projectRoot;
    private string $workDir;
    private string $storageRoot;
    private string $xlsxPath;
    private string $configPath;

    protected function _before(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->workDir = $this->projectRoot . '/tests/_output/tasks_runtime';
        $this->storageRoot = $this->workDir . '/var/tasks';
        $this->xlsxPath = $this->workDir . '/tasks.xlsx';
        $this->configPath = $this->workDir . '/tasks-config.yaml';

        $this->resetDirectory($this->workDir);

        $modelPath = $this->workDir . '/input/User.php';
        $schemaPath = $this->workDir . '/input/USER.sql';
        $xmlPath = $this->workDir . '/input/User.orm.xml';
        $mismatchPath = $this->workDir . '/input/mismatch.txt';
        $configExcerptPath = $this->workDir . '/input/config.txt';

        $this->ensureDir(dirname($modelPath));
        file_put_contents($modelPath, "<?php\nnamespace app\\models;\nclass User extends BaseModel {\n    public function relations(): array { return ['items' => ['condition' => 'x=1']]; }\n}\n");
        file_put_contents($schemaPath, "CREATE TABLE USER (ID INT PRIMARY KEY, TYPE VARCHAR(10));");
        file_put_contents($xmlPath, '<entity name="app\\models\\User"></entity>');
        file_put_contents($mismatchPath, '[RELATION_REJECTED] app\\models\\User::items contains SQL query modifiers');
        file_put_contents($configExcerptPath, 'flags: use_ast_parsing=true');

        file_put_contents($this->configPath, "tools:\n  ai:\n    task_storage_root: '{$this->storageRoot}'\n    default_report_open: false\n    weak_model_mode: true\n    max_input_length: 2000\n");

        $this->createWorkbook($this->xlsxPath, [
            'TASKS' => [
                ['TASK_ID', 'NAME', 'CAT', 'DONE', 'DESCRIPTIONS', 'ARTIFACT_NAME', 'RESULT_ARTIFACT'],
                ['MODEL_001', 'Analyze User', 'models_ai_analysis', 'N', 'single model', 'ART_MODEL_USER', 'result__MODEL_001.json'],
            ],
            'DONE_ARTIFACTS' => [
                ['TASK_ID', 'ARTIFACT_NAME', 'COMMENT', 'TIMESTAMP'],
            ],
            'INPUTS' => [
                ['NAME', 'SOURCE_REF', 'POLICY'],
                ['model_source', $modelPath, 'one model only'],
                ['schema_excerpt', $schemaPath, 'schema only'],
                ['generated_xml', $xmlPath, 'xml excerpt'],
                ['mismatch', $mismatchPath, 'rejection reasons'],
                ['config_excerpt', $configExcerptPath, 'flags excerpt'],
            ],
            'ARTIFACTS' => [
                ['NAME', 'INPUTS', 'PROMPT_NAME'],
                ['ART_MODEL_USER', 'model_source;schema_excerpt;generated_xml;mismatch;config_excerpt', 'MODEL_AI_ANALYSIS'],
            ],
            'PROMPTS' => [
                ['NAME', 'PROMPT_BODY'],
                ['MODEL_AI_ANALYSIS', 'Return strict JSON diagnostics only.'],
            ],
            'DICTIONARY' => [
                ['ID', 'VALUE', 'DESCRIPTION'],
                ['RISK_HIGH', 'high', 'High risk'],
            ],
        ]);
    }

    public function testExecuteAiTasksAndBuildHtmlReport(): void
    {
        $execute = new CommandTester(new AiTaskExecuteCommand());
        $executeCode = $execute->execute([
            '--config' => $this->configPath,
            '--file' => $this->xlsxPath,
            '--task-set' => 'models_ai_analysis',
        ]);

        $this->assertSame(Command::SUCCESS, $executeCode);
        $this->assertStringContainsString('Executed tasks: 1', $execute->getDisplay());
        $this->assertFileExists($this->storageRoot . '/models_ai_analysis/results/result__MODEL_001.json');
        $this->assertFileExists($this->storageRoot . '/models_ai_analysis/prompts/prompt__MODEL_001.md');

        $rawJson = (string) file_get_contents($this->storageRoot . '/models_ai_analysis/results/result__MODEL_001.json');
        $this->assertStringContainsString('"risk_level": "medium"', $rawJson);
        $this->assertStringContainsString('"task_id": "MODEL_001"', $rawJson);

        $report = new CommandTester(new BuildTaskReportCommand());
        $reportCode = $report->execute([
            '--config' => $this->configPath,
            '--name' => 'models_ai_analysis',
            '--no-open' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $reportCode);
        $this->assertStringContainsString('Results included: 1', $report->getDisplay());

        $reportPath = $this->storageRoot . '/models_ai_analysis/reports/models_ai_analysis.html';
        $this->assertFileExists($reportPath);
        $html = (string) file_get_contents($reportPath);
        $this->assertStringContainsString('Report: models_ai_analysis', $html);
        $this->assertStringContainsString('MODEL_001', $html);
    }

    private function resetDirectory(string $path): void
    {
        if (is_dir($path)) {
            $this->deleteRecursive($path);
        }
        $this->ensureDir($path);
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

    private function ensureDir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException('Cannot create dir: ' . $path);
        }
    }

    /**
     * @param array<string, list<list<string>>> $sheets
     */
    private function createWorkbook(string $path, array $sheets): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create xlsx: ' . $path);
        }

        $sheetNames = array_keys($sheets);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($sheetNames)));
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetNames));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml(count($sheetNames)));

        $index = 1;
        foreach ($sheetNames as $name) {
            $zip->addFromString('xl/worksheets/sheet' . $index . '.xml', $this->sheetXml($sheets[$name]));
            $index++;
        }

        $zip->close();
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    /**
     * @param list<string> $sheetNames
     */
    private function workbookXml(array $sheetNames): string
    {
        $sheetsXml = '';
        $i = 1;
        foreach ($sheetNames as $name) {
            $sheetsXml .= '<sheet name="' . $this->x($name) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $i++;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(int $sheetCount): string
    {
        $rels = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    /**
     * @param list<list<string>> $rows
     */
    private function sheetXml(array $rows): string
    {
        $sheetData = '';
        $rowNumber = 1;
        foreach ($rows as $row) {
            $cells = '';
            $col = 0;
            foreach ($row as $value) {
                $ref = $this->colLetter($col) . $rowNumber;
                $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $this->x($value) . '</t></is></c>';
                $col++;
            }
            $sheetData .= '<row r="' . $rowNumber . '">' . $cells . '</row>';
            $rowNumber++;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '</worksheet>';
    }

    private function colLetter(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intdiv($index - $mod - 1, 26);
        }

        return $letters;
    }

    private function x(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
