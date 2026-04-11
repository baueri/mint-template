<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Unit;

use Baueri\Mint\Cache;
use Baueri\Mint\MintCli;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\Tests\Support\TempViews;
use PHPUnit\Framework\TestCase;

final class MintCliTest extends TestCase
{
    public function testClearCallsCacheClearAndOutputsMessage(): void
    {
        $lines = [];
        $cli = new MintCli(function (string $line) use (&$lines): void { $lines[] = $line; });

        $cacheDir = TempViews::makeDir('mint_cache');
        $cache = new Cache($cacheDir);
        $cache->write('page.php', '<?php // compiled ?>', null);
        $compiled = $cache->compiledPath('page.php');
        $this->assertFileExists($compiled);

        $cli->clear($cache);

        $this->assertFileDoesNotExist($compiled);
        $this->assertStringContainsString('cleared', implode(' ', $lines));
    }

    public function testCompileAllCompilesEveryTemplateUnderViewsPath(): void
    {
        $lines = [];
        $cli = new MintCli(function (string $line) use (&$lines): void { $lines[] = $line; });

        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');
        TempViews::put($viewsDir, 'index.php', '<h1>{{ $title }}</h1>');
        TempViews::put($viewsDir, 'partials/card.php', '<div>{{ $name }}</div>');

        $cache = new Cache($cacheDir);
        $cli->compileAll(new MintCompiler($viewsDir), $viewsDir, $cache);

        $this->assertFileExists($cache->compiledPath('index.php'));
        $this->assertFileExists($cache->compiledPath('partials/card.php'));

        $output = implode("\n", $lines);
        $this->assertStringContainsString('index.php', $output);
        $this->assertStringContainsString('partials/card.php', $output);
        $this->assertStringContainsString('2 template(s)', $output);
    }

    public function testCompileAllReportsErrorWithoutThrowingForBadTemplate(): void
    {
        $lines = [];
        $cli = new MintCli(function (string $line) use (&$lines): void { $lines[] = $line; });

        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');
        TempViews::put($viewsDir, 'bad.php', '<mint-extend>content</mint-extend>');

        $cli->compileAll(new MintCompiler($viewsDir), $viewsDir, new Cache($cacheDir));

        $this->assertStringContainsString('Error', implode("\n", $lines));
    }

    public function testOutputCallableReceivesAllLines(): void
    {
        $lines = [];
        $cli = new MintCli(function (string $line) use (&$lines): void { $lines[] = $line; });

        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');
        TempViews::put($viewsDir, 'a.php', '<p>A</p>');
        TempViews::put($viewsDir, 'b.php', '<p>B</p>');

        $cli->compileAll(new MintCompiler($viewsDir), $viewsDir, new Cache($cacheDir));

        $this->assertGreaterThanOrEqual(3, count($lines)); // 2 compiled + 1 summary
    }

    public function testDefaultOutputWritesToStdout(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');
        TempViews::put($viewsDir, 'x.php', '<p>X</p>');

        $cli = new MintCli();

        ob_start();
        $cli->compileAll(new MintCompiler($viewsDir), $viewsDir, new Cache($cacheDir));
        $output = ob_get_clean();

        $this->assertStringContainsString('x.php', $output);
        $this->assertStringContainsString('Done.', $output);
    }

    public function testMintViewExposesPublicReadonlyProperties(): void
    {
        $viewsDir = TempViews::makeDir('mint_views');
        $cacheDir = TempViews::makeDir('mint_cache');

        $cache    = new Cache($cacheDir);
        $compiler = new MintCompiler($viewsDir);
        $view     = new \Baueri\Mint\MintView($viewsDir, $cache, $compiler);

        $this->assertSame($viewsDir, $view->viewsPath);
        $this->assertSame($cache,    $view->cache);
        $this->assertSame($compiler, $view->compiler);
    }
}
