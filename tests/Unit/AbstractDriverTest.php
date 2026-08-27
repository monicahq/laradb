<?php

declare(strict_types=1);

namespace LaraDb\Tests\Unit;

use LaraDb\Tests\Fixtures\UnprivilegedDriver;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * The behaviour every driver inherits, exercised through a stand-in whose
 * introspection is refused at every turn.
 */
final class AbstractDriverTest extends TestCase
{
    private UnprivilegedDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->driver = new UnprivilegedDriver($pdo);
    }

    public function test_a_refused_probe_leaves_a_hole_rather_than_an_exception(): void
    {
        $info = $this->driver->describe();

        $this->assertSame('unprivileged', $info->engine);
        $this->assertNull($info->version);
        $this->assertNull($info->name);
        $this->assertNull($info->indexCount);
        $this->assertNull($info->sizeInBytes);
        $this->assertNull($info->formattedSize());
        $this->assertSame(1, $info->tableCount);
        $this->assertSame([], $info->metadata);
    }

    public function test_a_refused_foreign_key_lookup_yields_no_references(): void
    {
        // The columns still render; they just carry no reference.
        $this->assertSame([], $this->driver->getForeignKeys('posts'));
        $this->assertSame([], $this->driver->getColumns('posts'));
    }
}
