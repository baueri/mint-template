<?php

declare(strict_types=1);

namespace Baueri\Mint\Module;

use Baueri\Mint\Context;

abstract class Module
{
    abstract public function render(Context $context): string;

    protected function view(Context $context, string $template, array $data = []): string
    {
        // Make module forwarded HTML attributes available in template-backed modules
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
