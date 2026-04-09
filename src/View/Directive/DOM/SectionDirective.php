<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use DOMElement;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\RenderContext;

class SectionDirective implements DOMDirective
{
    public function __construct(
        private readonly MintCompiler $compiler,
        private readonly RenderContext $context,
    ) {}

    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'mint-section';
    }

    public function compileOpen(DOMElement $node): string
    {
        return "<?php ob_start(); ?>";
    }

    public function compileClose(DOMElement $node): string
    {
        $namePhp = addslashes($node->getAttribute('name'));

        return <<<PHP
<?php
    \$__mint_section = ob_get_clean();
    \$__mint_env->sections['{$namePhp}'] = (\$__mint_env->sections['{$namePhp}'] ?? '') . \$__mint_section;
?>
PHP;
    }

    public function isSelfClosing(): bool
    {
        return false;
    }
}
