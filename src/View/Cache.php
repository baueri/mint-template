<?php

declare(strict_types=1);

namespace Mint\View;

class Cache
{
    public function __construct(
        private readonly string $path
    ) {}

    public function compiledPath(string $template): string
    {
        return $this->path . '/' . sha1($template) . '.php';
    }

    public function isFresh(string $template, string $source): bool
    {
        $compiled = $this->compiledPath($template);

        return is_file($compiled)
            && filemtime($compiled) >= filemtime($source);
    }

    public function write(string $template, string $php): string
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        $file = $this->compiledPath($template);
        file_put_contents($file, $php);

        return $file;
    }
}
