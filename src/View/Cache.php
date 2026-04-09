<?php

declare(strict_types=1);

namespace Baueri\Mint;

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
        if (! is_dir($this->path)) {
            $ok = mkdir($this->path, 0775, true);
            if (! $ok && ! is_dir($this->path)) {
                throw new \RuntimeException("Failed to create cache directory: {$this->path}");
            }
        }

        $prefix = "<?php // rendered template of: {$this->path}/{$template} ?>" . PHP_EOL;

        $file = $this->compiledPath($template);
        $bytes = file_put_contents($file, $prefix . $php);
        if ($bytes === false) {
            throw new \RuntimeException("Failed to write compiled template: {$file}");
        }

        return $file;
    }

    public function clear(): void
    {
        if (!is_dir($this->path)) {
            return;
        }

        $iterator = new \DirectoryIterator($this->path);
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                @unlink($file->getPathname());
            }
        }
    }
}
