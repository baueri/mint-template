<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use DOMElement;
use RuntimeException;

/**
 * <mint-slot> is only compiled when nested inside a <mod-*> body (see AbstractModuleDirective).
 * Any other occurrence is rejected at compile time.
 */
final class MintSlotDirective implements DOMDirective
{
    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'mint-slot';
    }

    public function compileOpen(DOMElement $node): string
    {
        throw new RuntimeException(
            '<mint-slot> may only appear as a direct child of a <mod-*> module body.'
        );
    }

    public function compileClose(DOMElement $node): string
    {
        return '';
    }

    public function isSelfClosing(): bool
    {
        return false;
    }
}
