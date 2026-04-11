<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use Baueri\Mint\MintCompiler;

class CustomModuleDirective extends AbstractModuleDirective
{
    public function __construct(
        string $name,
        private readonly string $class,
        MintCompiler $compiler,
    ) {
        if ($class === '') {
            throw new \InvalidArgumentException('parameter `$class` must not be empty');
        }

        parent::__construct($name, $compiler);
    }

    protected function renderAfterContextPhp(): string
    {
        $class = str_replace('\\\\', '\\', $this->class);

        return '$module = new \\' . $class . "();\n\n"
            . 'echo $module->render($__mint_props);';
    }
}
