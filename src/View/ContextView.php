<?php

declare(strict_types=1);

namespace Mint\View;

class ContextView
{
    public function __construct(
        protected Context $context
    ) {}

    public function render(string $template, array $data = []): string
    {
        return $this->context
            ->baseView()
            ->render(
                $template,
                $this->context->withSlot($data)
            );
    }
}