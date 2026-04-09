<?php

declare(strict_types=1);

use Baueri\Mint\Cache;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;

require __DIR__ . '/../vendor/autoload.php';

$views = __DIR__ . '/views/layout';
$cache = __DIR__ . '/var/cache-layout';

$compiler = new MintCompiler($views);
$view = new MintView($views, new Cache($cache), $compiler);

echo $view->render('page.php', [
    'title' => 'Mint layout example',
    'items' => ['One', 'Two', 'Three'],
    'ok' => true,
]);

