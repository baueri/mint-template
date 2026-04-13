<?php

declare(strict_types=1);

namespace Baueri\Mint;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Baueri\Mint\Directive\DOM\DOMDirective;
use Baueri\Mint\Directive\DOM\ForeachDirective;
use Baueri\Mint\Directive\DOM\IfDirective;
use Baueri\Mint\Directive\DOM\IncludeDirective;
use Baueri\Mint\Directive\DOM\RepeatDirective;
use Baueri\Mint\Directive\DOM\SectionDirective;
use Baueri\Mint\Directive\DOM\ExtendDirective;
use Baueri\Mint\Directive\DOM\MintSlotDirective;
use Baueri\Mint\Directive\DOM\YieldDirective;
use Baueri\Mint\Directive\Text\IfDirective as TextIfDirective;
use Baueri\Mint\Directive\Text\TextDirectiveInterface;
use Baueri\Mint\Directive\DOM\AbstractModuleDirective;
use Baueri\Mint\Directive\DOM\CustomModuleDirective;
use Baueri\Mint\Directive\DOM\ViewModuleDirective;
use Baueri\Mint\Directive\Text\ForeachDirective as TextForeachDirective;

class MintCompiler
{
    private const PHP_PLACEHOLDER_PREFIX = '__MINT_PHP_BLOCK_';

    /**
     * Synthetic element wrapping fragment templates for DOM parsing only.
     * Its opening/closing tags are never emitted — only child nodes are compiled.
     */
    private const COMPILE_FRAGMENT_ROOT_TAG = 'mint-internal-compile-root';

    /** Suffixes that produce tags reserved by built-in DOM directives or the compiler. */
    private const RESERVED_MINT_DIRECTIVE_SUFFIXES = [
        'include', 'extend', 'section', 'yield', 'attrs', 'internal-compile-root',
    ];

    /** @var DOMDirective[] */
    private array $domDirectives;

    /** @var TextDirectiveInterface[] */
    private array $textDirectives;

    /** @var array<string, true> */
    private array $registeredModuleSuffixes = [];

    public function __construct(private readonly string $viewPath)
    {
        $this->domDirectives = [
            new IfDirective(),
            new ForeachDirective($this),
            new RepeatDirective($this),
            new SectionDirective(),
            new YieldDirective(),
            new IncludeDirective(),
            new ExtendDirective(),
            new MintSlotDirective(),
        ];

        $this->textDirectives = [
            new TextIfDirective(),
            new TextForeachDirective()
        ];
    }

    /**
     * Register a DOM directive
     */
    public function registerDirective(DOMDirective $directive): void
    {
        if ($directive instanceof AbstractModuleDirective) {
            $this->reserveModuleSuffix($directive->moduleSuffix());
        }

        $this->domDirectives[] = $directive;
    }

    /**
     * Register a module: the tag name is `mod-` plus $name (e.g. `alert` → `<mod-alert>`).
     * $name must not contain `::` (that syntax is reserved for template paths on {@see MintView}).
     */
    public function registerModule(string $name, string $class): void
    {
        $this->registerDirective(new CustomModuleDirective($name, $class, $this));
    }

    /**
     * Register a view-only module: tag is `mod-` + $name (no `::` in $name). $template is a logical path
     * resolved like {@see MintView::render()} (optional `namespace::path.php`).
     */
    public function registerViewModule(string $name, string $template): void
    {
        $this->registerDirective(new ViewModuleDirective($name, $template, $this));
    }

    private function reserveModuleSuffix(string $name): void
    {
        if (str_contains($name, '::')) {
            throw new \InvalidArgumentException(
                'Module names cannot contain "::". That syntax is only for template paths on MintView (e.g. registerViewModule second argument); use a hyphenated tag suffix such as acme-badge.'
            );
        }

        if (isset($this->registeredModuleSuffixes[$name])) {
            throw new \InvalidArgumentException(
                "Module \"{$name}\" is already registered. Use a different name or a vendor prefix."
            );
        }

        $this->registeredModuleSuffixes[$name] = true;
    }

    /**
     * Register a text (@) directive
     */
    public function registerTextDirective(TextDirectiveInterface $directive): void
    {
        $this->textDirectives[] = $directive;
    }

