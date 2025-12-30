<?php

namespace Mint\View\Directive\DOM;

use DOMElement;
use Mint\View\MintCompiler;
use Mint\View\RenderContext;
use RuntimeException;

class WrapDirective implements DOMDirective
{
    public function __construct(
        private readonly MintCompiler $compiler,
        private readonly RenderContext $context
    ) {}

    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'x-wrap';
    }

    public function compileOpen(DOMElement $node): string
    {
        $view = $node->getAttribute('view');

        if (!$view) {
            throw new RuntimeException('x-wrap requires a view attribute');
        }

        // 🔥 IMPORTANT: compile inner content, not saveHTML
        $compiledBody = '';
        foreach ($node->childNodes as $child) {
            $compiledBody .= $this->compiler->compileNode($child);
        }

        // Store compiled body
        $this->context->sections['portal'] = $compiledBody;

        // Compile wrapper view
        return $this->compiler->compileView($view);
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
