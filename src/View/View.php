<?php

declare(strict_types=1);

namespace Baueri\Mint;

interface View
{
    public function render(string $template, array $data = []): string;
}
