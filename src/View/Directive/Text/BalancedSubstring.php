<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\Text;

/**
 * Finds the closing ")" that matches "(" at $openParenIndex (nested parens supported).
 * One-line expressions only; strings are not treated specially.
 */
final class BalancedSubstring
{
    public static function closingParenIndex(string $s, int $openParenIndex): ?int
    {
        $depth = 1;
        $len = strlen($s);
        for ($i = $openParenIndex + 1; $i < $len; $i++) {
            $c = $s[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }
}
