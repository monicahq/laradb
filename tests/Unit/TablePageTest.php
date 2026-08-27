<?php

declare(strict_types=1);

namespace LaraDb\Tests\Unit;

use LaraDb\DTO\ColumnInfo;
use LaraDb\DTO\TablePage;
use PHPUnit\Framework\TestCase;

final class TablePageTest extends TestCase
{
    private function page(int $current, int $total, int $perPage = 10, int $rows = 10): TablePage
    {
        return new TablePage(
            table: 'posts',
            columns: [new ColumnInfo('id', 'integer', false, true)],
            rows: array_fill(0, $rows, ['id' => 1]),
            page: $current,
            perPage: $perPage,
            total: $total,
        );
    }

    public function test_it_computes_the_last_page(): void
    {
        $this->assertSame(1, $this->page(1, 0)->lastPage());
        $this->assertSame(1, $this->page(1, 10)->lastPage());
        $this->assertSame(2, $this->page(1, 11)->lastPage());
        $this->assertSame(10, $this->page(1, 100)->lastPage());
    }

    public function test_it_knows_where_it_sits_in_the_result_set(): void
    {
        $page = $this->page(3, 100);

        $this->assertTrue($page->hasPreviousPage());
        $this->assertTrue($page->hasNextPage());
        $this->assertSame(21, $page->from());
        $this->assertSame(30, $page->to());
        $this->assertFalse($page->isEmpty());
    }

    public function test_an_empty_page_has_no_range(): void
    {
        $page = $this->page(1, 0, 10, 0);

        $this->assertTrue($page->isEmpty());
        $this->assertNull($page->from());
        $this->assertNull($page->to());
        $this->assertFalse($page->hasNextPage());
        $this->assertFalse($page->hasPreviousPage());
    }

    public function test_it_lists_every_page_when_there_are_few(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], $this->page(1, 50)->paginationWindow());
    }

    public function test_it_condenses_the_pagination_with_ellipses(): void
    {
        $this->assertSame([1, null, 48, 49, 50, 51, 52, null, 100], $this->page(50, 1000)->paginationWindow());
        $this->assertSame([1, 2, 3, 4, 5, null, 100], $this->page(3, 1000)->paginationWindow());
        $this->assertSame([1, null, 96, 97, 98, 99, 100], $this->page(98, 1000)->paginationWindow());
    }

    public function test_it_serialises_to_an_array(): void
    {
        $array = $this->page(2, 100)->toArray();

        $this->assertSame('posts', $array['table']);
        $this->assertSame(2, $array['page']);
        $this->assertSame(10, $array['per_page']);
        $this->assertSame(100, $array['total']);
        $this->assertSame(10, $array['last_page']);
        $this->assertSame([
            'name' => 'id',
            'type' => 'integer',
            'nullable' => false,
            'primary_key' => true,
            'default' => null,
            'foreign_key' => null,
        ], $array['columns'][0]);
        $this->assertSame('', $array['sql']);
        $this->assertSame(0.0, $array['duration_ms']);
    }

    public function test_it_carries_the_statement_that_produced_it(): void
    {
        $page = new TablePage(
            table: 'posts',
            columns: [new ColumnInfo('id', 'integer', false, true)],
            rows: [['id' => 1]],
            page: 1,
            perPage: 10,
            total: 1,
            sql: 'SELECT * FROM "posts" LIMIT 10 OFFSET 0',
            durationMs: 1.25,
        );

        $this->assertSame('SELECT * FROM "posts" LIMIT 10 OFFSET 0', $page->toArray()['sql']);
        $this->assertSame(1.25, $page->toArray()['duration_ms']);
    }

    public function test_it_names_the_primary_key(): void
    {
        $this->assertSame('id', $this->page(1, 10)->primaryKey());

        $composite = new TablePage(
            table: 'taggables',
            columns: [
                new ColumnInfo('tag_id', 'integer', false, true),
                new ColumnInfo('taggable_id', 'integer', false, true),
                new ColumnInfo('note', 'text'),
            ],
            rows: [],
            page: 1,
            perPage: 10,
            total: 0,
        );

        $this->assertSame('tag_id, taggable_id', $composite->primaryKey());

        $none = new TablePage(
            table: 'logs',
            columns: [new ColumnInfo('message', 'text')],
            rows: [],
            page: 1,
            perPage: 10,
            total: 0,
        );

        $this->assertNull($none->primaryKey());
    }
}
