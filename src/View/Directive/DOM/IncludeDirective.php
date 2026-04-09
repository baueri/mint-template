<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use DOMElement;
use RuntimeException;

/**
 * Render another template at runtime, inheriting the current render data.
 *
 * Usage:
 *   <mint-include name="partials/card.php" />
 *   <mint-include name="partials/card.php" :props="{$a, $b}" :a="{ $override }" />
 */
final class IncludeDirective implements DOMDirective
{
    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'mint-include';
    }

    public function compileOpen(DOMElement $node): string
    {
        $name = trim($node->getAttribute('name'));
        if ($name === '') {
            throw new RuntimeException('mint-include requires a name attribute');
        }

        $propsArray = $this->propsArrayPhp($node);
        $namePhp = addslashes($name);

        return "<?php echo \$__mint_view->render('{$namePhp}', array_merge(\$__mint_data ?? [], [\n    {$propsArray}\n])); ?>";
    }

    public function compileClose(DOMElement $node): string
    {
        return '';
    }

    public function isSelfClosing(): bool
    {
        return true;
    }

    /**
     * @return array<string, string> prop name => PHP expression string
     */
    private function collectProps(DOMElement $node): array
    {
        $fromPropsAttr = [];
        $explicit = [];

        foreach ($node->attributes as $attr) {
            if (! str_starts_with($attr->name, ':')) {
                continue;
            }

            $key = substr($attr->name, 1);
            if ($key === 'props') {
                $fromPropsAttr = $this->parsePropsShorthandAttribute($attr->value);
                continue;
            }

            if (preg_match('/^\{(.+?)\}$/', $attr->value, $m)) {
                $prop = $m[1];
            } else {
                $value = addslashes($attr->value);
                $prop = "'{$value}'";
            }

            $explicit[$key] = $prop;
        }

        // Shorthand first; explicit :attr wins for the same key.
        return array_merge($fromPropsAttr, $explicit);
    }

    /**
     * :props="{$a, $b, $c}" → prop keys a, b, c from variable names (no $ in key).
     *
     * Duplicates are allowed (last wins); explicit :a wins via array_merge.
     *
     * @return array<string, string>
     */
    private function parsePropsShorthandAttribute(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (! preg_match('/^\{\s*(.*?)\s*\}$/', $value, $m)) {
            throw new RuntimeException(
                ':props must use brace shorthand, e.g. :props="{$title, $author}"'
            );
        }

        $inner = trim($m[1]);
        if ($inner === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $inner) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                throw new RuntimeException(':props list contains an empty entry.');
            }

            if (! preg_match('/^\$\w+$/', $part)) {
                throw new RuntimeException(
                    ":props may only list simple variables like \$x (got \"{$part}\")."
                );
            }

            $propKey = substr($part, 1);
            $out[$propKey] = $part;
        }

        return $out;
    }

    private function propsArrayPhp(DOMElement $node): string
    {
        $propsPhp = [];
        foreach ($this->collectProps($node) as $key => $expr) {
            $propsPhp[] = "'{$key}' => {$expr}";
        }

        return implode(",\n    ", $propsPhp);
    }
}

