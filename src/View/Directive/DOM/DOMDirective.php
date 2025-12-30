<?php

declare(strict_types=1);

namespace Mint\View\Directive\DOM;

use DOMElement;

interface DOMDirective
{
    public function supports(DOMElement $node): bool;

    public function compileOpen(DOMElement $node): string;

    public function compileClose(DOMElement $node): string;

    /**
     * Return true if this directive is self-closing (no child nodes should be compiled)
     */
    public function isSelfClosing(): bool;
}

