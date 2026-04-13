<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\Text;

use Baueri\Mint\Directive\Text\TextDirectiveInterface;

class ForeachDirective implements TextDirectiveInterface
{
    public function compile(string $template): string
    {
        $pattern = '/@foreach\b\s*\(/';

        while (preg_match($pattern, $template, $m, PREG_OFFSET_CAPTURE)) {
            $matchStart = $m[0][1];
            $openParen = $matchStart + strlen($m[0][0]) - 1;
            $closeParen = BalancedSubstring::closingParenIndex($template, $openParen);
            if ($closeParen === null) {
                break;
            }

            $inner = substr($template, $openParen + 1, $closeParen - $openParen - 1);
            $replacement = '<?php foreach(' . $inner . '): ?>';
            $span = $closeParen + 1 - $matchStart;
            $template = substr_replace($template, $replacement, $matchStart, $span);
        }

        return preg_replace('/@endforeach\b/', '<?php endforeach; ?>', $template);
    }
}
