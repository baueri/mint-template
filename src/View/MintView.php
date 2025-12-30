<?php

declare(strict_types=1);

namespace Mint\View;

class MintView implements View
{
    public function __construct(
        private readonly string $viewsPath,
        private readonly Cache  $cache,
        private readonly MintCompiler $compiler
    ) {}

    public function render(string $template, array $data = []): string
    {
        $source = $this->viewsPath . '/' . $template;

//        if (!$this->cache->isFresh($template, $source)) {
            $php = $this->compiler->compile($source);
            $this->cache->write($template, $php);
//        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $this->cache->compiledPath($template);
        return ob_get_clean();
    }
}
