<?php

declare(strict_types=1);

use Baueri\Mint\Cache;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;

require __DIR__ . '/../vendor/autoload.php';

$views = __DIR__ . '/views/named-slots';
$cache = __DIR__ . '/var/cache-named-slots';

$compiler = new MintCompiler($views);
$compiler->registerViewModule('card', 'components/card.php');

$view = new MintView($views, new Cache($cache), $compiler);

echo $view->render('demo.php', []);
