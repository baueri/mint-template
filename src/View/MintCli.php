<?php

declare(strict_types=1);

namespace Baueri\Mint;

class MintCli
{
    /** @var callable(string): void */
    private $output;

    /**
     * @param callable(string): void|null $output  Receives one line at a time (no trailing newline).
     *                                              Defaults to writing to stdout.
     */
    public function __construct(?callable $output = null)
    {
        $this->output = $output ?? static fn(string $line) => print($line . PHP_EOL);
    }

    /**
     * Remove all compiled templates from the cache.
     */
    public function clear(CacheInterface $cache): void
    {
        $cache->clear();
        $this->writeln('Cache cleared.');
    }

    /**
     * Compile every *.php template found under $viewsPath and write results to the cache.
     */
    public function compileAll(MintCompiler $compiler, string $viewsPath, CacheInterface $cache): void
    {
        $files = $this->findTemplates($viewsPath);
        $count = 0;

        foreach ($files as $file) {
            $template = $this->relativeTemplateName($file, $viewsPath);
            try {
                $php = $compiler->compile($file);
                $cache->write($template, $php, $file);
                $this->writeln("Compiled: {$template}");
                $count++;
            } catch (\Throwable $e) {
                $this->writeln("Error in {$template}: " . $e->getMessage());
            }
        }

        $this->writeln("Done. {$count} template(s) compiled.");
    }

    /**
     * Poll $viewsPath for changed *.php files and recompile them.
     *
     * Runs until the process is terminated. If ext-pcntl is available,
     * SIGINT (Ctrl+C) is handled for a clean exit message.
     *
     * @param int $pollIntervalMs  How often to check for changes, in milliseconds.
     */
    public function watch(
        MintCompiler $compiler,
        string $viewsPath,
        CacheInterface $cache,
        int $pollIntervalMs = 500
    ): void {
        $this->writeln("Watching {$viewsPath} for changes. Press Ctrl+C to stop.");

        if (function_exists('pcntl_signal')) {
            $output = $this->output;
            pcntl_signal(SIGINT, static function () use ($output): void {
                ($output)('Stopped.');
                exit(0);
            });
        }

        $mtimes = [];

        while (true) {
            $files = $this->findTemplates($viewsPath);

            foreach ($files as $file) {
                $mtime = filemtime($file);

                if (isset($mtimes[$file]) && $mtimes[$file] !== $mtime) {
                    $template = $this->relativeTemplateName($file, $viewsPath);
                    try {
                        $php = $compiler->compile($file);
                        $cache->write($template, $php, $file);
                        $this->writeln('[' . date('H:i:s') . "] Recompiled: {$template}");
                    } catch (\Throwable $e) {
                        $this->writeln('[' . date('H:i:s') . "] Error in {$template}: " . $e->getMessage());
                    }
                }

                $mtimes[$file] = $mtime;
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep($pollIntervalMs * 1000);
        }
    }

    private function writeln(string $message): void
    {
        ($this->output)($message);
    }

    /**
     * @return list<string>
     */
    private function findTemplates(string $viewsPath): array
    {
        if (! is_dir($viewsPath)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function relativeTemplateName(string $absolutePath, string $viewsPath): string
    {
        $viewsPath = rtrim(str_replace('\\', '/', $viewsPath), '/');
        $absolutePath = str_replace('\\', '/', $absolutePath);

        return ltrim(substr($absolutePath, strlen($viewsPath)), '/');
    }
}
