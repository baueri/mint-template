<?php

declare(strict_types=1);

namespace Mint\View\Directive\Text;

class IfDirective implements TextDirectiveInterface
{
    public function compile(string $template): string
    {
        $patterns = [
            '/@if\s*\((.*?)\)/'      => '<?php if($1): ?>',
            '/@elseif\s*\((.*?)\)/'  => '<?php elseif($1): ?>',
            '/@else\b/'               => '<?php else: ?>',
            '/@endif\b/'              => '<?php endif; ?>',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $template);
    }
}
