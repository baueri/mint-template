<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Feature;

use Baueri\Mint\MintCompiler;
use Baueri\Mint\Tests\Support\TempViews;
use PHPUnit\Framework\TestCase;

final class CompilerErrorTest extends TestCase
{
    public function testCompileThrowsWhenMintSlotAppearsOutsideModule(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        TempViews::put($viewsDir, 'bad.php', '<div><mint-slot name="x">Nope</mint-slot></div>');

        $compiler = new MintCompiler($viewsDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only appear as a direct child');
        $compiler->compile($viewsDir . '/bad.php');
    }

    public function testCompileThrowsWhenMintSlotInsideModuleHasNoName(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        TempViews::put($viewsDir, 'mod.php', '<mod-box><mint-slot></mint-slot></mod-box>');

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('box', BoxStubModule::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('non-empty name');
        $compiler->compile($viewsDir . '/mod.php');
    }

    public function testCompileThrowsWhenMintSlotUsesReservedNameBody(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        TempViews::put($viewsDir, 'body-slot.php', '<mod-box><mint-slot name="body">X</mint-slot></mod-box>');

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('box', BoxStubModule::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('name="body" is reserved');
        $compiler->compile($viewsDir . '/body-slot.php');
    }

    public function testCompileThrowsWhenMintSlotIsNotDirectChildOfModule(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        TempViews::put(
            $viewsDir,
            'wrap.php',
            '<mod-box><div><mint-slot name="head">X</mint-slot></div></mod-box>'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('box', BoxStubModule::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only appear as a direct child');
        $compiler->compile($viewsDir . '/wrap.php');
    }

    public function testCompileThrowsOnMissingTemplate(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $compiler = new MintCompiler($viewsDir);

        $this->expectException(\RuntimeException::class);
        $compiler->compile($viewsDir . '/missing.php');
    }
}

final class BoxStubModule extends \Baueri\Mint\Module\Module
{
    public function render(\Baueri\Mint\Context $context): string
    {
        return '';
    }
}

