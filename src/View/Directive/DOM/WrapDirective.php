<?php

namespace Baueri\Mint\Directive\DOM;

use DOMElement;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\RenderContext;
use RuntimeException;

class WrapDirective implements DOMDirective
{
    public function __construct(
        private readonly MintCompiler $compiler,
        private readonly RenderContext $context
    ) {}

    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'mint-wrap';
    }

    public function compileOpen(DOMElement $node): string
    {
        $view = $node->getAttribute('view');

        if (!$view) {
            throw new RuntimeException('mint-wrap requires a view attribute');
        }

        $viewPhp = addslashes($view . '.php');

        // Buffer everything inside <mint-wrap> so we can set it as a section at runtime,
        // then render the layout template (parent) in the same render environment.
        return <<<PHP
<?php ob_start(); ?>
PHP;
    }

    public function compileClose(DOMElement $node): string
    {
        $view = $node->getAttribute('view');
        $viewPhp = addslashes($view . '.php');

        return <<<PHP
<?php
    \$__mint_env->sections['{$view}'] = ob_get_clean();
    echo \$__mint_view->render('{$viewPhp}', array_merge(\$__mint_data ?? [], ['__mint_env' => \$__mint_env]));
?>
PHP;
    }

    public function isSelfClosing(): bool
    {
        return false;
    }
}
