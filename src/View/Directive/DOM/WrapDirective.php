<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use DOMElement;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\RenderContext;
use RuntimeException;

final class WrapDirective implements DOMDirective
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

        // Buffer everything inside <mint-wrap> so we can set it as a section at runtime,
        // then render the layout template (parent) in the same render environment.
        return "<?php ob_start(); ?>\n";
    }

    public function compileClose(DOMElement $node): string
    {
        $view = $node->getAttribute('view');
        if (!$view) {
            throw new RuntimeException('mint-wrap requires a view attribute');
        }

        $viewPhp = addslashes($view . '.php');
        $propsArray = $this->propsArrayPhp($node);

        // Order matters:
        // - $__mint_data: page render data (defaults)
        // - wrap props: explicit overrides for the layout
        // - __mint_env: must always be the shared render environment (wins)
        return "<?php\n"
            . "    \$__mint_env->sections['{$view}'] = ob_get_clean();\n"
            . "    echo \$__mint_view->render('{$viewPhp}', array_merge(\$__mint_data ?? [], [\n"
            . "        {$propsArray}\n"
            . "    ], ['__mint_env' => \$__mint_env]));\n"
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
