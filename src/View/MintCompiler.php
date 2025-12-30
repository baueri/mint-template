<?php

declare(strict_types=1);

namespace Mint\View;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Mint\View\Directive\DOM\ComponentDirective;
use Mint\View\Directive\DOM\DOMDirective;
use Mint\View\Directive\DOM\ForeachDirective;
use Mint\View\Directive\DOM\IfDirective;
use Mint\View\Directive\DOM\SectionDirective;
use Mint\View\Directive\DOM\WrapDirective;
use Mint\View\Directive\DOM\YieldDirective;
use Mint\View\Directive\Text\IfDirective as TextIfDirective;
use Mint\View\Directive\Text\TextDirectiveInterface;

class MintCompiler
{
    private RenderContext $context;

    /** @var DOMDirective[] */
    private array $domDirectives;

    /** @var TextDirectiveInterface[] */
    private array $textDirectives;

    public function __construct(private readonly string $viewPath)
    {
        $this->context = new RenderContext();

        $this->domDirectives = [
            new IfDirective(),
            new ForeachDirective(),
            new ComponentDirective(),
            new SectionDirective($this, $this->context),
            new YieldDirective($this->context),
            new WrapDirective($this, $this->context)
        ];

        $this->textDirectives = [
            new TextIfDirective()
        ];
    }

    /**
     * Register a DOM directive
     */
    public function registerDirective(DOMDirective $directive): void
    {
        $this->domDirectives[] = $directive;
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
        $template = file_get_contents($templatePath);

        // 1️⃣ Apply all text-based directives in order
        foreach ($this->textDirectives as $directive) {
            $template = $directive->compile($template);
        }

        // 2️⃣ Parse DOM and compile DOM-based directives
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML($template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return $this->walk($dom);
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
                    $php = $directive->compileOpen($node);

                    if (!$directive->isSelfClosing()) {
                        foreach ($node->childNodes as $child) {
                            $php .= $this->walk($child);
                        }
                    }

                    $php .= $directive->compileClose($node);
                    return $php;
                }
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

        foreach ($node->attributes as $attr) {
            if ($removeDirective && str_starts_with($attr->name, 'x:')) {
                continue;
            }
            $attrs .= " {$attr->name}=\"{$attr->value}\"";
        }

        $html = "<{$tag}{$attrs}>";

        foreach ($node->childNodes as $child) {
            $html .= $this->walk($child);
        }

        $html .= "</{$tag}>";

        return $html;
    }

    private function compileEcho(string $text): string
    {
        return preg_replace_callback(
            '/\{([^}]+)\}/',
            fn($m) => "<?php echo {$m[1]}; ?>",
            $text
        );
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

    public function getContext(): RenderContext
    {
        return $this->context;
    }
}
