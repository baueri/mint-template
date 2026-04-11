<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Feature;

use Baueri\Mint\Cache;
use Baueri\Mint\Module\Module;
use Baueri\Mint\Context;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;
use Baueri\Mint\Tests\Support\TempViews;
use PHPUnit\Framework\TestCase;

final class EngineRenderTest extends TestCase
{
    public function testUtf8TextInTemplatesIsNotCorruptedByLoadHtml(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        $emDash = "\u{2014}";
        $leftArrow = "\u{2190}";
        TempViews::put($viewsDir, 'unicode.php', '<p>' . $emDash . ' Mint ' . $leftArrow . '</p>');

        $compiler = new MintCompiler($viewsDir);
        $view = new MintView($viewsDir, new Cache($cacheDir), $compiler);

        $out = $view->render('unicode.php');

        $this->assertStringContainsString($emDash, $out);
        $this->assertStringContainsString($leftArrow, $out);
        $this->assertStringNotContainsString("\xC3\xA2", $out, 'should not contain UTF-8 mojibake (e.g. â)');
    }

    public function testRendersTemplateWithData(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'hello.php', '<div>{{ $name }}</div>');

        $compiler = new MintCompiler($viewsDir);
        $view = new MintView($viewsDir, new Cache($cacheDir), $compiler);

        $out = $view->render('hello.php', ['name' => 'Ivan']);
        $this->assertStringContainsString('Ivan', $out);
    }

    public function testCacheIsUsedWhenFresh(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'x.php', '<div>{{ $name }}</div>');

        $compiler = new MintCompiler($viewsDir);
        $cache = new Cache($cacheDir);
        $view = new MintView($viewsDir, $cache, $compiler);

        $out1 = $view->render('x.php', ['name' => 'A']);
        $this->assertStringContainsString('A', $out1);

        // Render again: should not throw and should still work (fresh compiled file)
        $out2 = $view->render('x.php', ['name' => 'B']);
        $this->assertStringContainsString('B', $out2);
    }

    public function testRendersCustomModuleWithSlot(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'c.php', '<mod-alert :type="error">SLOT</mod-alert>');

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('alert', AlertModule::class);

        $view = new MintView($viewsDir, new Cache($cacheDir), $compiler);
        $out = $view->render('c.php');

        $this->assertStringContainsString('error', $out);
        $this->assertStringContainsString('SLOT', $out);
    }

    public function testLayoutChangeIsPickedUpEvenWhenChildIsCached(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'layout.php', '<div>V1 <mint-yield name="portal" /></div>');
        TempViews::put($viewsDir, 'page.php', '<mint-extend path="layout.php"><mint-section name="portal">BODY</mint-section></mint-extend>');

        $compiler = new MintCompiler($viewsDir);
        $view = new MintView($viewsDir, new Cache($cacheDir), $compiler);

        $out1 = $view->render('page.php');
        $this->assertStringContainsString('V1', $out1);

        // Update only the layout and render again without touching the child.
        // With runtime composition, the layout is rendered separately and its cache will refresh.
        sleep(1);
        TempViews::put($viewsDir, 'layout.php', '<div>V2 <mint-yield name="portal" /></div>');

        $out2 = $view->render('page.php');
        $this->assertStringContainsString('V2', $out2);
    }
    
    public function testOnBeforeRenderListenerReceivesExpectedArgumentsAndRunsBeforeTemplate(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'before.php', '<p>{{ $injected }}</p>');

        $compiler = new MintCompiler($viewsDir);
        $view     = new MintView($viewsDir, new Cache($cacheDir), $compiler);

        $calls = [];
        $view->onBeforeRender(function (string $template, string $path, array &$data) use (&$calls): void {
            $calls[] = compact('template', 'path');
            $data['injected'] = 'from-listener';
        });

        $out = $view->render('before.php');

        $this->assertCount(1, $calls);
        $this->assertSame('before.php', $calls[0]['template']);
        $this->assertFileExists($calls[0]['path']);
        $this->assertStringContainsString('from-listener', $out);
    }

    public function testOnBeforeRenderRunsBeforeOnRender(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'order.php', '<i>x</i>');

        $compiler = new MintCompiler($viewsDir);
        $view     = new MintView($viewsDir, new Cache($cacheDir), $compiler);

        $order = [];
        $view->onBeforeRender(function () use (&$order): void { $order[] = 'before'; });
        $view->onRender(function () use (&$order): void { $order[] = 'after'; });

        $view->render('order.php');

        $this->assertSame(['before', 'after'], $order);
    }

    public function testOnRenderListenerReceivesExpectedArguments(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'listen.php', '<p>hello</p>');

        $compiler = new MintCompiler($viewsDir);
        $view     = new MintView($viewsDir, new Cache($cacheDir), $compiler);

        $calls = [];
        $view->onRender(function (string $template, string $compiledPath, float $ms, int $bytes) use (&$calls): void {
            $calls[] = compact('template', 'compiledPath', 'ms', 'bytes');
        });

        $out = $view->render('listen.php');

        $this->assertCount(1, $calls);
        $this->assertSame('listen.php', $calls[0]['template']);
        $this->assertFileExists($calls[0]['compiledPath']);
        $this->assertGreaterThanOrEqual(0.0, $calls[0]['ms']);
        $this->assertSame(strlen($out), $calls[0]['bytes']);
    }

    public function testOnCompileListenerFiresOnlyWhenTemplateIsCompiled(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'compile.php', '<p>world</p>');

        $compiler = new MintCompiler($viewsDir);
        $view     = new MintView($viewsDir, new Cache($cacheDir), $compiler);

        $compileCalls = [];
        $view->onCompile(function (string $template, string $sourcePath, string $compiledPath) use (&$compileCalls): void {
            $compileCalls[] = compact('template', 'sourcePath', 'compiledPath');
        });

        $view->render('compile.php'); // triggers compile
        $view->render('compile.php'); // cache is fresh — should NOT trigger compile again

        $this->assertCount(1, $compileCalls);
        $this->assertSame('compile.php', $compileCalls[0]['template']);
        $this->assertFileExists($compileCalls[0]['sourcePath']);
        $this->assertFileExists($compileCalls[0]['compiledPath']);
    }

    public function testMultipleListenersAllFire(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'multi.php', '<span>x</span>');

        $compiler = new MintCompiler($viewsDir);
        $view     = new MintView($viewsDir, new Cache($cacheDir), $compiler);

        $fired = [];
        $view->onRender(function () use (&$fired): void { $fired[] = 'a'; });
        $view->onRender(function () use (&$fired): void { $fired[] = 'b'; });

        $view->render('multi.php');

        $this->assertSame(['a', 'b'], $fired);
    }

    public function testMultipleBeforeRenderListenersAllFire(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'mbefore.php', '<b>z</b>');

        $compiler = new MintCompiler($viewsDir);
        $view     = new MintView($viewsDir, new Cache($cacheDir), $compiler);

        $fired = [];
        $view->onBeforeRender(function () use (&$fired): void { $fired[] = 'a'; });
        $view->onBeforeRender(function () use (&$fired): void { $fired[] = 'b'; });

        $view->render('mbefore.php');

        $this->assertSame(['a', 'b'], $fired);
    }
}

final class AlertModule extends Module
{
    public function render(Context $context): string
    {
        return (string) $context->resolve('type') . ':' . ($context->slot() ?? '');
    }
}

