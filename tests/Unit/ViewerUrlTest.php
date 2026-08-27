<?php

declare(strict_types=1);

namespace LaraDb\Tests\Unit;

use LaraDb\DTO\RowFilter;
use LaraDb\Support\ViewerUrl;
use PHPUnit\Framework\TestCase;

final class ViewerUrlTest extends TestCase
{
    private ViewerUrl $url;

    protected function setUp(): void
    {
        parent::setUp();

        $this->url = new ViewerUrl('http://localhost/db');
    }

    public function test_it_builds_the_page_url(): void
    {
        $this->assertSame('/db?table=users', $this->url->page('users'));
        $this->assertSame('/db?table=users&page=3', $this->url->page('users', 3));
    }

    public function test_it_builds_the_fragment_url(): void
    {
        $this->assertSame('/db/tables/users', $this->url->fragment('users'));
        $this->assertSame('/db/tables/users?page=3', $this->url->fragment('users', 3));
    }

    public function test_the_first_page_is_left_implicit(): void
    {
        $this->assertStringNotContainsString('page=', $this->url->page('users', 1));
        $this->assertStringNotContainsString('page=', $this->url->fragment('users', 1));
    }

    public function test_it_carries_a_filter(): void
    {
        $filter = new RowFilter('id', '3');

        $this->assertSame(
            '/db?table=accounts&column=id&value=3&from=users.account_id',
            $this->url->page('accounts', 1, $filter, 'users.account_id'),
        );
        $this->assertSame(
            '/db/tables/accounts?column=id&value=3',
            $this->url->fragment('accounts', 1, $filter),
        );
    }

    public function test_the_origin_is_dropped_without_a_filter(): void
    {
        $this->assertStringNotContainsString('from=', $this->url->page('accounts', 1, null, 'users.account_id'));
    }

    public function test_it_reduces_an_absolute_base_to_a_path(): void
    {
        // APP_URL can disagree with the host you are actually on. A relative
        // URL cannot, so the viewer never fetches across origins.
        $url = new ViewerUrl('https://example.test:8443/internal/db-2f9c');

        $this->assertSame('/internal/db-2f9c?table=users', $url->page('users'));
        $this->assertSame('/internal/db-2f9c/tables/users', $url->fragment('users'));
        $this->assertSame('/internal/db-2f9c', $url->root());
    }

    public function test_it_encodes_what_it_is_given(): void
    {
        $filter = new RowFilter('id', "' OR 1=1 --");
        $link = $this->url->page('we ird', 1, $filter);

        $this->assertStringContainsString('table=we%20ird', $link);
        $this->assertStringContainsString('value=%27%20OR%201%3D1%20--', $link);

        // The table also has to survive being a path segment.
        $this->assertStringContainsString('/tables/we%20ird', $this->url->fragment('we ird'));
    }
}
