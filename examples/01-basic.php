<?php

declare(strict_types=1);

use Baueri\Mint\Cache;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;

require __DIR__ . '/../vendor/autoload.php';

$views = __DIR__ . '/views/basic';
$cache = __DIR__ . '/var/cache-basic';

$view = new MintView(
    viewsPath: $views,
    cache: new Cache($cache),
    compiler: new MintCompiler($views),
);

echo $view->render('index.php', [
    'name' => '<Alice & Co>',
    'rawHtml' => '<strong>raw</strong>',
    'count' => 3,
    'isLoggedIn' => true,
    'userName' => 'Ivan',
    'products' => [
        ['name' => 'Notebook', 'price' => 12],
        ['name' => 'Pencil', 'price' => 2],
        ['name' => 'Backpack', 'price' => 39],
    ],
]);

