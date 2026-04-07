<?php

declare(strict_types=1);

namespace Mint\View\Component;

use Mint\View\Context;

abstract class Component
{
    abstract public function render(Context $context): string;
    
    protected function view(Context $context, string $template, array $data = []): string
    {
        return $context->view()->render(
            $template,
            $context->withSlot($data)
        );
    }
}
