<?php

declare(strict_types=1);

namespace Baueri\Mint;

interface CacheInterface
{
    public function isFresh(string $template, string $sourcePath): bool;

    public function write(string $template, string $php, ?string $sourcePath = null): string;

    public function compiledPath(string $template): string;

    public function forget(string $template): void;

    public function clear(): void;
}
