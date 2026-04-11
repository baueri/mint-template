<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Unit\Directive;

use Baueri\Mint\Directive\DOM\CustomComponentDirective;
use Baueri\Mint\Directive\DOM\ViewComponentDirective;
use Baueri\Mint\Directive\DOM\IfDirective;
use Baueri\Mint\Directive\DOM\IncludeDirective;
use Baueri\Mint\Directive\DOM\ExtendDirective;
use Baueri\Mint\Directive\DOM\YieldDirective;
use DOMDocument;
use PHPUnit\Framework\TestCase;

final class DomDirectiveUnitTest extends TestCase
{
    public function testDomIfDirectiveEmitsPhp(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<div x:if="{ $ok }">YES</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $node = $dom->documentElement;

        $d = new IfDirective();
        $this->assertTrue($d->supports($node));

        $this->assertSame('<?php if ( $ok ): ?>', $d->compileOpen($node));
        $this->assertSame('<?php endif; ?>', $d->compileClose($node));
        $this->assertFalse($d->isSelfClosing());
    }

    public function testCompilerWrapsWholeElementForXIf(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/t.php';
        file_put_contents($file, '<h1 x:if="{ $ok }">OK</h1>');

        $compiler = new \Baueri\Mint\MintCompiler($tmp);
        $php = $compiler->compile($file);

        $this->assertStringContainsString('<?php if ( $ok ): ?>', $php);
        $this->assertStringContainsString('<h1>OK</h1>', $php);
        $this->assertStringContainsString('<?php endif; ?>', $php);
    }

    public function testXIfOnCustomComponentRunsComponentDirectiveNotLiteralTag(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/t.php';
        file_put_contents($file, '<mint-alert x:if="{ $ok }" :type="success" />');

        $compiler = new \Baueri\Mint\MintCompiler($tmp);
        $compiler->registerComponent('alert', 'Some\\Alert');
        $php = $compiler->compile($file);

        $this->assertStringContainsString('<?php if ( $ok ): ?>', $php);
        $this->assertStringContainsString('new \\Some\\Alert', $php);
        $this->assertStringNotContainsString('<mint-alert', $php);
        $this->assertStringContainsString('<?php endif; ?>', $php);
    }

    public function testYieldDirectiveReturnsSectionContents(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<mint-yield name="title"></mint-yield>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $node = $dom->documentElement;

        $d = new YieldDirective();
        $this->assertTrue($d->supports($node));
        $this->assertSame("<?php echo \$__mint_env->sections['title'] ?? ''; ?>", $d->compileOpen($node));
        $this->assertTrue($d->isSelfClosing());
    }

    public function testCustomComponentDirectiveSupportsTagName(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<mint-alert></mint-alert>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomComponentDirective('alert', 'Some\\Class', $compiler);
        $this->assertTrue($d->supports($node));
        $this->assertFalse($d->isSelfClosing());
        $this->assertFalse($d->hasSlotBody($node));
        $this->assertStringNotContainsString('ob_start', $d->compileOpen($node));
    }

    public function testCustomComponentDirectiveWithSlotBodyUsesOutputBuffering(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<mint-alert>Hi</mint-alert>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomComponentDirective('alert', 'Some\\Class', $compiler);
        $this->assertFalse($d->isSelfClosing());
        $this->assertTrue($d->hasSlotBody($node));
        $this->assertStringContainsString('ob_start', $d->compileOpen($node));
    }

    public function testViewComponentDirectiveEmitsViewRender(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<mint-link></mint-link>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new ViewComponentDirective('link', 'components/link.php', $compiler);
        $php = $d->compileOpen($node);
        $this->assertStringContainsString('$__mint_props->view()->render(', $php);
        $this->assertStringContainsString('\'components/link.php\'', $php);
        $this->assertStringContainsString('\array_merge($__mint_view->shared(), $__mint_props->all())', $php);
        $this->assertStringNotContainsString('$component = new \\', $php);
    }

    public function testViewComponentDirectiveWithSlotUsesBufferedClose(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<mint-link>X</mint-link>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new ViewComponentDirective('link', 'components/link.php', $compiler);
        $this->assertStringContainsString('ob_start', $d->compileOpen($node));
        $close = $d->compileClose($node);
        $this->assertStringContainsString('ob_get_clean', $close);
        $this->assertStringContainsString('$__mint_props->view()->render(', $close);
    }

    public function testCustomComponentPropsShorthandExpandsToContextEntries(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><mint-book :props="{$bookTitle, $author}" />',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomComponentDirective('book', 'Some\\Book', $compiler);
        $php = $d->compileOpen($node);
        $this->assertStringContainsString("'bookTitle' => \$bookTitle", $php);
        $this->assertStringContainsString("'author' => \$author", $php);
        $this->assertStringNotContainsString("'props' =>", $php);
    }

    public function testCustomComponentPropsShorthandInvalidTokenThrows(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><mint-x :props="{$a + 1}" />',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomComponentDirective('x', 'Some\\X', $compiler);

        $this->expectException(\RuntimeException::class);
        $d->compileOpen($node);
    }

    public function testCustomComponentForwardsHtmlAttributesIntoContextBuild(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><mint-card class="wide" id="c1" />',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomComponentDirective('card', 'Some\\Card', $compiler);
        $php = $d->compileOpen($node);
        $this->assertStringContainsString('$__mint_attributes', $php);
        $this->assertStringContainsString("'attributes' => \$__mint_attributes", $php);
        $this->assertStringContainsString('class', $php);
        $this->assertStringContainsString('wide', $php);
    }

    public function testMintIncludeDirectiveEmitsRenderWithMergedProps(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><mint-include path="partials/card.php" :props="{$a, $a, $c}" :a="{ $override }" />',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $node = $dom->documentElement;

        $d = new IncludeDirective();
        $this->assertTrue($d->supports($node));

        $php = $d->compileOpen($node);
        $this->assertStringContainsString("\$__mint_view->render('partials/card.php'", $php);
        $this->assertStringContainsString("'a' =>", $php);
        $this->assertStringContainsString('$override', $php);
        $this->assertStringContainsString("'c' => \$c", $php);
    }

    public function testMintExtendDirectiveMergesPropsIntoLayoutRender(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><mint-extend path="layout.php" :body-class="{ $c }"></mint-extend>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $ctx = new \Baueri\Mint\RenderContext();
        $d = new ExtendDirective($compiler, $ctx);

        $php = $d->compileClose($node);
        $this->assertStringContainsString("\$__mint_view->render('layout.php'", $php);
        $this->assertStringContainsString("'bodyClass' =>", $php);
        $this->assertStringContainsString('$c', $php);
        $this->assertStringContainsString("'__mint_env' => \$__mint_env", $php);
        $this->assertStringContainsString("'slot' => ob_get_clean()", $php);
        $this->assertStringNotContainsString('sections', $php);
    }
}

