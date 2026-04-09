<?php

declare(strict_types=1);

namespace Baueri\Mint\Component;

use Baueri\Mint\Context;

abstract class Component
{
    abstract public function render(Context $context): string;
    
    protected function view(Context $context, string $template, array $data = []): string
    {
        // Make component forwarded HTML attributes available in template-backed components
        // without having to pass the whole context. Explicit $data overrides this.
        if (!array_key_exists('attributes', $data)) {
            $data['attributes'] = (string) $context->resolve('attributes', '');
        }

        return $context->view()->render(
            $template,
            $context->withSlot($data)
        );
    }
}
