<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EscapeHelperTest extends TestCase
{
    public function testEscapesHtml(): void
    {
        $this->assertSame('&lt;div&gt;', e('<div>'));
        $this->assertSame('Ivan &amp; Co', e('Ivan & Co'));
        $this->assertSame('&quot;quote&quot;', e('"quote"'));
        $this->assertSame('&#039;single&#039;', e("'single'"));
    }

    public function testNullBecomesEmptyString(): void
    {
        $this->assertSame('', e(null));
    }
}

