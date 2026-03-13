<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use App\Tools\Database\SchemaDumpParser;

final class SchemaDumpParserTest extends Unit
{
    private SchemaDumpParser $parser;
    private string $projectRoot;

    protected function _before(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->parser = new SchemaDumpParser();
    }

    public function testRejectsMysqlMockDump(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Schema parser found zero tables');
        $this->parser->parseFile($this->projectRoot . '/tests/_data/mock/database/schema.sql');
    }

    public function testParsesOracleMockDump(): void
    {
        $dto = $this->parser->parseFile($this->projectRoot . '/tests/_data/oracle/schema.sql');

        $this->assertCount(7, $dto->tables);
        $this->assertCount(5, $dto->sequences);
        $this->assertArrayHasKey('PRODUCT_CATEGORY', $dto->tables);
        $this->assertArrayHasKey('SEQ_PRODUCT', $dto->sequences);

        $productCategory = $dto->tables['PRODUCT_CATEGORY'];
        $this->assertTrue($productCategory->isManyMany);
        $this->assertSame(['PRODUCT_ID', 'CATEGORY_ID'], $productCategory->primaryKey);
        $this->assertCount(2, $productCategory->foreignKeys);
    }
}
