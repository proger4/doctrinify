<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Doctrinify\Tools\Schemas\YamlProjectProfile;

final class YamlProjectProfileTest extends Unit
{
    public function testLoadsValuesFromYamlConfig(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $profile = YamlProjectProfile::fromFile($projectRoot, 'tests/_data/mock/config.yaml');

        $this->assertSame('app\\models', $profile->getGeneratedPhpNamespace());
        $this->assertSame('{class}.orm.xml', $profile->getDoctrineXmlFilenamePattern());
        $this->assertSame('overwrite_all', $profile->getRegenerationStrategy());
        $this->assertTrue($profile->shouldAddGeneratedMarker());
        $this->assertFalse($profile->shouldEmbedDiagnostics());

        $rootAttributes = $profile->getDoctrineXmlRootAttributes();
        $this->assertArrayHasKey('xmlns', $rootAttributes);
        $this->assertSame('http://doctrine-project.org/schemas/orm/doctrine-mapping', $rootAttributes['xmlns']);
    }

    public function testDefaultsWhenProjectProfileSectionMissing(): void
    {
        $profile = new YamlProjectProfile([]);

        $this->assertSame('generated\\classes', $profile->getGeneratedPhpNamespace());
        $this->assertSame('{class}.orm.xml', $profile->getDoctrineXmlFilenamePattern());
        $this->assertSame('overwrite_all', $profile->getRegenerationStrategy());
        $this->assertTrue($profile->shouldAddGeneratedMarker());
        $this->assertFalse($profile->shouldEmbedDiagnostics());
    }
}
