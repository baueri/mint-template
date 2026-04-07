<?php

namespace Mint\View\Directive\DOM;

use DOMElement;

class ComponentDirective implements DOMDirective
{
    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'x-component';
    }

    public function compileOpen(DOMElement $node): string
    {
        $class = $node->getAttribute('name');

        if (!$class) {
            throw new \RuntimeException('<x-component> requires a name attribute with full class namespace');
        }

        // Fix escaped namespace
        $class = str_replace('\\\\', '\\', $class);

        $props = [];
        foreach ($node->attributes as $attr) {
            if (str_starts_with($attr->name, ':')) {
                $key = substr($attr->name, 1);

                // "{$user}" → "$user"
                if (preg_match('/\{(.+?)\}/', $attr->value, $m)) {
                    $props[$key] = $m[1];
                }
            }
        }

        $propsPhp = [];
        foreach ($props as $key => $expr) {
            $propsPhp[] = "'{$key}' => {$expr}";
        }

        $propsArray = implode(",\n    ", $propsPhp);

        return <<<PHP
        <?php
            \$__mint_props = new \\Mint\\View\\Context([
                {$propsArray}
            ], \$__mint_view);

            \$component = new \\{$class}();

            echo \$component->render(\$__mint_props);
        ?>
        PHP;
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