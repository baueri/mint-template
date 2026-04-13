<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Unit\Directive;

use Baueri\Mint\Directive\DOM\CustomModuleDirective;
use Baueri\Mint\Directive\DOM\ViewModuleDirective;
use Baueri\Mint\Directive\DOM\IfDirective;
use Baueri\Mint\Directive\DOM\IncludeDirective;
use Baueri\Mint\Directive\DOM\ExtendDirective;
use Baueri\Mint\Directive\DOM\MintSlotDirective;
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

    public function testXIfOnCustomModuleRunsModuleDirectiveNotLiteralTag(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/t.php';
        file_put_contents($file, '<mod-alert x:if="{ $ok }" :type="success" />');

        $compiler = new \Baueri\Mint\MintCompiler($tmp);
        $compiler->registerModule('alert', 'Some\\Alert');
        $php = $compiler->compile($file);

        $this->assertStringContainsString('<?php if ( $ok ): ?>', $php);
        $this->assertStringContainsString('new \\Some\\Alert', $php);
        $this->assertStringNotContainsString('<mod-alert', $php);
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

    public function testCustomModuleDirectiveSupportsTagName(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<mod-alert></mod-alert>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomModuleDirective('alert', 'Some\\Class', $compiler);
        $this->assertTrue($d->supports($node));
        $this->assertFalse($d->isSelfClosing());
        $this->assertFalse($d->hasSlotBody($node));
        $this->assertStringNotContainsString('ob_start', $d->compileOpen($node));
    }

    public function testCustomModuleDirectiveWithSlotBodyUsesOutputBuffering(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<mod-alert>Hi</mod-alert>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomModuleDirective('alert', 'Some\\Class', $compiler);
        $this->assertFalse($d->isSelfClosing());
        $this->assertTrue($d->hasSlotBody($node));
        $this->assertSame('', $d->compileOpen($node));

        $tmp = sys_get_temp_dir() . '/mint_modslot_' . bin2hex(random_bytes(8));
        mkdir($tmp);
        file_put_contents($tmp . '/m.php', '<mod-alert>Hi</mod-alert>');
        $compiler->registerModule('alert', 'Some\\Class');
        $php = $compiler->compile($tmp . '/m.php');
        $this->assertStringContainsString('$__mint_slot_stack', $php);
        $this->assertStringContainsString('ob_start', $php);
        $this->assertStringContainsString('array_pop($__mint_slot_stack)', $php);
    }

    public function testViewModuleDirectiveEmitsViewRender(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<mod-link></mod-link>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new ViewModuleDirective('link', 'components/link.php', $compiler);
        $php = $d->compileOpen($node);
        $this->assertStringContainsString('$__mint_props->view()->render(', $php);
        $this->assertStringContainsString('\'components/link.php\'', $php);
        $this->assertStringContainsString('\array_merge($__mint_view->shared(), $__mint_props->all())', $php);
        $this->assertStringNotContainsString('$module = new \\', $php);
    }

    public function testViewModuleDirectiveWithSlotUsesBufferedClose(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<mod-link>X</mod-link>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new ViewModuleDirective('link', 'components/link.php', $compiler);
        $this->assertSame('', $d->compileOpen($node));
        $close = $d->compileClose($node);
        $this->assertStringContainsString('array_pop($__mint_slot_stack)', $close);
        $this->assertStringContainsString('$__mint_props->view()->render(', $close);

        $tmp = sys_get_temp_dir() . '/mint_vmslot_' . bin2hex(random_bytes(8));
        mkdir($tmp);
        file_put_contents($tmp . '/v.php', '<mod-link>X</mod-link>');
        $compiler2 = new \Baueri\Mint\MintCompiler($tmp);
        $compiler2->registerViewModule('link', 'components/link.php');
        $compiled = $compiler2->compile($tmp . '/v.php');
        $this->assertStringContainsString('$__mint_slot_stack', $compiled);
        $this->assertStringContainsString('ob_start', $compiled);
    }

    public function testCustomModulePropsShorthandExpandsToContextEntries(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><mod-book :props="{$bookTitle, $author}" />',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomModuleDirective('book', 'Some\\Book', $compiler);
        $php = $d->compileOpen($node);
        $this->assertStringContainsString("'bookTitle' => \$bookTitle", $php);
        $this->assertStringContainsString("'author' => \$author", $php);
        $this->assertStringNotContainsString("'props' =>", $php);
    }

    public function testCustomModulePropsShorthandInvalidTokenThrows(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><mod-x :props="{$a + 1}" />',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomModuleDirective('x', 'Some\\X', $compiler);

        $this->expectException(\RuntimeException::class);
        $d->compileOpen($node);
    }

    public function testCustomModuleForwardsHtmlAttributesIntoContextBuild(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><mod-card class="wide" id="c1" />',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $node = $dom->documentElement;

        $compiler = new \Baueri\Mint\MintCompiler(sys_get_temp_dir());
        $d = new CustomModuleDirective('card', 'Some\\Card', $compiler);
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

    public function testMintSlotDirectiveThrowsWhenNotInsideModuleBody(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><mint-slot name="head"></mint-slot>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $node = $dom->documentElement;

        $d = new MintSlotDirective();
        $this->assertTrue($d->supports($node));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only appear as a direct child');
        $d->compileOpen($node);
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

        $d = new ExtendDirective();

        $php = $d->compileClose($node);
        $this->assertStringContainsString("\$__mint_view->render('layout.php'", $php);
        $this->assertStringContainsString("'bodyClass' =>", $php);
        $this->assertStringContainsString('$c', $php);
        $this->assertStringContainsString("'__mint_env' => \$__mint_env", $php);
        $this->assertStringContainsString("'slot' => ob_get_clean()", $php);
        $this->assertStringNotContainsString('sections', $php);
    }
}

