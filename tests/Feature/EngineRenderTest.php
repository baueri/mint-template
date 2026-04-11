<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Feature;

use Baueri\Mint\Cache;
use Baueri\Mint\Component\Component;
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

    public function testRendersCustomComponentWithSlot(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'c.php', '<mint-alert :type="error">SLOT</mint-alert>');

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerComponent('alert', AlertComponent::class);

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
        TempViews::put($viewsDir, 'page.php', '<mint-wrap path="layout.php"><mint-section name="portal">BODY</mint-section></mint-wrap>');

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
}

final class AlertComponent extends Component
{
    public function render(Context $context): string
    {
        return (string) $context->resolve('type') . ':' . ($context->slot() ?? '');
    }
}

