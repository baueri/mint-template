<?php

declare(strict_types=1);

namespace Baueri\Mint;

class MintView implements View
{
    /** @var array<string, string> namespace => absolute directory */
    private array $namespaces = [];

    /** @var array<string, mixed> merged into every {@see render()} (per-render keys override) */
    private array $shared = [];

    public function __construct(
        public readonly string $viewsPath,
        public readonly CacheInterface $cache,
        public readonly MintCompiler $compiler
    ) {
    }

    /**
     * Register an additional view root, addressable as `{namespace}::relative/path.php`.
     */
    public function registerNamespace(string $namespace, string $path): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $namespace)) {
            throw new \InvalidArgumentException(
                'Namespace must match [a-zA-Z_][a-zA-Z0-9_]*.'
            );
        }

        if (isset($this->namespaces[$namespace])) {
            throw new \InvalidArgumentException("View namespace \"{$namespace}\" is already registered.");
        }

        $real = realpath($path);
        if ($real === false || ! is_dir($real)) {
            throw new \InvalidArgumentException("View namespace path is not a directory: {$path}");
        }

        $this->namespaces[$namespace] = $real;
    }

    /**
     * Register variables merged into every template render. Call-time {@see render()} data wins on key conflicts.
     *
     * @param array<string, mixed>|string $key
     */
    public function share(array|string $key, mixed $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $name => $v) {
                if (! is_string($name)) {
                    throw new \InvalidArgumentException('Shared variable names must be strings.');
                }
                $this->share($name, $v);
            }

            return;
        }

        $this->assertValidShareKey($key);
        $this->shared[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function shared(): array
    {
        return $this->shared;
    }

    public function render(string $template, array $data = []): string
    {
        $source = $this->resolveTemplateSourcePath($template);

        if (! $this->cache->isFresh($template, $source)) {
            $php = $this->compiler->compile($source);
            $this->cache->write($template, $php, $source);
        }

        $data = array_merge($this->shared, $data);

        if (! array_key_exists('slot', $data) && isset($__mint_slot)) {
            $data['slot'] = $__mint_slot;
        }

        $__mint_data = $data;
        $__mint_env = $data['__mint_env'] ?? new RenderContext();

        extract($data, EXTR_SKIP);

        $__mint_view = $this;

        ob_start();
        include $this->cache->compiledPath($template);

        return ob_get_clean();
    }

    private function resolveTemplateSourcePath(string $template): string
    {
        $pos = strpos($template, '::');
        if ($pos === false) {
            return $this->joinUnderRoot($this->viewsPath, $template);
        }

        if (strpos($template, '::', $pos + 2) !== false) {
            throw new \RuntimeException('Template name may only contain one "::" separator.');
        }

        $namespace = substr($template, 0, $pos);
        $relative = substr($template, $pos + 2);

        if ($namespace === '' || $relative === '') {
            throw new \RuntimeException('Invalid namespaced template name.');
        }

        if (! isset($this->namespaces[$namespace])) {
            throw new \RuntimeException("Unknown view namespace \"{$namespace}\".");
        }

        return $this->joinUnderRoot($this->namespaces[$namespace], $relative);
    }

    private function joinUnderRoot(string $root, string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || str_ends_with($relative, '/')) {
            throw new \RuntimeException('Invalid template path.');
        }

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '..' || $segment === '') {
                throw new \RuntimeException('Invalid template path segment.');
            }
        }

        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new \RuntimeException("Views path does not exist: {$root}");
        }

        $candidate = $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($candidate);

        if ($resolved === false || ! is_file($resolved)) {
            throw new \RuntimeException("Template not found: {$relative}");
        }

        $this->assertPathInsideRoot($rootReal, $resolved);

        return $resolved;
    }

    private function assertPathInsideRoot(string $rootReal, string $fileReal): void
    {
        $rootNorm = $this->normalizePath($rootReal);
        $fileNorm = $this->normalizePath($fileReal);

        if ($fileNorm !== $rootNorm && ! str_starts_with($fileNorm, $rootNorm . '/')) {
            throw new \RuntimeException('Template path escapes allowed view root.');
        }
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function assertValidShareKey(string $key): void
    {
        if (str_starts_with($key, '__mint_')) {
            throw new \InvalidArgumentException('Keys prefixed with __mint_ are reserved for the engine.');
        }

        if (! preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $key)) {
            throw new \InvalidArgumentException(
                'Shared variable name must be a valid PHP variable name.'
            );
        }
    }
}
