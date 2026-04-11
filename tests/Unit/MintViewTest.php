<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Unit;

use Baueri\Mint\Cache;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;
use Baueri\Mint\Tests\Support\TempViews;
use PHPUnit\Framework\TestCase;

final class MintViewTest extends TestCase
{
    public function testRegisterNamespaceResolvesTemplates(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        $pkgDir = TempViews::makeDir('mint_pkg_views');

        TempViews::put($appDir, 'app.php', 'APP');
        TempViews::put($pkgDir, 'hello.php', 'PKG_HELLO');

        $cacheDir = TempViews::makeDir('mint_cache');
        $compiler = new MintCompiler($appDir);
        $view = new MintView($appDir, new Cache($cacheDir), $compiler);
        $view->registerNamespace('pkg', $pkgDir);

        $this->assertStringContainsString('PKG_HELLO', $view->render('pkg::hello.php'));
        $this->assertStringContainsString('APP', $view->render('app.php'));
    }

    public function testDuplicateNamespaceThrows(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        $pkgA = TempViews::makeDir('mint_pkg_a');
        $pkgB = TempViews::makeDir('mint_pkg_b');

        $view = new MintView($appDir, new Cache(TempViews::makeDir('mint_cache')), new MintCompiler($appDir));
        $view->registerNamespace('vendor', $pkgA);

        $this->expectException(\InvalidArgumentException::class);
        $view->registerNamespace('vendor', $pkgB);
    }

    public function testPathTraversalInNamespacedTemplateThrows(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        $pkgDir = TempViews::makeDir('mint_pkg_views');
        TempViews::put($pkgDir, 'safe.php', 'ok');

        $view = new MintView($appDir, new Cache(TempViews::makeDir('mint_cache')), new MintCompiler($appDir));
        $view->registerNamespace('pkg', $pkgDir);

        $this->expectException(\RuntimeException::class);
        $view->render('pkg::../safe.php');
    }

    public function testInvalidNamespaceSyntaxThrows(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        TempViews::put($appDir, 'x.php', 'x');

        $view = new MintView($appDir, new Cache(TempViews::makeDir('mint_cache')), new MintCompiler($appDir));

        $this->expectException(\RuntimeException::class);
        $view->render('a::b::c.php');
    }

    public function testSharedVariablesAvailableInEveryRender(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        TempViews::put($appDir, 'greet.php', '<p>{{ $siteName }}</p>');

        $view = new MintView($appDir, new Cache(TempViews::makeDir('mint_cache')), new MintCompiler($appDir));
        $view->share('siteName', 'Mint');

        $this->assertStringContainsString('Mint', $view->render('greet.php'));
    }

    public function testRenderDataOverridesSharedVariables(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        TempViews::put($appDir, 'x.php', '<p>{{ $n }}</p>');

        $view = new MintView($appDir, new Cache(TempViews::makeDir('mint_cache')), new MintCompiler($appDir));
        $view->share('n', 'global');

        $this->assertStringContainsString('local', $view->render('x.php', ['n' => 'local']));
    }

    public function testShareBulkArray(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        TempViews::put($appDir, 'y.php', '<p>{{ $a }}-{{ $b }}</p>');

        $view = new MintView($appDir, new Cache(TempViews::makeDir('mint_cache')), new MintCompiler($appDir));
        $view->share(['a' => '1', 'b' => '2']);

        $this->assertStringContainsString('1-2', $view->render('y.php'));
    }

    public function testShareInvalidVariableNameThrows(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        $view = new MintView($appDir, new Cache(TempViews::makeDir('mint_cache')), new MintCompiler($appDir));

        $this->expectException(\InvalidArgumentException::class);
        $view->share('9bad', 'x');
    }

    public function testShareReservedPrefixThrows(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        $view = new MintView($appDir, new Cache(TempViews::makeDir('mint_cache')), new MintCompiler($appDir));

        $this->expectException(\InvalidArgumentException::class);
        $view->share('__mint_data', []);
    }

    public function testMintIncludeInheritsSharedVariables(): void
    {
        $appDir = TempViews::makeDir('mint_app_views');
        TempViews::put($appDir, 'outer.php', '<mint-include path="inner.php" />');
        TempViews::put($appDir, 'inner.php', '<em>{{ $sharedKey }}</em>');

        $view = new MintView($appDir, new Cache(TempViews::makeDir('mint_cache')), new MintCompiler($appDir));
        $view->share('sharedKey', 'INCL');

        $this->assertStringContainsString('<em>INCL</em>', $view->render('outer.php'));
    }
}
