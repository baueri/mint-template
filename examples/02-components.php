<?php

declare(strict_types=1);

use Baueri\Mint\Cache;
use Baueri\Mint\Module\Module;
use Baueri\Mint\Context;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;

require __DIR__ . '/../vendor/autoload.php';

$views = __DIR__ . '/views/components';
$cache = __DIR__ . '/var/cache-components';

final class UserCard extends Module
{
    public function render(Context $context): string
    {
        return $this->view($context, 'components/user-card.php', [
            'user' => $context->resolve('user'),
        ]);
    }
}

final class Alert extends Module
{
    public function render(Context $context): string
    {
        return $this->view($context, 'components/alert.php', [
            'type' => (string) $context->resolve('type', 'info'),
        ]);
    }
}

$compiler = new MintCompiler($views);
$compiler->registerModule('user-card', UserCard::class);
$compiler->registerModule('alert', Alert::class);

$view = new MintView($views, new Cache($cache), $compiler);

echo $view->render('index.php', [
    'user' => [
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ],
]);
