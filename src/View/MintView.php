<?php

declare(strict_types=1);

namespace Baueri\Mint;

class MintView implements View
{
    private const EVT_BEFORE_RENDER = 'beforeRender';

    /** After successful render (see {@see onRender()}). */
    private const EVT_RENDER = 'render';

    private const EVT_COMPILE = 'compile';

    /** @var array<string, string> namespace => absolute directory */
    private array $namespaces = [];

    /** @var array<string, mixed> merged into every {@see render()} (per-render keys override) */
    private array $shared = [];

    /**
     * Event name => listeners (FIFO). Signatures per event:
     * - {@see self::EVT_BEFORE_RENDER}: (string $template, string $compiledPath, array &$data): void
     * - {@see self::EVT_RENDER}: (string $template, string $compiledPath, float $ms, int $bytes): void
     * - {@see self::EVT_COMPILE}: (string $template, string $sourcePath, string $compiledPath): void
     *
     * @var array<string, list<callable>>
     */
    private array $listeners = [
        self::EVT_BEFORE_RENDER => [],
        self::EVT_RENDER => [],
        self::EVT_COMPILE => [],
    ];

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

    /**
     * Register a listener that fires before each template render (after data merge, before the compiled file runs).
     *
     * Callback signature: (string $template, string $compiledPath, array &$data): void
     */
    public function onBeforeRender(callable $listener): void
    {
        $this->pushListener(self::EVT_BEFORE_RENDER, $listener);
    }

    /**
     * Register a listener that fires after each template render.
     *
     * Callback signature: (string $template, string $compiledPath, float $ms, int $bytes): void
     */
    public function onRender(callable $listener): void
    {
        $this->pushListener(self::EVT_RENDER, $listener);
    }

    /**
     * Register a listener that fires whenever a template is (re)compiled and written to cache.
     *
     * Callback signature: (string $template, string $sourcePath, string $compiledPath): void
     */
    public function onCompile(callable $listener): void
    {
        $this->pushListener(self::EVT_COMPILE, $listener);
    }

    public function render(string $template, array $data = []): string
    {
        $source = $this->resolveTemplateSourcePath($template);

        if (! $this->cache->isFresh($template, $source)) {
            $php = $this->compiler->compile($source);
            $this->cache->write($template, $php, $source);

            $compiledPath = $this->cache->compiledPath($template);
            foreach ($this->listeners[self::EVT_COMPILE] as $listener) {
                $listener($template, $source, $compiledPath);
            }
        }

        $data = array_merge($this->shared, $data);

        if (! array_key_exists('slot', $data) && isset($__mint_slot)) {
            $data['slot'] = $__mint_slot;
        }

        $compiledPath = $this->cache->compiledPath($template);
        foreach ($this->listeners[self::EVT_BEFORE_RENDER] as $listener) {
            $listener($template, $compiledPath, $data);
        }

        $__mint_data = $data;
        $__mint_env = $data['__mint_env'] ?? new RenderContext();

        extract($data, EXTR_SKIP);

        $__mint_view = $this;
        $__mint_compiled_path = $compiledPath;

        $__mint_start = microtime(true);
        ob_start();
        include $__mint_compiled_path;
        $__mint_output = ob_get_clean();

        foreach ($this->listeners[self::EVT_RENDER] as $listener) {
            $listener($template, $compiledPath, (microtime(true) - $__mint_start) * 1000, strlen($__mint_output));
        }

        return $__mint_output;
    }

    private function pushListener(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
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
