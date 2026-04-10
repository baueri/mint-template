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
        $hash = sha1($template);

        return $this->path . '/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '.php';
    }

    public function isFresh(string $template, string $source): bool
    {
        $compiled = $this->compiledPath($template);

        return is_file($compiled)
            && filemtime($compiled) >= filemtime($source);
    }

    /**
     * @param string|null $sourcePath Absolute path to the source template (for the generated file header only).
     */
    public function write(string $template, string $php, ?string $sourcePath = null): string
    {
        $file = $this->compiledPath($template);
        $dir = dirname($file);
        if (! is_dir($dir)) {
            $ok = mkdir($dir, 0775, true);
            if (! $ok && ! is_dir($dir)) {
                throw new \RuntimeException("Failed to create cache directory: {$dir}");
            }
        }

        $header = 'Mint compiled. logical template: ' . $template;
        if ($sourcePath !== null && $sourcePath !== '') {
            $header .= ' | source: ' . $sourcePath;
        }
        $prefix = "<?php // {$header} ?>" . PHP_EOL;

        $bytes = file_put_contents($file, $prefix . $php);
        if ($bytes === false) {
            throw new \RuntimeException("Failed to write compiled template: {$file}");
        }

        return $file;
    }

    /**
     * Remove the compiled artifact for a logical template name (e.g. `page.php` or `pkg::partials/x.php`).
     */
    public function forget(string $template): void
    {
        $compiled = $this->compiledPath($template);
        if (is_file($compiled)) {
            @unlink($compiled);
        }
        $dir = dirname($compiled);
        if (is_dir($dir)) {
            @rmdir($dir);
        }
        $grand = dirname($dir);
        if (is_dir($grand)) {
            @rmdir($grand);
        }
    }

    public function clear(): void
    {
        if (! is_dir($this->path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->path,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                @unlink($path);
            } elseif ($file->isDir()) {
                @rmdir($path);
            }
        }
    }
}
