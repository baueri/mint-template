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
}
