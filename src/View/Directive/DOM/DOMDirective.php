<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use DOMElement;

interface DOMDirective
{
    public function supports(DOMElement $node): bool;

    public function compileOpen(DOMElement $node): string;

    public function compileClose(DOMElement $node): string;

    /**
     * Return true if child nodes should not be compiled separately (e.g. mint-yield).
     */
    public function isSelfClosing(): bool;
}

