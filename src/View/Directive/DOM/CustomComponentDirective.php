<?php

declare(strict_types=1);

namespace Mint\View\Directive\DOM;

use DOMElement;
use Mint\View\Component\Component;
use Mint\View\Directive\DOM\DOMDirective;


class CustomComponentDirective implements DOMDirective
{
    public function __construct(
        protected readonly string $name,
        protected readonly string $class
    ) {
        if (empty($class)) {
            throw new \InvalidArgumentException('parameter `$class` must not be empty');
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('parameter `$name` must not be empty');
        }
    }

    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'x-' . $this->name;
    }

    public function compileOpen(DOMElement $node): string
    {
        $class = str_replace('\\\\', '\\', $this->class);

        $props = [];

        foreach ($node->attributes as $attr) {
            if (str_starts_with($attr->name, ':')) {
                $key = substr($attr->name, 1);

                if (preg_match('/^\{(.+?)\}$/', $attr->value, $m)) {
                    $prop = $m[1];
                } else {
                    $value = addslashes($attr->value);
                    $prop = "'{$value}'";
                }

                $props[$key] = $prop;
            }
        }

        $propsPhp = [];
        foreach ($props as $key => $expr) {
            $propsPhp[] = "'{$key}' => {$expr}";
        }

        $propsArray = implode(",\n    ", $propsPhp);

        return <<<PHP
<?php
    ob_start(); // 🔥 SLOT START
?>
PHP;
    }

    public function compileClose(DOMElement $node): string
    {
        $class = str_replace('\\\\', '\\', $this->class);

        $props = [];

        foreach ($node->attributes as $attr) {
            if (str_starts_with($attr->name, ':')) {
                $key = substr($attr->name, 1);

                if (preg_match('/^\{(.+?)\}$/', $attr->value, $m)) {
                    $prop = $m[1];
                } else {
                    $value = addslashes($attr->value);
                    $prop = "'{$value}'";
                }

                $props[$key] = $prop;
            }
        }

        $propsPhp = [];
        foreach ($props as $key => $expr) {
            $propsPhp[] = "'{$key}' => {$expr}";
        }

        $propsArray = implode(",\n    ", $propsPhp);

        return <<<PHP
        <?php
            \$__mint_slot = ob_get_clean(); // 🔥 SLOT END

            \$__mint_props = new \\Mint\\View\\Context([
                {$propsArray}
            ], \$__mint_view, \$__mint_slot);

            \$component = new \\{$class}();

            echo \$component->render(\$__mint_props);
        ?>
        PHP;
    }

    public function isSelfClosing(): bool
    {
        return false; // 🔥 MOST MÁR NEM!
    }
}
