<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Support;

final class TempViews
{
    public static function makeDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . '_' . bin2hex(random_bytes(8));
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create temp dir: {$dir}");
        }
        return $dir;
    }

    public static function put(string $viewsDir, string $name, string $contents): string
    {
        $path = $viewsDir . '/' . $name;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, $contents);
        return $path;
    }
}

