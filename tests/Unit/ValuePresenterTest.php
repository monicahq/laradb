<?php

declare(strict_types=1);

namespace LaraDb\Tests\Unit;

use LaraDb\Support\ValuePresenter;
use PHPUnit\Framework\TestCase;

final class ValuePresenterTest extends TestCase
{
    public function test_it_renders_null_distinctly(): void
    {
        $presenter = new ValuePresenter;

        $this->assertTrue($presenter->isNull(null));
        $this->assertFalse($presenter->isNull(''));
        $this->assertSame('NULL', $presenter->display(null));
    }

    public function test_it_renders_scalars(): void
    {
        $presenter = new ValuePresenter;

        $this->assertSame('42', $presenter->display(42));
        $this->assertSame('3.5', $presenter->display(3.5));
        $this->assertSame('true', $presenter->display(true));
        $this->assertSame('false', $presenter->display(false));
        $this->assertSame('hello', $presenter->display('hello'));
    }

    public function test_it_truncates_long_values_and_keeps_the_full_one(): void
    {
        $presenter = new ValuePresenter(10);
        $value = str_repeat('a', 25);

        $this->assertTrue($presenter->isTruncated($value));
        $this->assertSame(str_repeat('a', 10).'…', $presenter->display($value));
        $this->assertSame($value, $presenter->full($value));
    }

    public function test_it_leaves_short_values_alone(): void
    {
        $presenter = new ValuePresenter(10);

        $this->assertFalse($presenter->isTruncated('short'));
        $this->assertSame('short', $presenter->display('short'));
    }

    public function test_truncation_can_be_disabled(): void
    {
        $presenter = new ValuePresenter(0);
        $value = str_repeat('a', 500);

        $this->assertFalse($presenter->isTruncated($value));
        $this->assertSame($value, $presenter->display($value));
    }

    public function test_it_counts_characters_not_bytes(): void
    {
        $presenter = new ValuePresenter(3);

        $this->assertSame('éàü…', $presenter->display('éàürs'));
    }
}
