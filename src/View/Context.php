<?php

declare(strict_types=1);

namespace Baueri\Mint;

class Context
{
    protected array $data;
    protected MintView $view;
    protected ?Slot $slot;

    public function __construct(array $data, MintView $view, Slot|string|null $slot = null)
    {
        if (is_string($slot)) {
            $slot = new Slot(['body' => $slot]);
        }

        $this->data = $data;
        $this->view = $view;
        $this->slot = $slot;
    }

    public function resolve(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    /**
     * Slot bag for the module body (default + named mint-slot regions), or null when the module
     * has no slot body (self-closing).
     */
    public function slot(): ?Slot
    {
        return $this->slot;
    }

    public function view(): ContextView
    {
        return new ContextView($this);
    }

    public function withSlot(array $data): array
    {
        if (! array_key_exists('slot', $data)) {
            $data['slot'] = $this->slot ?? new Slot();
        }

        return $data;
    }

    public function baseView(): MintView
    {
        return $this->view;
    }
}
