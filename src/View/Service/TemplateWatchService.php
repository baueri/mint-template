<?php

declare(strict_types=1);

namespace Mint\View\Service;

use Mint\View\Cache;
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
                var_dump('ch: ', $file, '@@@@@@@@@@');
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
        $this->cache->deleteByPrefix($templateFile);
    }

    private function cacheDir(): string
    {
        $ref = new \ReflectionClass($this->cache);
        $prop = $ref->getProperty('path');
        $prop->setAccessible(true);

        return $prop->getValue($this->cache);
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
