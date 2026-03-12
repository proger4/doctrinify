<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Doctrinify\Tools\Analysis\PipelineAnalyzer;
use Doctrinify\Tools\Schemas\Pipeline\IntrospectionResultSchema;
use Doctrinify\Tools\Schemas\Pipeline\ModelIntrospectionSchema;
use Doctrinify\Tools\Schemas\Pipeline\ModelRelationSchema;
use Doctrinify\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;

final class PipelineAnalyzerTest extends Unit
{
    public function testRejectsSqlRelationAndExposesReasonInDiagnostics(): void
    {
        $model = new ModelIntrospectionSchema(
            className: 'app\\models\\X',
            extends: null,
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
        $this->assertFalse($decision->accepted);
        $this->assertNotNull($decision->rejectionReason);
        $this->assertStringContainsString('where', (string) $decision->rejectionReason);
        $this->assertStringContainsString('joinType', (string) $decision->rejectionReason);

        $report = $analyzer->renderReport($result->diagnostics);
        $this->assertStringContainsString('relation `badRelation` rejected', $report);
        $this->assertStringContainsString('RELATION_REJECTED', $report);
    }
}
