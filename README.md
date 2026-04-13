# Mint Template

**Mint** compiles HTML-ish templates to plain PHP, caches them, and renders with `MintView`. It uses PHP’s `ext-dom` / libxml for structure (layouts, conditionals, includes) and works without a framework.

**PHP 8.1+**, `ext-dom`, `ext-libxml`.

**Full documentation:** [mint.ivanbauer.hu](https://mint.ivanbauer.hu)

## Install

```bash
composer require baueri/mint-template
```

Composer loads `e()` for HTML escaping from `src/View/helpers.php`. If you already define `e()` yourself, yours wins.

---

## Quick start

```php
<?php

declare(strict_types=1);

use Baueri\Mint\Cache;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;

require __DIR__ . '/vendor/autoload.php';

$view = new MintView(
    viewsPath: __DIR__ . '/views',
    cache: new Cache(__DIR__ . '/var/cache/views'),
    compiler: new MintCompiler(__DIR__ . '/views'),
);

echo $view->render('welcome.php', ['name' => 'Ada']);
```

```html
<!-- views/welcome.php -->
<p>Hello, {{{ $name }}}.</p>
```

The first argument to `render()` is a logical path under `viewsPath` (for example `welcome.php`). Templates recompile when the source file is newer than the cache.

---

## Syntax at a glance

| Topic | Notes |
|--------|--------|
| **`{{ $x }}` / `{{{ $x }}}`** | Raw vs escaped output (same idea inside attributes). |
| **`mint-extend` / `{{ $slot }}`** | Page layouts; body becomes the default slot. |
| **`mint-section` / `mint-yield`** | Named layout fragments (global to the render). |
| **`<mod-*>` modules** | Register PHP classes or view templates on `MintCompiler`; props use `:name="{ $expr }"`. |
| **`<mint-slot name="…">`** | Named regions inside a module body; `$slot` is a `Slot` object (`__toString` / `$slot->body` = default; `$slot->head` etc. for names). `name="body"` is reserved. |
| **`x:if`, `x:foreach`, `x:repeat`** | DOM conditionals and loops. |
| **`@if` / `@foreach`** | Text directives applied before HTML parse. |
| **`mint-include`** | Partials with merged data and props. |

Details, edge cases, namespaces (`shop::path.php`), cache, CLI (`vendor/bin/mint`), lifecycle hooks, and extending the compiler are covered in the **[documentation site](https://mint.ivanbauer.hu)**.

---

## Shared data

`MintView::share()` merges into every `render()`; keys passed to `render()` override shared keys. Reserved: keys starting with `__mint_`, and names that are not valid PHP variable names.

---

## Examples

Runnable scripts live under [`examples/`](examples/) (basic render, components, layouts, **named slots**). A small styled site is under [`examples/site/`](examples/site/).

---

## Development

```bash
composer install
vendor/bin/phpunit
```
