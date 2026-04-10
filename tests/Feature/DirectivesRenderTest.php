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

    public function testWrapDirectiveRendersLayoutWithPortalSection(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'layout.php', '<div class="layout"><mint-yield name="layout"/></div>');
        TempViews::put($viewsDir, 'page.php', '<mint-wrap view="layout"><span>BODY</span></mint-wrap>');

        $compiler = new MintCompiler($viewsDir);
        $out = $this->render($compiler, $viewsDir, $cacheDir, 'page.php');

        $this->assertStringContainsString('class="layout"', $out);
        $this->assertStringContainsString('BODY', $out);
    }

    public function testRegisteredComponentRendersWithProps(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'hello.php',
            '<mint-hello :name="{ $name }" />'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerComponent('hello', HelloComponent::class);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'hello.php', ['name' => 'Ivan']);

        $this->assertStringContainsString('Hello Ivan', $out);
    }

    public function testCustomComponentDirectiveRendersSlotAndProps(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($viewsDir, 'custom.php', '<mint-greet :name="Ivan">SLOT</mint-greet>');

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerComponent('greet', GreetComponent::class);

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
            '<mint-book :props="{$bookTitle, $author, $isbn}" />'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerComponent('book', BookSpreadComponent::class);

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
            '<mint-pair :props="{$a, $b}" :b="{ $override }" />'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerComponent('pair', PairSpreadComponent::class);

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
        TempViews::put($viewsDir, 'page-book.php', '<mint-book-shell class="book" data-kind="paper" />');

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerComponent('book-shell', BookShellComponent::class);

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
            '<div><mint-include name="partials/card.php" :props="{$a, $a, $c}" :a="{ $override }" /></div>'
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

    public function testMintWrapPassesPropsIntoLayoutRender(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put(
            $viewsDir,
            'layout.php',
            '<html><body class="{{{ $bodyClass ?? \'\' }}}"><main><mint-yield name="layout"/></main></body></html>'
        );

        TempViews::put(
            $viewsDir,
            'page.php',
            '<mint-wrap view="layout" :body-class="{ $wrapClass }"><p>Hi</p></mint-wrap>'
        );

        $compiler = new MintCompiler($viewsDir);

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'page.php', [
            'wrapClass' => 'page',
            'bodyClass' => 'from-data',
        ]);

        $this->assertStringContainsString('<body class="page">', $out);
        $this->assertStringContainsString('<p>Hi</p>', $out);
    }

    public function testViewComponentRendersTemplateWithPropsAndSlot(): void
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
            '<mint-badge :label="{ $l }">inner</mint-badge>'
        );

        $compiler = new MintCompiler($viewsDir);
        $compiler->registerViewComponent('badge', 'components/badge.php');

        $out = $this->render($compiler, $viewsDir, $cacheDir, 'vc-page.php', ['l' => 'OK']);

        $this->assertStringContainsString('OK', $out);
        $this->assertStringContainsString('inner', $out);
    }

    public function testRenderNamespacedIncludeViaMintView(): void
    {
        $appDir = TempViews::makeDir('mint_app');
        $extraDir = TempViews::makeDir('mint_extra');
        $cacheDir = TempViews::makeDir('mint_cache');

        TempViews::put($appDir, 'page.php', '<mint-include name="extra::frag.php" />');
        TempViews::put($extraDir, 'frag.php', '<em>EXTRA</em>');

        $compiler = new MintCompiler($appDir);
        $cache = new Cache($cacheDir);
        $view = new MintView($appDir, $cache, $compiler);
        $view->registerNamespace('extra', $extraDir);

        $out = $view->render('page.php');

        $this->assertStringContainsString('EXTRA', $out);
    }
}

final class HelloComponent extends Component
{
    public function render(Context $context): string
    {
        return 'Hello ' . $context->resolve('name');
    }
}

final class GreetComponent extends Component
{
    public function render(Context $context): string
    {
        $name = $context->resolve('name', 'unknown');
        $slot = $context->slot() ?? '';
        return "Hi {$name} {$slot}";
    }
}

final class BookSpreadComponent extends Component
{
    public function render(Context $context): string
    {
        return (string) $context->resolve('bookTitle')
            . '|' . (string) $context->resolve('author')
            . '|' . (string) $context->resolve('isbn');
    }
}

final class PairSpreadComponent extends Component
{
    public function render(Context $context): string
    {
        return (string) $context->resolve('a') . '|' . (string) $context->resolve('b');
    }
}

final class BookShellComponent extends Component
{
    public function render(Context $context): string
    {
        return $this->view($context, 'components/book-shell.php', $context->all());
    }
}

