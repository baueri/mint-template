<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Unit\Directive;

use Baueri\Mint\Directive\Text\IfDirective;
use PHPUnit\Framework\TestCase;

final class TextIfDirectiveTest extends TestCase
{
    public function testWhitespaceAfterDirectiveIsOptional(): void
    {
        $d = new IfDirective();

        $out1 = $d->compile('@if($ok)YES@endif');
        $this->assertStringContainsString('<?php if($ok): ?>', $out1);

        $out2 = $d->compile('@if ($ok)YES@endif');
        $this->assertStringContainsString('<?php if($ok): ?>', $out2);
    }

    public function testElseifElseEndifCompile(): void
    {
        $d = new IfDirective();
        $out = $d->compile('@if($a)A@elseif($b)B@else C@endif');

        $this->assertStringContainsString('<?php if($a): ?>', $out);
        $this->assertStringContainsString('<?php elseif($b): ?>', $out);
        $this->assertStringContainsString('<?php else: ?>', $out);
        $this->assertStringContainsString('<?php endif; ?>', $out);
    }
}

