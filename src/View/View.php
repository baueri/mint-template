<?php

declare(strict_types=1);

namespace Baueri\Mint;

interface View
{
    public function render(string $template, array $data = []): string;

    /**
     * Register variables merged into every render; per-render data overrides the same keys.
     *
     * @param array<string, mixed>|string $key
     */
    public function share(array|string $key, mixed $value = null): void;

    /**
     * @return array<string, mixed>
     */
    public function shared(): array;
}
