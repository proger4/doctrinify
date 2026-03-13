<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use App\Tools\Codebase\CodebaseInputLoader;

final class CodebaseInputLoaderTest extends Unit
{
    private string $projectRoot;
    private string $tmpRoot;

    protected function _before(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->tmpRoot = $this->projectRoot . '/tests/_output/codebase_loader';
        $this->resetDir($this->tmpRoot);
    }

    public function testUsesClassListWhenPresent(): void
    {
        $configPath = $this->tmpRoot . '/with-classlist.yaml';
        file_put_contents($this->tmpRoot . '/classlist.txt', "app\\models\\User\napp\\models\\Product\n");
        file_put_contents($configPath, $this->baseConfigYaml('tests/_data/mock/models', 'tests/_data/mock/database/schema.sql'));

        $loader = new CodebaseInputLoader($this->projectRoot);
        $input = $loader->load($configPath, true);

        $this->assertSame([], $input->warnings);
        $this->assertSame(['app\\models\\Product', 'app\\models\\User'], $input->classes);
        $this->assertArrayHasKey('app\\models\\User', $input->classFiles);
    }

    public function testMissingClassListFallsBackToAutoscan(): void
    {
        $configPath = $this->tmpRoot . '/no-classlist.yaml';
        file_put_contents($configPath, $this->baseConfigYaml('tests/_data/mock/models', 'tests/_data/mock/database/schema.sql'));

        $loader = new CodebaseInputLoader($this->projectRoot);
        $input = $loader->load($configPath, true);

        $this->assertNotEmpty($input->warnings);
        $this->assertStringContainsString('fallback to models_path autoscan', $input->warnings[0]);
        $this->assertCount(9, $input->classes);
        $this->assertContains('app\\models\\User', $input->classes);
        $this->assertNotContains('app\\models\\BaseModel', $input->classes);
    }

    public function testMissingConfiguredClassListFallsBackToAutoscan(): void
    {
        $configPath = $this->tmpRoot . '/missing-configured-classlist.yaml';
        $yaml = $this->baseConfigYaml('tests/_data/mock/models', 'tests/_data/mock/database/schema.sql')
            . "\nclasslist_path: 'tests/_output/missing-classlist.txt'\n";
        file_put_contents($configPath, $yaml);

        $loader = new CodebaseInputLoader($this->projectRoot);
        $input = $loader->load($configPath, true);

        $this->assertNotEmpty($input->warnings);
        $this->assertCount(9, $input->classes);
    }

    public function testAutoscanAppliesWildcardBlacklistAndBaseClassFilter(): void
    {
        $modelsCopy = $this->tmpRoot . '/models';
        $this->copyDir($this->projectRoot . '/tests/_data/mock/models', $modelsCopy);

        file_put_contents(
            $modelsCopy . '/TestGhost.php',
            "<?php\nnamespace app\\models;\nclass TestGhost extends BaseModel { public static function tableName(){ return 'ghost'; } }\n"
        );

        $configPath = $this->tmpRoot . '/autoscan-filter.yaml';
        file_put_contents($configPath, $this->baseConfigYaml('tests/_output/codebase_loader/models', 'tests/_data/mock/database/schema.sql'));

        $loader = new CodebaseInputLoader($this->projectRoot);
        $input = $loader->load($configPath, true);

        $this->assertNotContains('app\\models\\TestGhost', $input->classes);
        $this->assertNotContains('app\\models\\BaseModel', $input->classes);
    }

    private function baseConfigYaml(string $modelsPath, string $schemaPath): string
    {
        return <<<YAML
models_path: '{$modelsPath}'
base_classes:
  - 'app\\models\\BaseModel'
doctrine_xml_path: 'tests/_output/generated/doctrine'
schema_path: '{$schemaPath}'
blacklist:
  - 'app\\models\\Old*'
  - 'app\\models\\Test*'
model_scan_exclude_dirs:
  - 'vendor'
  - 'tests'
  - 'runtime'
flags:
  generate_doctrine_xml: true
  generate_php_accessors: true
YAML;
    }

    private function resetDir(string $path): void
    {
        if (is_dir($path)) {
            $this->deleteDir($path);
        }
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function deleteDir(string $path): void
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
                $this->deleteDir($full);
                rmdir($full);
                continue;
            }
            unlink($full);
        }
    }

    private function copyDir(string $from, string $to): void
    {
        if (!is_dir($to)) {
            mkdir($to, 0777, true);
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
            if (is_dir($src)) {
                $this->copyDir($src, $dst);
            } else {
                copy($src, $dst);
            }
        }
    }
}
