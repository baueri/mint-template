<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use DOMElement;
use RuntimeException;

final class ExtendDirective implements DOMDirective
{
    public function supports(DOMElement $node): bool
    {
        return $node->tagName === 'mint-extend';
    }

    public function compileOpen(DOMElement $node): string
    {
        $path = trim($node->getAttribute('path'));
        if ($path === '') {
            throw new RuntimeException('mint-extend requires a path attribute');
        }

        // Buffer children so we can pass them as $slot when rendering the layout.
        return "<?php ob_start(); ?>\n";
    }

    public function compileClose(DOMElement $node): string
    {
        $path = trim($node->getAttribute('path'));
        if ($path === '') {
            throw new RuntimeException('mint-extend requires a path attribute');
        }

        $pathPhp = addslashes($path);
        $propsArray = $this->propsArrayPhp($node);

        // Order: page data, wrap props, env, then slot (buffered body) last so it wins.
        return "<?php\n"
            . "    echo \$__mint_view->render('{$pathPhp}', array_merge(\$__mint_data ?? [], [\n"
            . "        {$propsArray}\n"
            . "    ], ['__mint_env' => \$__mint_env, 'slot' => ob_get_clean()]));\n"
            . "?>";
    }

    public function isSelfClosing(): bool
    {
        return false;
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

            $key = $this->normalizePropKey(substr($attr->name, 1));
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
     * HTML parsing may normalize attribute names. Prefer kebab-case for props (e.g. `:body-class`)
     * and convert it to camelCase (`bodyClass`) for use as PHP variable keys.
     */
    private function normalizePropKey(string $key): string
    {
        if (! str_contains($key, '-')) {
            return $key;
        }

        $parts = explode('-', $key);
        $first = array_shift($parts) ?? '';
        $out = $first;
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $out .= strtoupper($p[0]) . substr($p, 1);
        }

        return $out;
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

        return implode(",\n        ", $propsPhp);
    }
}
