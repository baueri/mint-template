<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use DOMElement;
use Baueri\Mint\MintCompiler;

class ForeachDirective implements DOMDirective
{
    public function __construct(
        protected readonly MintCompiler $compiler
    ) {}

    public function supports(DOMElement $node): bool
    {
        return $node->hasAttribute('x:foreach');
    }

    public function compileOpen(DOMElement $node): string
    {
        preg_match('/^\{(.+?)\}$/', $node->getAttribute('x:foreach'), $m);
        $expr = $m[1];

        // klónozd a node-ot x:foreach nélkül
        $clone = $node->cloneNode(true);
        $clone->removeAttribute('x:foreach');

        $inner = $this->compiler->compileNode($clone);

        return "<?php foreach ({$expr}): ?>{$inner}";
    }

    public function compileClose(DOMElement $node): string
    {
        return "<?php endforeach; ?>";
    }

    public function isSelfClosing(): bool
    {
        return true;
    }
}