    public function compile(string $templatePath): string
    {
        $template = @file_get_contents($templatePath);
        if ($template === false) {
            throw new \RuntimeException("Failed to read template: {$templatePath}");
        }

        foreach ($this->textDirectives as $directive) {
            $template = $directive->compile($template);
        }

        [$template, $phpBlocks] = $this->protectPhpBlocks($template);

        $template = $this->rewriteAttributesEchoInOpeningTags($template);

        $useFragmentRoot = $this->shouldWrapInCompileFragmentRoot($template);

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // libxml defaults HTML fragments to ISO-8859-1; declare UTF-8 so multibyte
        // literals (e.g. em dash, arrows) in template files are not mis-parsed.
        $tag = self::COMPILE_FRAGMENT_ROOT_TAG;
        $htmlUtf8 = '<?xml encoding="UTF-8">'
            . ($useFragmentRoot ? "<{$tag}>{$template}</{$tag}>" : $template);
        $ok = $dom->loadHTML($htmlUtf8, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (! $ok) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $msg = "Failed to parse template HTML: {$templatePath}";
            if (!empty($errors)) {
                $first = $errors[0];
                $msg .= " (line {$first->line}, column {$first->column})";
            }

            throw new \RuntimeException($msg);
        }

        $root = $dom->documentElement;
        if ($root === null) {
            throw new \RuntimeException("Failed to parse template HTML (no document element): {$templatePath}");
        }

        if ($useFragmentRoot) {
            if (strcasecmp($root->tagName, self::COMPILE_FRAGMENT_ROOT_TAG) !== 0) {
                throw new \RuntimeException(
                    "Unexpected DOM root <{$root->tagName}> after fragment parse: {$templatePath}"
                );
            }
            $compiled = '';
            foreach ($root->childNodes as $child) {
                $compiled .= $this->walk($child);
            }
        } else {
            $compiled = $this->walk($dom);
        }

        return $this->restorePhpBlocks($compiled, $phpBlocks);
    }

    /**
     * Bare text / mustaches at the document top become <p>…</p> under HTML parsing.
     * Wrapping those fragments in a synthetic root avoids that, while full documents
     * (DOCTYPE or <html>) must not be wrapped — libxml would break the tree.
     */
    private function shouldWrapInCompileFragmentRoot(string $templateForDom): bool
    {
        $s = ltrim($templateForDom, "\r\n\t ");
        $placeholderPrefix = preg_quote(self::PHP_PLACEHOLDER_PREFIX, '/');
        $s = preg_replace('/^(?:' . $placeholderPrefix . '\d+__\s*)+/', '', $s) ?? $s;
        $s = ltrim($s, "\r\n\t ");
        if ($s === '') {
            return true;
        }
        if (preg_match('/^<!DOCTYPE\b/i', $s) === 1) {
            return false;
        }
        if (preg_match('/^<html\b/i', $s) === 1) {
            return false;
        }

        return true;
    }

    /**
     * DOMDocument::loadHTML() does not reliably preserve PHP blocks.
     * Protect them with placeholders before parsing and restore after compilation.
     *
     * @return array{0: string, 1: array<string,string>}
     */
    private function protectPhpBlocks(string $template): array
    {
        $phpBlocks = [];
        $i = 0;

        $template = preg_replace_callback(
            '/<\?(?:php|=)[\s\S]*?\?>/i',
            function (array $m) use (&$phpBlocks, &$i): string {
                $key = self::PHP_PLACEHOLDER_PREFIX . $i++ . '__';
                $phpBlocks[$key] = $m[0];
                return $key;
            },
            $template
        );

        if ($template === null) {
            throw new \RuntimeException('Failed to protect PHP blocks in template.');
        }

        return [$template, $phpBlocks];
    }

    /**
     * HTML parsers drop `{{ $attributes }}` inside tag opens. Rewrite to `mint-attrs`, which
     * compileElement() turns into `<?php echo $attributes; ?>` before the closing `>`.
     */
    private function rewriteAttributesEchoInOpeningTags(string $template): string
    {
        $pattern = '/<([a-zA-Z][\w:-]*)([^>]*)>/';
        $result = preg_replace_callback(
            $pattern,
            static function (array $m): string {
                $attrs = $m[2];
                if (! preg_match('/\{\{\{\s*\$attributes\s*\}\}\}|\{\{\s*\$attributes\s*\}\}/', $attrs)) {
                    return $m[0];
                }

                $attrs2 = preg_replace(
                    '/\s*\{\{\{\s*\$attributes\s*\}\}\}\s*|\s*\{\{\s*\$attributes\s*\}\}\s*/',
                    ' ',
                    $attrs
                );
                // Avoid duplicate marker if the author also added `mint-attrs` by hand.
                $attrs2 = preg_replace('/\s+mint-attrs\b\s*/', ' ', (string) $attrs2);
                $attrs2 = trim((string) $attrs2);
                $mid = $attrs2 === '' ? '' : (' ' . $attrs2);

                return '<' . $m[1] . ' mint-attrs' . $mid . '>';
            },
            $template
        );

        if ($result === null) {
            throw new \RuntimeException('Failed to rewrite $attributes merge in opening tags.');
        }

        return $result;
    }

