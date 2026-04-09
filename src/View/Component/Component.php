<?php

declare(strict_types=1);

namespace Baueri\Mint\Component;

use Baueri\Mint\Context;

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
