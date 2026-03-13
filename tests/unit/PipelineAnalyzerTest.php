<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use App\Tools\Analysis\PipelineAnalyzer;
use App\Tools\Schemas\Pipeline\IntrospectionResultSchema;
use App\Tools\Schemas\Pipeline\ModelIntrospectionSchema;
use App\Tools\Schemas\Pipeline\ModelRelationSchema;
use App\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;

final class PipelineAnalyzerTest extends Unit
{
    public function testKeepsSqlRelationAndReportsModifiersInDiagnostics(): void
    {
        $model = new ModelIntrospectionSchema(
            className: 'app\\models\\X',
            extends: null,
            isAbstract: false,
            table: 'x',
            relations: [
                new ModelRelationSchema(
                    name: 'badRelation',
                    kind: 'one-to-many',
                    target: 'app\\models\\Y',
                    mapping: ['id' => 'x_id'],
                    queryModifiers: ['where', 'joinType'],
                ),
            ],
            discriminator: null,
            rules: [],
            attributeLabels: [],
            primaryKey: [],
        );

        $input = new IntrospectionResultSchema(
            models: ['app\\models\\X' => $model],
            database: new DatabaseIntrospectionDto(tables: [], sequences: []),
        );

        $analyzer = new PipelineAnalyzer();
        $result = $analyzer->analyze($input);

        $this->assertCount(1, $result->models['app\\models\\X']->relations);
        $decision = $result->models['app\\models\\X']->relations[0];
        $this->assertTrue($decision->accepted);
        $this->assertNotNull($decision->rejectionReason);
        $this->assertStringContainsString('where', (string) $decision->rejectionReason);
        $this->assertStringContainsString('joinType', (string) $decision->rejectionReason);

        $report = $analyzer->renderReport($result->diagnostics);
        $this->assertStringContainsString('relation `badRelation` has SQL modifiers', $report);
        $this->assertStringContainsString('RELATION_SQL_MODIFIERS', $report);
    }
}
