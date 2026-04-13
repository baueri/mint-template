<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\Text;

class IfDirective implements TextDirectiveInterface
{
    public function compile(string $template): string
    {
        $template = self::replaceParenDirective($template, 'elseif', '<?php elseif(%s): ?>');
        $template = self::replaceParenDirective($template, 'if', '<?php if(%s): ?>');

        $patterns = [
            '/@else\b/'   => '<?php else: ?>',
            '/@endif\b/' => '<?php endif; ?>',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $template);
    }

    private static function replaceParenDirective(string $template, string $tag, string $phpFormat): string
    {
        $pattern = '/@' . preg_quote($tag, '/') . '\b\s*\(/';

        while (preg_match($pattern, $template, $m, PREG_OFFSET_CAPTURE)) {
            $matchStart = $m[0][1];
            $openParen = $matchStart + strlen($m[0][0]) - 1;
            $closeParen = BalancedSubstring::closingParenIndex($template, $openParen);
            if ($closeParen === null) {
                break;
            }

            $inner = substr($template, $openParen + 1, $closeParen - $openParen - 1);
            $replacement = sprintf($phpFormat, $inner);
            $span = $closeParen + 1 - $matchStart;
            $template = substr_replace($template, $replacement, $matchStart, $span);
        }

        return $template;
    }
}
