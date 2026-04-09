<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Feature;

use Baueri\Mint\MintCompiler;
use Baueri\Mint\Tests\Support\TempViews;
use PHPUnit\Framework\TestCase;

final class CompilerErrorTest extends TestCase
{
    public function testCompileThrowsOnMissingTemplate(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $compiler = new MintCompiler($viewsDir);

        $this->expectException(\RuntimeException::class);
        $compiler->compile($viewsDir . '/missing.php');
    }
}

