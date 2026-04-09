<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\DOM;

use DOMElement;
use Baueri\Mint\MintCompiler;
use RuntimeException;

class RepeatDirective implements DOMDirective
{
    public function __construct(
        protected readonly MintCompiler $compiler
    ) {}

    public function supports(DOMElement $node): bool
    {
        return $node->hasAttribute('x:repeat');
    }

    public function compileOpen(DOMElement $node): string
    {
        [$countExpr, $loopVar] = $this->parseRepeatAttribute($node->getAttribute('x:repeat'));

        $clone = $node->cloneNode(true);
        $clone->removeAttribute('x:repeat');

        $inner = $this->compiler->compileNode($clone);

        return "<?php for ({$loopVar} = 0; {$loopVar} < (int) ({$countExpr}); {$loopVar}++): ?>{$inner}";
    }

    public function compileClose(DOMElement $node): string
    {
        return '<?php endfor; ?>';
    }

    public function isSelfClosing(): bool
    {
        return true;
    }

    /**
     * @return array{0: string, 1: string} [count PHP expression, loop variable e.g. "$i"]
     */
    private function parseRepeatAttribute(string $value): array
    {
        $value = trim($value);
        if (! preg_match('/^\{\s*(.+?)\s+as\s+(\$\w+)\s*\}$/', $value, $m)) {
            throw new RuntimeException(
                'x:repeat requires `{ <count> as $var }`, e.g. `{ $n as $i }` or `{ 5 as $i }`.'
            );
        }

        return [trim($m[1]), $m[2]];
    }
}
