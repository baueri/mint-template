<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Unit;

use Baueri\Mint\MintCompiler;
use PHPUnit\Framework\TestCase;

final class MintCompilerTest extends TestCase
{
    public function testCompilesMustacheEchoInTextNodes(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/t.php';
        file_put_contents($file, '<div>Hello {{ $name }}</div>');

        $compiler = new MintCompiler($tmp);
        $php = $compiler->compile($file);

        $this->assertStringContainsString('<?php echo $name; ?>', $php);
    }

    public function testBareMustacheFragmentIsNotWrappedInParagraphByParser(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/meta.php';
        file_put_contents($file, "{{ og_image('images/logo.png') }}\n");

        $compiler = new MintCompiler($tmp);
        $php = $compiler->compile($file);

        $this->assertStringContainsString("og_image('images/logo.png')", $php);
        $this->assertStringNotContainsString('<p>', $php);
        $this->assertStringNotContainsString('</p>', $php);
    }

    public function testFullHtmlDocumentIsNotWrappedSoDoctypeTreeSurvives(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/layout.php';
        file_put_contents(
            $file,
            "<!DOCTYPE html>\n<html lang=\"en\"><head><title>x</title></head><body>ok</body></html>\n"
        );

        $compiler = new MintCompiler($tmp);
        $php = $compiler->compile($file);

        $this->assertStringContainsString('<html lang="en">', $php);
        $this->assertStringContainsString('</html>', $php);
        $this->assertStringNotContainsString('mint-internal-compile-root', $php);
    }

    public function testVoidElementsHaveNoClosingTag(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/head.php';
        file_put_contents($file, '<meta charset="utf-8" /><link rel="stylesheet" href="/a.css" />');

        $compiler = new MintCompiler($tmp);
        $php = $compiler->compile($file);

        $this->assertStringContainsString('<meta charset="utf-8">', $php);
        $this->assertStringContainsString('<link rel="stylesheet" href="/a.css">', $php);
        $this->assertStringNotContainsString('</meta>', $php);
        $this->assertStringNotContainsString('</link>', $php);
    }

    public function testCompilesMustacheInAttributes(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/t.php';
        file_put_contents($file, '<div data-x="{{ $name }}"></div>');

        $compiler = new MintCompiler($tmp);
        $php = $compiler->compile($file);

        $this->assertStringContainsString('data-x="<?php echo $name; ?>"', $php);
    }

    public function testProtectsPhpBlocksSoTextDirectivesSurviveDomParsing(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/t.php';
        file_put_contents($file, '<div>@if($ok)YES@endif</div>');

        $compiler = new MintCompiler($tmp);
        $php = $compiler->compile($file);

        $this->assertStringContainsString('<?php if($ok): ?>', $php);
        $this->assertStringContainsString('<?php endif; ?>', $php);
    }

    public function testTripleMustacheIsEscaped(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/t.php';
        file_put_contents($file, '<div>{{{ $name }}}</div>');

        $compiler = new MintCompiler($tmp);
        $php = $compiler->compile($file);

        $this->assertStringContainsString('<?php echo e($name); ?>', $php);
    }

    public function testDuplicateRegisterModuleThrows(): void
    {
        $compiler = new MintCompiler(sys_get_temp_dir());
        $compiler->registerModule('chip', 'X\\Chip');

        $this->expectException(\InvalidArgumentException::class);
        $compiler->registerModule('chip', 'X\\Chip2');
    }

    public function testRegisterViewModuleAfterRegisterModuleSameNameThrows(): void
    {
        $compiler = new MintCompiler(sys_get_temp_dir());
        $compiler->registerModule('chip', 'X\\Chip');

        $this->expectException(\InvalidArgumentException::class);
        $compiler->registerViewModule('chip', 'partial.php');
    }

    public function testModuleNameCannotUseTemplateNamespaceSyntax(): void
    {
        $compiler = new MintCompiler(sys_get_temp_dir());

        $this->expectException(\InvalidArgumentException::class);
        $compiler->registerModule('acme::widget', 'X\\Y');
    }

    public function testRegisterDirectiveDuplicateModTagThrows(): void
    {
        $compiler = new MintCompiler(sys_get_temp_dir());
        $compiler->registerDirective(
            new \Baueri\Mint\Directive\DOM\CustomModuleDirective('zap', 'A\\B', $compiler)
        );

        $this->expectException(\InvalidArgumentException::class);
        $compiler->registerDirective(
            new \Baueri\Mint\Directive\DOM\ViewModuleDirective('zap', 'c.php', $compiler)
        );
    }

    public function testUnregisteredModuleTagThrows(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/t.php';
        file_put_contents($file, '<div><mod-ghost /></div>');

        $compiler = new MintCompiler($tmp);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/mod-ghost/');
        $compiler->compile($file);
    }

    public function testUnknownMintDirectiveTagThrows(): void
    {
        $tmp = sys_get_temp_dir() . '/mint_' . bin2hex(random_bytes(8));
        mkdir($tmp);

        $file = $tmp . '/t.php';
        file_put_contents($file, '<div><mint-typo /></div>');

        $compiler = new MintCompiler($tmp);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/mint-typo/');
        $compiler->compile($file);
    }
}

