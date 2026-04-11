<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use Baueri\Mint\MintCompiler;

/**
 * Renders a registered view-only <mod-{name}> via Context::view()->render().
 */
final class ViewModuleDirective extends AbstractModuleDirective
{
    public function __construct(
        string $name,
        private readonly string $template,
        MintCompiler $compiler,
    ) {
        if ($template === '') {
            throw new \InvalidArgumentException('parameter `$template` must not be empty');
        }

        parent::__construct($name, $compiler);
    }

    protected function renderAfterContextPhp(): string
    {
        $tpl = addslashes($this->template);

        return 'echo $__mint_props->view()->render(\'' . $tpl . '\', \array_merge($__mint_view->shared(), $__mint_props->all()));';
    }
}
