<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use Baueri\Mint\MintCompiler;
use DOMElement;
use RuntimeException;

class CustomComponentDirective implements DOMDirective
{
    /** @see registerComponentDirective() tag becomes mint-{name} */
    public const TAG_PREFIX = 'mint-';

    public function __construct(
        protected readonly string $name,
        protected readonly string $class,
        protected readonly MintCompiler $compiler,
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
        return $node->tagName === self::TAG_PREFIX . $this->name;
    }

    /**
     * True when the element has inner content that should be captured as the slot
     * (non-whitespace text or any child element). When false, render without ob_start / slot.
     */
    public function hasSlotBody(DOMElement $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                if (trim($child->nodeValue) !== '') {
                    return true;
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                return true;
            }
        }

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
            if (array_key_exists($propKey, $out)) {
                throw new RuntimeException(":props lists \"{$propKey}\" more than once.");
            }

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

    public function compileOpen(DOMElement $node): string
    {
        $class = str_replace('\\\\', '\\', $this->class);
        $propsArray = $this->propsArrayPhp($node);
        $attrBlock = $this->compiler->compileForwardedAttributesBlock($node);

        if (! $this->hasSlotBody($node)) {
            return '        <?php' . "\n            "
                . $attrBlock . "\n            "
                . '$__mint_props = new \Baueri\Mint\Context(\array_merge(' . "\n                "
                . "['attributes' => \$__mint_attributes],\n                [\n                    "
                . $propsArray . "\n                ]\n            ), \$__mint_view);\n\n            "
                . '$component = new \\' . $class . "();\n\n            "
                . 'echo $component->render($__mint_props);' . "\n        ?>\n";
        }

        return "<?php ob_start(); ?>\n";
    }

    public function compileClose(DOMElement $node): string
    {
        if (! $this->hasSlotBody($node)) {
            return '';
        }

        $class = str_replace('\\\\', '\\', $this->class);
        $propsArray = $this->propsArrayPhp($node);
        $attrBlock = $this->compiler->compileForwardedAttributesBlock($node);

        return '        <?php' . "\n            "
            . '$__mint_slot = ob_get_clean();' . "\n\n            "
            . $attrBlock . "\n            "
            . '$__mint_props = new \Baueri\Mint\Context(\array_merge(' . "\n                "
            . "['attributes' => \$__mint_attributes],\n                [\n                    "
            . $propsArray . "\n                ]\n            ), \$__mint_view, \$__mint_slot);\n\n            "
            . '$component = new \\' . $class . "();\n\n            "
            . 'echo $component->render($__mint_props);' . "\n        ?>\n";
    }

    public function isSelfClosing(): bool
    {
        return false;
    }
}
