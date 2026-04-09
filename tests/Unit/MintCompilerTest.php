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
}

