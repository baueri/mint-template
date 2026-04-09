<?php

declare(strict_types=1);

namespace Baueri\Mint\Service;

use Baueri\Mint\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TemplateWatchService
{
    private array $lastState = [];

    public function __construct(
        private readonly string $templatePath,
        private readonly Cache  $cache,
        private readonly int $intervalSeconds = 1
    ) {}

    public function run(): void
    {
        echo "Mint template watcher started\n";

        $this->snapshot();

        while (true) {
            sleep($this->intervalSeconds);
            $this->checkForChanges();
        }
    }

    private function snapshot(): void
    {
        foreach ($this->allTemplates() as $file) {
            $this->lastState[$file] = filemtime($file);
        }
    }

    private function checkForChanges(): void
    {
        foreach ($this->allTemplates() as $file) {
            $mtime = filemtime($file);
            if (!isset($this->lastState[$file])) {
                $this->onChanged($file, 'created');
            } elseif ($this->lastState[$file] !== $mtime) {
                $this->onChanged($file, 'modified');
            }

            $this->lastState[$file] = $mtime;
        }
    }

    private function onChanged(string $file, string $type): void
    {
        echo strtoupper($type) . ": {$file}\n";
        $this->invalidateCacheFor($file);
    }

    private function invalidateCacheFor(string $templateFile): void
    {
        // Current cache keys are not derived from absolute file paths, so the
        // simplest correct invalidation is clearing the compiled cache.
        $this->cache->clear();
    }

    private function allTemplates(): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->templatePath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                yield $file->getPathname();
            }
        }
    }
}
