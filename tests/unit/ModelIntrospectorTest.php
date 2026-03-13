<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Doctrinify\Tools\Introspection\ModelIntrospector;

final class ModelIntrospectorTest extends Unit
{
    public function testParsesSqlRelationModifiersFromMockModel(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $file = $projectRoot . '/tests/_data/mock/models/Product.php';

        $introspector = new ModelIntrospector();
        $meta = $introspector->introspect('app\\models\\Product', $file);

        $target = null;
        foreach ($meta->relations as $relation) {
            if ($relation->name === 'categoriesWithSql') {
                $target = $relation;
                break;
            }
        }

        $this->assertNotNull($target, 'categoriesWithSql relation should be parsed');
        assert($target !== null);

        $this->assertContains('where', $target->queryModifiers);
        $this->assertContains('orderBy', $target->queryModifiers);
        $this->assertContains('joinWith', $target->queryModifiers);
        $this->assertContains('condition', $target->queryModifiers);
        $this->assertContains('order', $target->queryModifiers);
        $this->assertContains('joinType', $target->queryModifiers);
    }
}
