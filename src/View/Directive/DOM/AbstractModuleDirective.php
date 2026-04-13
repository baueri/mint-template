<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use Baueri\Mint\MintCompiler;
use DOMElement;
use RuntimeException;

/**
 * Shared compilation for <mod-{name}> tags (class-backed or view-backed modules).
 */
abstract class AbstractModuleDirective implements DOMDirective
{
    /** @see MintCompiler::registerModule() tag becomes mod-{name} */
    public const TAG_PREFIX = 'mod-';

    public function __construct(
        protected readonly string $name,
        protected readonly MintCompiler $compiler,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('parameter `$name` must not be empty');
        }
    }

    public function moduleSuffix(): string
    {
        return $this->name;
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
     * PHP statements after `$__mint_props` is constructed (including `echo` / `new`).
     */
    abstract protected function renderAfterContextPhp(): string;

    /**
     * @return array<string, string> prop name => PHP expression string
     */
    protected function collectProps(DOMElement $node): array
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

        return array_merge($fromPropsAttr, $explicit);
    }

    /**
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

    protected function propsArrayPhp(DOMElement $node): string
    {
        $propsPhp = [];
        foreach ($this->collectProps($node) as $key => $expr) {
            $propsPhp[] = "'{$key}' => {$expr}";
        }

        return implode(",\n    ", $propsPhp);
    }

    public function compileOpen(DOMElement $node): string
    {
        $propsArray = $this->propsArrayPhp($node);
        $attrBlock = $this->compiler->compileForwardedAttributesBlock($node);
        $render = $this->renderAfterContextPhp();

        if (! $this->hasSlotBody($node)) {
            return '        <?php' . "\n            "
                . $attrBlock . "\n            "
                . '$__mint_props = new \Baueri\Mint\Context(\array_merge(' . "\n                "
                . "['attributes' => \$__mint_attributes],\n                [\n                    "
                . $propsArray . "\n                ]\n            ), \$__mint_view);\n\n            "
                . $render . "\n        ?>\n";
        }

        return '';
    }

    public function compileClose(DOMElement $node): string
    {
        if (! $this->hasSlotBody($node)) {
            return '';
        }

        $propsArray = $this->propsArrayPhp($node);
        $attrBlock = $this->compiler->compileForwardedAttributesBlock($node);
        $render = $this->renderAfterContextPhp();

        return '        <?php' . "\n            "
            . '$__mint_frame = array_pop($__mint_slot_stack);' . "\n            "
            . '$__mint_mod_slot = new \\Baueri\\Mint\\Slot(\\array_merge(' . "\n                "
            . '$__mint_frame[\'named\'],' . "\n                "
            . '[\'body\' => $__mint_frame[\'def_buf\']]' . "\n            "
            . '));' . "\n\n            "
            . $attrBlock . "\n            "
            . '$__mint_props = new \Baueri\Mint\Context(\array_merge(' . "\n                "
            . "['attributes' => \$__mint_attributes],\n                [\n                    "
            . $propsArray . "\n                ]\n            ), \$__mint_view, \$__mint_mod_slot);"
            . "\n\n            "
            . $render . "\n        ?>\n";
    }

    /**
     * Compile module children: default slot is all content outside mint-slot; each mint-slot name
     * captures its inner HTML. Uses a stack so nested modules do not clobber buffers.
     */
    public function compileChildrenWithSlots(DOMElement $node): string
    {
        $php = '<?php $__mint_slot_stack = $__mint_slot_stack ?? [];' . "\n"
            . '$__mint_slot_stack[] = [\'named\' => [], \'def_buf\' => \'\'];' . "\n"
            . 'ob_start(); ?>' . "\n";

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === 'mint-slot') {
                $name = trim($child->getAttribute('name'));
                if ($name === '') {
                    throw new RuntimeException('mint-slot requires a non-empty name attribute');
                }
                if ($name === 'body') {
                    throw new RuntimeException(
                        'mint-slot name="body" is reserved for the default slot; omit mint-slot for main content.'
                    );
                }
                $namePhp = var_export($name, true);

                $php .= '<?php $__mint_sf = array_key_last($__mint_slot_stack);' . "\n"
                    . '$__mint_slot_stack[$__mint_sf][\'def_buf\'] .= ob_get_clean();' . "\n"
                    . 'ob_start(); ?>' . "\n";

                foreach ($child->childNodes as $slotChild) {
                    $php .= $this->compiler->compileNode($slotChild);
                }

                $php .= '<?php $__mint_sf = array_key_last($__mint_slot_stack);' . "\n"
                    . '$__mint_slot_stack[$__mint_sf][\'named\'][' . $namePhp . '] = '
                    . '($__mint_slot_stack[$__mint_sf][\'named\'][' . $namePhp . '] ?? \'\') . ob_get_clean();' . "\n"
                    . 'ob_start(); ?>' . "\n";

                continue;
            }

            $php .= $this->compiler->compileNode($child);
        }

        $php .= '<?php $__mint_sf = array_key_last($__mint_slot_stack);' . "\n"
            . '$__mint_slot_stack[$__mint_sf][\'def_buf\'] .= ob_get_clean();' . "\n"
            . '?>' . "\n";

        return $php;
    }

    public function isSelfClosing(): bool
    {
        return false;
    }
}
