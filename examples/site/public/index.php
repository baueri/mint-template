<?php

declare(strict_types=1);

use Baueri\Mint\Cache;
use Baueri\Mint\Module\Module;
use Baueri\Mint\Context;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}

final class Alert extends Module
{
    public function render(Context $context): string
    {
        return $context->view()->render('components/alert.php', $context->all());
    }
}

final class StatTile extends Module
{
    public function render(Context $context): string
    {
        $label = htmlspecialchars((string) $context->resolve('label', ''), ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars((string) $context->resolve('value', ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <div class="stat-tile">
                <div class="stat-tile__value">{$value}</div>
                <div class="stat-tile__label">{$label}</div>
            </div>
            HTML;
    }
}

final class FeatureCallout extends Module
{
    public function render(Context $context): string
    {
        return $this->view($context, 'components/feature-callout.php', $context->all());
    }
}

$views = dirname(__DIR__) . '/views';
$cachePath = dirname(__DIR__) . '/var/cache';

$compiler = new MintCompiler($views);
$compiler->registerModule('alert', Alert::class);
$compiler->registerModule('stat-tile', StatTile::class);
$compiler->registerModule('feature-callout', FeatureCallout::class);

$view = new MintView($views, new Cache($cachePath), $compiler);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

if ($path === '/features') {
    echo $view->render('pages/features.php', [
        'title' => 'Features',
        'nav_active' => 'features',
    ]);

    return;
}

echo $view->render('pages/home.php', [
    'title' => 'Showcase',
    'nav_active' => 'home',
    'preview' => true,
    'stat_repos' => 128,
    'stat_stars' => '4.2k',
    'stat_uptime' => '99.97%',
    'team' => [
        ['name' => 'Avery Chen', 'role' => 'Compiler', 'status' => 'online'],
        ['name' => 'Jordan Blake', 'role' => 'Runtime', 'status' => 'busy'],
        ['name' => 'Riley Moss', 'role' => 'Cache', 'status' => 'online'],
    ],
]);
