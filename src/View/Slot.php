<?php

declare(strict_types=1);

namespace Baueri\Mint;

/**
 * Module slot content: default/body via __toString() or $slot->body; named mint-slot regions via __get().
 */
final class Slot
{
    /**
     * @param array<string, string> $slots keyed by name; key "body" is the main (default) slot
     */
    public function __construct(private array $slots = [])
    {
    }

    public function __toString(): string
    {
        return $this->slots['body'] ?? '';
    }

    public function __get(string $name): string
    {
        return $this->slots[$name] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->slots;
    }
}
