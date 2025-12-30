<?php

declare(strict_types=1);

namespace Mint\View\Directive\DOM;

use DOMElement;
use Mint\View\RenderContext;

class YieldDirective implements DOMDirective
{
    public function __construct(
        private readonly RenderContext $context
    ) {
    }

    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'x-yield';
    }

    public function compileOpen(DOMElement $node): string
    {
        $name = $node->getAttribute('name');

        return $this->context->sections[$name] ?? '';
    }

    public function compileClose(DOMElement $node): string
    {
        return '';
    }

    public function isSelfClosing(): bool
    {
        return true;
    }
}