    private function restorePhpBlocks(string $compiled, array $phpBlocks): string
    {
        if (empty($phpBlocks)) {
            return $compiled;
        }

        return strtr($compiled, $phpBlocks);
    }

    private function walk(DOMNode $node): string
    {
        $output = '';

        if ($node instanceof DOMText) {
            return $this->compileEcho($node->nodeValue);
        }

        if ($node instanceof DOMElement) {
            foreach ($this->domDirectives as $directive) {
                if ($directive->supports($node)) {
                    // x:if should wrap the whole element, not only its children.
                    // Otherwise `<h1 x:if="...">OK</h1>` would compile to `if (...) OK endif`
                    // instead of `if (...) <h1>OK</h1> endif`.
                    if ($directive instanceof IfDirective) {
                        // Re-compile the element via walk() after stripping x:if so nested
                        // directives (e.g. <mod-alert x:if="...">) are not emitted as raw HTML.
                        $inner = $node->cloneNode(true);
                        $inner->removeAttribute('x:if');

                        return $directive->compileOpen($node)
                            . $this->walk($inner)
                            . $directive->compileClose($node);
                    }

                    $php = $directive->compileOpen($node);

                    $skipChildren = $directive->isSelfClosing()
                        || ($directive instanceof AbstractModuleDirective && ! $directive->hasSlotBody($node));

                    if (! $skipChildren) {
                        if ($directive instanceof AbstractModuleDirective && $directive->hasSlotBody($node)) {
                            $php .= $directive->compileChildrenWithSlots($node);
                        } else {
                            foreach ($node->childNodes as $child) {
                                $php .= $this->walk($child);
                            }
                        }
                    }

                    $php .= $directive->compileClose($node);
                    return $php;
                }
            }

            $tag = $node->tagName;
            if (str_starts_with($tag, AbstractModuleDirective::TAG_PREFIX)) {
                $suffix = substr($tag, strlen(AbstractModuleDirective::TAG_PREFIX));
                throw new \InvalidArgumentException(
                    "Unknown module \"<{$tag}>\". Did you forget to call registerModule('{$suffix}', ...)?"
                );
            }

            if (str_starts_with($tag, 'mint-') && $tag !== self::COMPILE_FRAGMENT_ROOT_TAG) {
                throw new \InvalidArgumentException(
                    "Unknown built-in directive \"<{$tag}>\". Check the tag name for typos."
                );
            }

            return $this->compileElement($node);
        }

        foreach ($node->childNodes as $child) {
            $output .= $this->walk($child);
        }

        return $output;
    }

    private function compileElement(DOMElement $node, bool $removeDirective = false): string
    {
        $tag = $node->tagName;
        $attrs = '';
        $forwardMintAttrs = $node->hasAttribute('mint-attrs');

        foreach ($node->attributes as $attr) {
            if ($removeDirective && str_starts_with($attr->name, 'x:')) {
                continue;
            }

            if ($attr->name === 'mint-attrs') {
                continue;
            }

            $value = $this->compileAttribute($attr->value);

            $attrs .= " {$attr->name}=\"{$value}\"";
        }

        $html = "<{$tag}{$attrs}";
        if ($forwardMintAttrs) {
            $html .= '<?php echo $attributes; ?>';
        }
        $html .= '>';

        if ($this->isHtmlVoidElement($tag)) {
            return $html;
        }

        foreach ($node->childNodes as $child) {
            $html .= $this->walk($child);
        }

        $html .= "</{$tag}>";

        return $html;
    }

    /**
     * HTML void elements must not get a closing tag from the compiler; libxml
     * still represents them as DOMElement nodes with no children.
     *
     * @see https://html.spec.whatwg.org/multipage/syntax.html#void-elements
     */
    private function isHtmlVoidElement(string $tagName): bool
    {
        static $void = [
            'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
            'link', 'meta', 'param', 'source', 'track', 'wbr',
        ];

        return in_array(strtolower($tagName), $void, true);
    }

