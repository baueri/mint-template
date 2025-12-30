<?php

declare(strict_types=1);

namespace Mint\View;

interface View
{
    public function render(string $template, array $data = []): string;
}
