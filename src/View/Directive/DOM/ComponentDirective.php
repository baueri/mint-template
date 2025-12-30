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

        // Replace double backslashes with single backslashes
        $class = str_replace('\\\\', '\\', $class);

        $props = [];
        foreach ($node->attributes as $attr) {
            if (str_starts_with($attr->name, ':')) {
                $key = substr($attr->name, 1);

                // value like "{$user}" → "$user"
                preg_match('/\{(.+?)\}/', $attr->value, $m);

                $props[$key] = $m[1];
            }
        }

        $propsPhp = [];
        foreach ($props as $key => $expr) {
            $propsPhp[] = "'{$key}' => {$expr}";
        }

        $propsArray = implode(",\n    ", $propsPhp);

        return <<<PHP
<?php
\$__mint_props = new \Mint\View\Context([
    {$propsArray}
]);
\$component = new \\{$class}();
echo \$component->render(\$__mint_props);
?>
PHP;
    }

    public function compileClose(DOMElement $node): string
    {
        // self-closing component → no close output
        return '';
    }

    public function isSelfClosing(): bool
    {
        return true; // children should NOT be compiled
    }
}
