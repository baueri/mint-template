<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escape a value for safe HTML output.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

