<?php

declare(strict_types=1);

namespace Mint\View\Directive\DOM;

use DOMElement;

class IfDirective implements DOMDirective
{
    public function supports(DOMElement $node): bool
    {
        return $node->hasAttribute('x:if');
    }

    public function compileOpen(DOMElement $node): string
    {
        preg_match('/\{(.+?)\}/', $node->getAttribute('x:if'), $m);
        return "<?php if ({$m[1]}): ?>";
    }

    public function compileClose(DOMElement $node): string
    {
        return "<?php endif; ?>";
    }

    public function isSelfClosing(): bool
    {
        return false; // must compile children
    }
}