    private function compileAttribute(string $value): string
    {
        $value = preg_replace_callback(
            '/\{\{\{\s*([\s\S]*?)\s*\}\}\}/',
            fn($m) => '<?php echo e(' . trim($m[1]) . '); ?>',
            $value
        );

        if ($value === null) {
            throw new \RuntimeException('Failed to compile escaped mustache in attribute.');
        }

        $value = preg_replace_callback(
            '/\{\{\s*([\s\S]*?)\s*\}\}/',
            fn($m) => '<?php echo ' . trim($m[1]) . '; ?>',
            $value
        );

        if ($value === null) {
            throw new \RuntimeException('Failed to compile raw mustache in attribute.');
        }

        return $value;
    }

    private function compileEcho(string $text): string
    {
        $text = preg_replace_callback(
            '/\{\{\{\s*([\s\S]*?)\s*\}\}\}/',
            fn($m) => '<?php echo e(' . trim($m[1]) . '); ?>',
            $text
        );

        if ($text === null) {
            throw new \RuntimeException('Failed to compile escaped mustache in text.');
        }

        $text = preg_replace_callback(
            '/\{\{\s*([\s\S]*?)\s*\}\}/',
            fn($m) => '<?php echo ' . trim($m[1]) . '; ?>',
            $text
        );

        if ($text === null) {
            throw new \RuntimeException('Failed to compile raw mustache in text.');
        }

        return $text;
    }

    public function compileView(string $name): string
    {
        $file = $this->viewPath . '/' . $name . '.php';

        return $this->compile($file);
    }

    public function compileNode(DOMNode $node): string
    {
        return $this->walk($node);
    }

    /**
     * Build PHP that sets $__mint_attributes from non-prop, non-directive HTML attributes
     * (everything except names starting with ":" or "x:").
     *
     * @return string PHP lines (no <?php), assigns $__mint_attributes (string)
     */
    public function compileForwardedAttributesBlock(DOMElement $node): string
    {
        $parts = ['$__mint_attributes = \'\';'];

        foreach ($node->attributes as $attr) {
            $name = $attr->name;
            if (str_starts_with($name, ':') || str_starts_with($name, 'x:')) {
                continue;
            }

            $parts[] = $this->compileForwardedAttributeAppend($name, $attr->value);
        }

        return implode("\n            ", $parts);
    }

    private function compileForwardedAttributeAppend(string $name, string $value): string
    {
        if (! preg_match('/^[a-zA-Z_:][\w\-.:]*$/', $name)) {
            throw new \RuntimeException("Invalid forwarded attribute name: {$name}");
        }

        if ($this->forwardedAttributeIsBoolean($name, $value)) {
            return '$__mint_attributes .= \' \' . ' . var_export($name, true) . ';';
        }

        $lines = [];
        $lines[] = '$__mint_attributes .= \' \' . ' . var_export($name, true) . ' . \'="\'' . ';';
        foreach ($this->compileForwardedAttributeValueFragments($value) as $line) {
            $lines[] = $line;
        }
        $lines[] = '$__mint_attributes .= \'"\';';

        return implode("\n            ", $lines);
    }

    private function forwardedAttributeIsBoolean(string $name, string $value): bool
    {
        if ($value !== '') {
            return false;
        }

        static $names = [
            'async', 'autofocus', 'autoplay', 'checked', 'controls', 'default', 'defer',
            'disabled', 'download', 'formnovalidate', 'hidden', 'ismap', 'itemscope', 'loop',
            'multiple', 'muted', 'novalidate', 'open', 'playsinline', 'readonly', 'required',
            'reversed', 'scoped', 'selected',
        ];

        return in_array(strtolower($name), $names, true);
    }

    /**
     * @return list<string> PHP lines appending to $__mint_attributes
     */
    private function compileForwardedAttributeValueFragments(string $value): array
    {
        $lines = [];
        $rest = $value;

        while ($rest !== '') {
            if (preg_match('/^\{\{\{\s*([\s\S]*?)\s*\}\}\}/', $rest, $m)) {
                $expr = trim($m[1]);
                $lines[] = '$__mint_attributes .= \\e(' . $expr . ');';
                $rest = substr($rest, strlen($m[0]));
            } elseif (preg_match('/^\{\{\s*([\s\S]*?)\s*\}\}/', $rest, $m)) {
                $expr = trim($m[1]);
                $lines[] = '$__mint_attributes .= (string) (' . $expr . ');';
                $rest = substr($rest, strlen($m[0]));
            } elseif (preg_match('/^[^{]+/', $rest, $m)) {
                $lines[] = '$__mint_attributes .= ' . var_export($m[0], true) . ';';
                $rest = substr($rest, strlen($m[0]));
            } elseif (str_starts_with($rest, '{')) {
                throw new \RuntimeException('Stray `{` in component HTML attribute value.');
            } else {
                break;
            }
        }

        return $lines;
    }
}
