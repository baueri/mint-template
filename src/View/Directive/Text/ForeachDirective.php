<?php

declare(strict_types=1);

namespace Baueri\Mint\Directive\Text;

use Baueri\Mint\Directive\Text\TextDirectiveInterface;

class ForeachDirective implements TextDirectiveInterface
{
    public function compile(string $template): string
    {
        $patterns = [
            '/@foreach\s*\((.*?)\)/' => '<?php foreach($1): ?>',
            '/@endforeach/' => '<?php endforeach; ?>'
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $template);
    }
}
