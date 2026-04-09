<?php

declare(strict_types=1);

namespace Baueri\Mint;

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