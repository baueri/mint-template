<?php

declare(strict_types=1);

namespace Mint\View;

class Context
{
    public function __construct(
        private array $data = []
    ) {}

    public function with(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->data[$key] = $value;
        return $clone;
    }

    public function resolve(string $path): mixed
    {
        $segments = preg_split('/->|\./', $path);
        $value = $this->data[array_shift($segments)] ?? null;

        foreach ($segments as $seg) {
            if (is_object($value) && isset($value->$seg)) {
                $value = $value->$seg;
            } elseif (is_array($value) && array_key_exists($seg, $value)) {
                $value = $value[$seg];
            } else {
                return null;
            }
        }

        return $value;
    }
}
