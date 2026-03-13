<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use App\Tools\Schemas\YamlProjectProfile;

final class YamlProjectProfileTest extends Unit
{
    public function testLoadsValuesFromYamlConfig(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $profile = YamlProjectProfile::fromFile('tests/_data/mock/config.yaml', $projectRoot);

        $this->assertSame('{class}.orm.xml', $profile->getDoctrineXmlFilenamePattern());
        $this->assertSame('doctrinify', $profile->getGenerationNaming());
        $this->assertTrue($profile->shouldAddGeneratedMarker());
        $this->assertFalse($profile->shouldEmbedDiagnostics());

        $rootAttributes = $profile->getDoctrineXmlRootAttributes();
        $this->assertArrayHasKey('xmlns', $rootAttributes);
        $this->assertSame('http://doctrine-project.org/schemas/orm/doctrine-mapping', $rootAttributes['xmlns']);
    }

    public function testDefaultsWhenProjectProfileSectionMissing(): void
    {
        $profile = new YamlProjectProfile([]);

        $this->assertSame('{class}.orm.xml', $profile->getDoctrineXmlFilenamePattern());
        $this->assertSame('doctrinify', $profile->getGenerationNaming());
        $this->assertTrue($profile->shouldAddGeneratedMarker());
        $this->assertFalse($profile->shouldEmbedDiagnostics());
    }

    public function testSupportsCustomNamingFromToolingConfig(): void
    {
        $profile = new YamlProjectProfile([
            'tooling' => [
                'regeneration' => [
                    'naming' => 'acme-doctrine-kit',
                ],
            ],
        ]);

        $this->assertSame('acme-doctrine-kit', $profile->getGenerationNaming());
    }
}
