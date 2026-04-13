<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Unit\Directive;

use Baueri\Mint\Directive\Text\ForeachDirective;
use PHPUnit\Framework\TestCase;

final class TextForeachDirectiveTest extends TestCase
{
    public function testCompilesForeachBlocks(): void
    {
        $d = new ForeachDirective();
        $out = $d->compile('@foreach($xs as $x)X@endforeach');

        $this->assertStringContainsString('<?php foreach($xs as $x): ?>', $out);
        $this->assertStringContainsString('<?php endforeach; ?>', $out);
    }

    public function testInnerParenthesesInExpression(): void
    {
        $d = new ForeachDirective();
        $out = $d->compile('@foreach($items->slice(0, 3) as $i)X@endforeach');

        $this->assertStringContainsString('<?php foreach($items->slice(0, 3) as $i): ?>', $out);
    }
}

