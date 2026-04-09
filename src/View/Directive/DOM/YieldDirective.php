<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use DOMElement;

class YieldDirective implements DOMDirective
{
    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'mint-yield';
    }

    public function compileOpen(DOMElement $node): string
    {
        $name = $node->getAttribute('name');

        $namePhp = addslashes($name);

        return "<?php echo \$__mint_env->sections['{$namePhp}'] ?? ''; ?>";
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
