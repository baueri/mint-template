<?php

declare(strict_types=1);

namespace Mint\View\Directive\DOM;

use DOMElement;
use Mint\View\MintCompiler;
use Mint\View\RenderContext;

class SectionDirective implements DOMDirective
{
    public function __construct(
        private readonly MintCompiler $compiler,
        private readonly RenderContext $context,
    ) {}

    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'x-section';
    }

    public function compileOpen(DOMElement $node): string
    {
        $name = $node->getAttribute('name');
        $compiled = '';

        foreach ($node->childNodes as $child) {
            $compiled .= $this->compiler->compileNode($child);
        }

        $this->context->sections[$name] =
            ($this->context->sections[$name] ?? '') . $compiled;

        return '';
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
