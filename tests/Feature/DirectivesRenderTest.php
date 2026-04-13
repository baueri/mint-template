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

final class DirectivesRenderTest extends TestCase
{
    private function render(MintCompiler $compiler, string $viewsDir, string $cacheDir, string $template, array $data = []): string
    {
        $cache = new Cache($cacheDir);
        $view = new MintView($viewsDir, $cache, $compiler);
        return $view->render($template, $data);
    }

    public function testDomIfDirectiveRendersConditionally(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'if.php',
            '<div><span x:if="{ $ok }">YES</span><span x:if="{ ! $ok }">NO</span></div>'
        );

        $compiler = new MintCompiler($viewsDir);

        $outTrue = $this->render($compiler, $viewsDir, $cacheDir, 'if.php', ['ok' => true]);
        $this->assertStringContainsString('YES', $outTrue);
        $this->assertStringNotContainsString('NO', $outTrue);

        $cacheDir2 = TempViews::makeDir('mint_cache');
        $outFalse = $this->render($compiler, $viewsDir, $cacheDir2, 'if.php', ['ok' => false]);
        $this->assertStringContainsString('NO', $outFalse);
        $this->assertStringNotContainsString('YES', $outFalse);
    }

    public function testDomForeachDirectiveRepeatsNode(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'foreach.php',
            '<ul><li x:foreach="{ $items as $i }">{{ $i }}</li></ul>'
        );

        $compiler = new MintCompiler($viewsDir);
        $out = $this->render($compiler, $viewsDir, $cacheDir, 'foreach.php', ['items' => ['A', 'B', 'C']]);

        $this->assertStringContainsString('A', $out);
        $this->assertStringContainsString('B', $out);
        $this->assertStringContainsString('C', $out);
    }

    public function testDomRepeatDirectiveCountsFromZero(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'repeat.php',
            '<ul><li x:repeat="{ $count as $i }">{{ $i }}</li></ul>'
        );

        $compiler = new MintCompiler($viewsDir);
        $out = $this->render($compiler, $viewsDir, $cacheDir, 'repeat.php', ['count' => 3]);

        $this->assertStringContainsString('0', $out);
        $this->assertStringContainsString('1', $out);
        $this->assertStringContainsString('2', $out);
        $this->assertStringNotContainsString('3', $out);
    }

    public function testDomRepeatDirectiveLiteralCount(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'repeat-lit.php',
            '<ul><li x:repeat="{ 2 as $i }">x</li></ul>'
        );

        $compiler = new MintCompiler($viewsDir);
        $out = $this->render($compiler, $viewsDir, $cacheDir, 'repeat-lit.php');

        $this->assertSame(2, substr_count($out, 'x'));
    }

    public function testSectionAndYieldDirective(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'sections.php',
            '<div><mint-section name="title">Hello</mint-section><h1><mint-yield name="title"/></h1></div>'
        );

        $compiler = new MintCompiler($viewsDir);
        $out = $this->render($compiler, $viewsDir, $cacheDir, 'sections.php');

        $this->assertStringContainsString('<h1>', $out);
        $this->assertStringContainsString('Hello', $out);
    }

    public function testExtendDirectiveRendersLayoutWithSlot(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'layout.php', '<div class="layout">{{ $slot }}</div>');
        TempViews::put($viewsDir, 'page.php', '<mint-extend path="layout.php"><span>BODY</span></mint-extend>');

        $compiler = new MintCompiler($viewsDir);
        $out = $this->render($compiler, $viewsDir, $cacheDir, 'page.php');

        $this->assertStringContainsString('class="layout"', $out);
        $this->assertStringContainsString('BODY', $out);
    }

    public function testRegisteredModuleRendersWithProps(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'hello.php',
            '<mod-hello :name="{ $name }" />'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('hello', HelloModule::class);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'hello.php', ['name' => 'Ivan']);

        $this->assertStringContainsString('Hello Ivan', $out);
    }

    public function testCustomModuleDirectiveRendersSlotAndProps(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'custom.php', '<mod-greet :name="Ivan">SLOT</mod-greet>');

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('greet', GreetModule::class);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'custom.php');

        $this->assertStringContainsString('Ivan', $out);
        $this->assertStringContainsString('SLOT', $out);
    }

    public function testPropsShorthandListsVariablesAsContextKeys(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'book.php',
            '<mod-book :props="{$bookTitle, $author, $isbn}" />'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('book', BookSpreadModule::class);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'book.php', [
            'bookTitle' => 'Mint',
            'author' => 'Ada',
            'isbn' => '123',
        ]);

        $this->assertSame('Mint|Ada|123', trim($out));
    }

    public function testExplicitPropsOverridePropsShorthand(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'pair.php',
            '<mod-pair :props="{$a, $b}" :b="{ $override }" />'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('pair', PairSpreadModule::class);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'pair.php', [
            'a' => '1',
            'b' => '2',
            'override' => '9',
        ]);

        $this->assertSame('1|9', trim($out));
    }

    public function testForwardedHtmlAttributesAvailableAsStringInView(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'components/book-shell.php', '<div{{ $attributes }}>inside</div>');
        TempViews::put($viewsDir, 'page-book.php', '<mod-book-shell class="book" data-kind="paper" />');

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('book-shell', BookShellModule::class);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'page-book.php');

        $this->assertStringContainsString('<div class="book" data-kind="paper">', $out);
        $this->assertStringContainsString('inside', $out);
    }

    public function testMintIncludeRendersPartialWithPropsAndExplicitOverride(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'partials/card.php', '<span>{{ $a }}-{{ $c }}</span>');
        TempViews::put(
            $viewsDir,
            'include.php',
            '<div><mint-include path="partials/card.php" :props="{$a, $a, $c}" :a="{ $override }" /></div>'
        );

        $compiler = new MintCompiler($viewsDir);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'include.php', [
            'a' => 1,
            'c' => 3,
            'override' => 9,
        ]);

        $this->assertStringContainsString('<span>9-3</span>', $out);
    }

    public function testTextIfDirectiveSurvivesDomParsingAndControlsOutput(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'text_if.php', '<div>@if($ok) YES @else NO @endif</div>');

        $compiler = new MintCompiler($viewsDir);

        $outTrue = $this->render($compiler, $viewsDir, $cacheDir, 'text_if.php', ['ok' => true]);
        $this->assertStringContainsString('YES', $outTrue);
        $this->assertStringNotContainsString('NO', $outTrue);

        $cacheDir2 = TempViews::makeDir('mint_cache');
        $outFalse = $this->render($compiler, $viewsDir, $cacheDir2, 'text_if.php', ['ok' => false]);
        $this->assertStringContainsString('NO', $outFalse);
        $this->assertStringNotContainsString('YES', $outFalse);
    }

    public function testTextForeachDirectiveSurvivesDomParsing(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'text_foreach.php',
            '<ul>@foreach($items as $i)<li>{{ $i }}</li>@endforeach</ul>'
        );

        $compiler = new MintCompiler($viewsDir);
        $out = $this->render($compiler, $viewsDir, $cacheDir, 'text_foreach.php', ['items' => ['X', 'Y']]);

        $this->assertStringContainsString('X', $out);
        $this->assertStringContainsString('Y', $out);
    }

    public function testMintExtendPassesPropsIntoLayoutRender(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'layout.php',
            '<html><body class="{{{ $bodyClass ?? \'\' }}}"><main>{{ $slot }}</main></body></html>'
        );

        TempViews::put(
            $viewsDir,
            'page.php',
            '<mint-extend path="layout.php" :body-class="{ $wrapClass }"><p>Hi</p></mint-extend>'
        );

        $compiler = new MintCompiler($viewsDir);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'page.php', [
            'wrapClass' => 'page',
            'bodyClass' => 'from-data',
        ]);

        $this->assertStringContainsString('<body class="page">', $out);
        $this->assertStringContainsString('<p>Hi</p>', $out);
    }

    public function testViewModuleReceivesNamedSlotsFromMintSlot(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'components/panel.php',
            '<article class="panel"><header>{{ $slot->head }}</header><div class="body">{{ $slot }}</div></article>'
        );
        TempViews::put(
            $viewsDir,
            'panel-page.php',
            '<mod-panel><mint-slot name="head"><strong>Title</strong></mint-slot><p>Main</p></mod-panel>'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerViewModule('panel', 'components/panel.php');

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'panel-page.php');

        $this->assertStringContainsString('<strong>Title</strong>', $out);
        $this->assertStringContainsString('<p>Main</p>', $out);
        $this->assertStringContainsString('<header>', $out);
    }

    public function testNamedSlotsWithSameNameConcatenate(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'components/twice.php', '<div>{{ $slot->a }}</div>');
        TempViews::put(
            $viewsDir,
            'twice-page.php',
            '<mod-twice><mint-slot name="a">1</mint-slot><mint-slot name="a">2</mint-slot></mod-twice>'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerViewModule('twice', 'components/twice.php');

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'twice-page.php');
        $this->assertStringContainsString('12', $out);
    }

    public function testPhpModuleUsesSlotNamed(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'slotnamed-page.php',
            '<mod-slotnamed><mint-slot name="head">H</mint-slot>B</mod-slotnamed>'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerModule('slotnamed', SlotNamedModule::class);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'slotnamed-page.php');
        $this->assertSame('[H|B]', trim($out));
    }

    public function testViewModuleRendersTemplateWithPropsAndSlot(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'components/badge.php',
            '<span class="badge">{{ $label }}: {{ $slot }}</span>'
        );
        TempViews::put(
            $viewsDir,
            'vc-page.php',
            '<mod-badge :label="{ $l }">inner</mod-badge>'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerViewModule('badge', 'components/badge.php');

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'vc-page.php', ['l' => 'OK']);

        $this->assertStringContainsString('OK', $out);
        $this->assertStringContainsString('inner', $out);
    }

    public function testViewModuleTemplateSeesSharedVariables(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'components/site-chip.php',
            '<span>{{ $brand }}</span>'
        );
        TempViews::put(
            $viewsDir,
            'vc-shared-page.php',
            '<mod-site-chip />'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerViewModule('site-chip', 'components/site-chip.php');

        $cache = new Cache($cacheDir);
        $view = new MintView($viewsDir, $cache, $compiler);
        $view->share('brand', 'Acme');

        $out = $view->render('vc-shared-page.php');

        $this->assertStringContainsString('Acme', $out);
    }

    public function testRenderNamespacedIncludeViaMintView(): void
    {
        $appDir = TempViews::makeDir('mint_app');
        $extraDir = TempViews::makeDir('mint_extra');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($appDir, 'page.php', '<mint-include path="extra::frag.php" />');
        TempViews::put($extraDir, 'frag.php', '<em>EXTRA</em>');

        $compiler = new MintCompiler($appDir);
        $cache = new Cache($cacheDir);
        $view = new MintView($appDir, $cache, $compiler);
        $view->registerNamespace('extra', $extraDir);

        $out = $view->render('page.php');

        $this->assertStringContainsString('EXTRA', $out);
    }
}

final class HelloModule extends Module
{
    public function render(Context $context): string
    {
        return 'Hello ' . $context->resolve('name');
    }
}

final class GreetModule extends Module
{
    public function render(Context $context): string
    {
        $name = $context->resolve('name', 'unknown');
        $slot = (string) ($context->slot() ?? '');
        return "Hi {$name} {$slot}";
    }
}

final class BookSpreadModule extends Module
{
    public function render(Context $context): string
    {
        return (string) $context->resolve('bookTitle')
            . '|' . (string) $context->resolve('author')
            . '|' . (string) $context->resolve('isbn');
    }
}

final class PairSpreadModule extends Module
{
    public function render(Context $context): string
    {
        return (string) $context->resolve('a') . '|' . (string) $context->resolve('b');
    }
}

final class BookShellModule extends Module
{
    public function render(Context $context): string
    {
        return $this->view($context, 'components/book-shell.php', $context->all());
    }
}

final class SlotNamedModule extends Module
{
    public function render(Context $context): string
    {
        $s = $context->slot();

        return '[' . ($s !== null ? $s->head : '') . '|' . (string) ($s ?? '') . ']';
    }
}
