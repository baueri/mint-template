<?php

declare(strict_types=1);

namespace Mint\View\Directive\Text;

interface TextDirectiveInterface
{
    /**
     * Compile the template text for this directive
     */
    public function compile(string $template): string;
}
