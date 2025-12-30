<?php

declare(strict_types=1);

namespace Mint\View\Directive\DOM;

use DOMElement;

class ForeachDirective implements DOMDirective
{
    public function supports(DOMElement $node): bool
    {
        return $node->hasAttribute('x:foreach');
    }

    public function compileOpen(DOMElement $node): string
    {
        // "{$users as $user}"
        preg_match('/\{(.+?)\s+as\s+(.+?)\}/', $node->getAttribute('x:foreach'), $m);

        $collection = trim($m[1]);
        $item = trim($m[2]);

        return "<?php foreach ({$collection} as {$item}): ?>";
    }

    public function compileClose(DOMElement $node): string
    {
        return "<?php endforeach; ?>";
    }

    public function isSelfClosing(): bool
    {
        return false; // must compile children inside foreach
    }
}
