# Mint Template

**Mint** is a small PHP template engine: you write HTML-ish templates with mustaches and directives, the library compiles them to plain PHP, caches the result, and `include`s that file on each render. It uses PHP’s `ext-dom` / libxml for structure (layouts, `x:if`, includes) and stays usable without a framework—only `MintView`, `MintCompiler`, and a `Cache` implementation.

**PHP 8.1+**, `ext-dom`, `ext-libxml`.

**Documentation:** [mint.ivanbauer.hu](https://mint.ivanbauer.hu)

## Install

```bash
composer require baueri/mint-template
```

Composer loads `e()` for HTML escaping from `src/View/helpers.php`. If you already define `e()` yourself, yours wins.

---

## First run

**`bootstrap.php`**

```php
<?php

declare(strict_types=1);

use Baueri\Mint\Cache;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;

require __DIR__ . '/vendor/autoload.php';

$viewsPath = __DIR__ . '/views';
$cachePath = __DIR__ . '/var/cache/views';

$view = new MintView(
    viewsPath: $viewsPath,
    cache: new Cache($cachePath),
    compiler: new MintCompiler($viewsPath),
);

$view->share('appName', 'My App');
```

**`public/index.php`** (or any front controller)

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

echo $view->render('welcome.php', ['name' => 'Ada']);
```

**`views/welcome.php`**

```html
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>{{ $appName }}</title>
  </head>
  <body>
    <p>Hello, {{{ $name }}}.</p>
  </body>
</html>
```

The first argument to `render()` is a **logical path** under `viewsPath` (e.g. `welcome.php`). The engine recompiles when the source file is newer than the cached PHP.

---

## Output: `{{` and `{{{`

| Syntax | Compiles to | Typical use |
|--------|-------------|-------------|
| `{{ $x }}` | `<?php echo $x; ?>` | HTML you trust (or that is already safe) |
| `{{{ $x }}}` | `<?php echo e($x); ?>` | Text from users or the database |

**`views/demo.php`**

```html
<p>Raw (trusted markup): {{ $trustedHtml }}</p>
<p>Escaped (user title): {{{ $userTitle }}}</p>
```

In **attribute values**, the same rules apply: `{{ }}` vs `{{{ }}}` inside quotes.

---

## Shared data

`MintView::share()` merges into **every** `render()` call. Keys passed to `render()` override shared keys.

```php
$view->share('locale', 'en_GB');
$view->share(['assetVersion' => 'v3']);

echo $view->render('page.php', ['title' => 'Home']); // sees locale, assetVersion, title
```

Reserved: keys starting with `__mint_`, and names that are not valid PHP variable names, are rejected.

---

## Layout with `mint-extend` and `{{ $slot }}`

Wrap a page in a parent template. Everything inside `<mint-extend>…</mint-extend>` is captured and passed to the layout as **`$slot`**. The `path` attribute is a logical template name (same rules as `render()`), including `.php`.

**`views/layouts/app.php`** (parent)

```html
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>{{ $title ?? $appName }}</title>
  </head>
  <body class="{{{ $bodyClass ?? '' }}}">
    <header><strong>{{ $appName }}</strong></header>
    <main>{{ $slot }}</main>
  </body>
</html>
```

**`views/pages/about.php`** (child)

```html
<mint-extend path="layouts/app.php" :title="{'About us'}">
  <article>
    <h1>About</h1>
    <p>This body becomes <code>$slot</code> in the layout.</p>
  </article>
</mint-extend>
```

Optional attributes on `<mint-extend>` whose names start with `:` are passed into the layout as data. Kebab-case names become camelCase keys (e.g. `:body-class="{ $class }"` → `$bodyClass` in the layout). String literals can be written as `:title="{'About us'}"` (expression inside `{ ... }`).

**`public/about.php`**

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

echo $view->render('pages/about.php', [
    'appName' => 'Acme',
    'bodyClass' => 'page-about',
]);
```

---

## Named sections: `mint-section` and `mint-yield`

Child templates can push named fragments into a **`RenderContext`**; the layout prints them with `<mint-yield name="…" />`. Multiple sections with the same name are concatenated in order.

**`views/layouts/site.php`**

```html
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>{{ $title }}</title>
    <mint-yield name="head" />
  </head>
  <body>
    <header><mint-yield name="heading" /></header>
    <main>{{ $slot }}</main>
    <footer><mint-yield name="footer" /></footer>
  </body>
</html>
```

**`views/pages/article.php`**

```html
<mint-extend path="layouts/site.php" :title="{'Mint sections'}">
  <mint-section name="head">
    <meta name="description" content="Demo of mint-section / mint-yield" />
  </mint-section>

  <mint-section name="heading">
    <h1>{{ $title }}</h1>
  </mint-section>

  <article>{{ $bodyHtml }}</article>

  <mint-section name="footer">
    <p>Thanks for reading.</p>
  </mint-section>
</mint-extend>
```

```php
echo $view->render('pages/article.php', [
    'title' => 'Mint sections',
    'bodyHtml' => '<p>App-supplied HTML.</p>',
]);
```

Use `{{{ $bodyHtml }}}` instead of `{{ $bodyHtml }}` when the fragment is plain text or must be escaped.

---

## Partials: `mint-include`

Renders another template with the **current** data merged with optional props. Self-closing.

**`views/partials/tag.php`**

```html
<span class="tag">{{{ $label }}}</span>
```

**`views/pages/tags-demo.php`**

```html
<div>
  <?php $label = 'PHP'; ?>
  <mint-include path="partials/tag.php" :props="{$label}" />
</div>
```

`:props="{$a, $b}"` maps simple variable names to keys `a`, `b`. Explicit `:key="{ $expr }"` overrides the same key from `:props`.

---

## Conditionals and loops

### Text directives (`@…`)

Processed on the raw template **before** HTML parsing:

```html
@if ($showGreeting)
  <p>Hello, {{{ $name }}}.</p>
@else
  <p>Hi there.</p>
@endif

@foreach ($items as $item)
  <li>{{{ $item }}}</li>
@endforeach
```

### DOM attributes (`x:if`, `x:foreach`, `x:repeat`)

`x:if` uses the first `{ ... }` in the attribute value as a PHP expression:

```html
<p x:if="{ $user !== null }">Logged in as {{{ $user['name'] }}}.</p>
```

`x:foreach` must be **only** `{ $items as $item }` (whole attribute):

```html
<ul>
  <li x:foreach="{ $products as $p }">{{{ $p['name'] }}} — {{ $p['price'] }}</li>
</ul>
```

`x:repeat` repeats a block with a **0-based** index:

```html
<ul>
  <li x:repeat="{ $count as $i }">Item {{ $i }}</li>
</ul>
```

```html
<li x:repeat="{ 5 as $k }">{{ $k }}</li>
```

Raw `<?php … ?>` blocks in templates are preserved through compilation.

---

## Components: `<mod-*>` modules

Register a suffix once on the compiler; the tag becomes `<mod-{suffix}>`. Props use `:name="{ $expr }"` or string literals `:title="{'Hello'}"`. Class-based modules implement `Module::render(Context $context)`.

**`src/Components/Alert.php`**

```php
<?php

declare(strict_types=1);

namespace App\Components;

use Baueri\Mint\Context;
use Baueri\Mint\Module\Module;

final class Alert extends Module
{
    public function render(Context $context): string
    {
        $type = (string) $context->resolve('type', 'info');
        $slot = $context->slot() ?? '';

        return '<div class="alert alert-' . e($type) . '">' . $slot . '</div>';
    }
}
```

**Registration and render**

```php
$compiler->registerModule('alert', \App\Components\Alert::class);

echo $view->render('page.php', []);
```

**`views/page.php`**

```html
<mod-alert :type="{'error'}">
  Something went wrong.
</mod-alert>

<mod-alert :type="{'success'}" />
```

### Template-only modules

When a component is just a view, register the template path:

```php
$compiler->registerViewModule('badge', 'components/badge.php');
```

```html
<mod-badge :label="{ $title }" />
```

Forwarded HTML attributes (not starting with `:` or `x:`) are available as `$attributes` in template-backed modules via `Module::view()` / `$context`.

---

## Multiple view roots (namespaces)

```php
$view->registerNamespace('shop', __DIR__ . '/vendor/shop/views');
```

Logical paths use a single `::` separator:

```php
echo $view->render('shop::checkout/summary.php', $data);
```

```html
<mint-include path="shop::partials/cart-line.php" />
<mint-extend path="shop::layouts/minimal.php"></mint-extend>
```

Paths cannot contain `..` and must resolve inside the base `viewsPath` or the registered namespace directory.

---

## Cache

```php
$view->cache->forget('pages/home.php'); // drop one compiled template
$view->cache->clear();                  // empty the cache directory
```

---

## CLI

Clear compiled files (path is your cache directory):

```bash
vendor/bin/mint clear --cache=var/cache/views
```

---

## Programmatic CLI (`MintCli`)

Use inside your app or custom console command when the compiler already has all modules registered:

```php
use Baueri\Mint\MintCli;

$cli = new MintCli(fn (string $line) => print $line . PHP_EOL);

$cli->clear($view->cache);
$cli->compileAll($view->compiler, $view->viewsPath, $view->cache);
$cli->watch($view->compiler, $view->viewsPath, $view->cache, pollIntervalMs: 500);
```

`$view->viewsPath`, `$view->cache`, and `$view->compiler` are public `readonly` properties.

---

## Lifecycle hooks

```php
$view->onBeforeRender(function (string $template, string $compiledPath, array &$data): void {
    // mutate $data before the compiled template runs
});

$view->onRender(function (string $template, string $compiledPath, float $ms, int $bytes): void {
    // after successful render; $ms is milliseconds, $bytes is output length
});

$view->onCompile(function (string $template, string $sourcePath, string $compiledPath): void {
    // after a template is (re)compiled and written to cache
});
```

---

## Extending the compiler

- `MintCompiler::registerDirective(DOMDirective $directive)` — custom elements processed in the DOM pass.
- `MintCompiler::registerTextDirective(TextDirectiveInterface $directive)` — custom `@`-style transforms on the source string before parsing.

Implement the same interfaces as the built-in directives under `src/View/Directive/`.

---

## Development

```bash
composer install
vendor/bin/phpunit
```

Further runnable demos live under [`examples/`](examples/).
