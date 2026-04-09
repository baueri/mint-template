<?php

declare(strict_types=1);

namespace Baueri\Mint\Tests\Unit;

use Baueri\Mint\Cache;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase
{
    public function testWriteCreatesCompiledFile(): void
    {
        $dir = sys_get_temp_dir() . '/mint_cache_' . bin2hex(random_bytes(8));
        $cache = new Cache($dir);

        $file = $cache->write('index.php', '<?php echo 1; ?>');

        $this->assertFileExists($file);
        $this->assertStringEndsWith('.php', $file);
    }

    public function testClearRemovesCompiledFiles(): void
    {
        $dir = sys_get_temp_dir() . '/mint_cache_' . bin2hex(random_bytes(8));
        $cache = new Cache($dir);

        $file = $cache->write('index.php', '<?php echo 1; ?>');
        $this->assertFileExists($file);

        $cache->clear();

        $this->assertFileDoesNotExist($file);
    }
}

