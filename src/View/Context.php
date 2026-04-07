<?php

declare(strict_types=1);

namespace Mint\View;

class Context
{
    protected array $data;
    protected MintView $view;
    protected ?string $slot;

    public function __construct(array $data, MintView $view, ?string $slot = null)
    {
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

    public function slot(): ?string
    {
        return $this->slot;
    }

    public function view(): ContextView
    {
        return new ContextView($this);
    }

    public function withSlot(array $data): array
    {
        if ($this->slot !== null && !array_key_exists('slot', $data)) {
            $data['slot'] = $this->slot;
        }

        return $data;
    }

    public function baseView(): MintView
    {
        return $this->view;
    }
}
