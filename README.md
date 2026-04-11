# Mint Template (Baueri\Mint)

A tiny PHP template compiler that supports:

- `{{ ... }}` echo expressions
- `@if / @elseif / @else / @endif`
- DOM directives like `x:if`, `x:foreach`, and `mint-` prefixed directive tags
- `mod-*` prefixed module tags for reusable UI components
- `MintView::share()` for data merged into every template render

## Install

```bash
composer require baueri/mint-template
```

## Basic usage

```php
use Baueri\Mint\Cache;
use Baueri\Mint\MintCompiler;
use Baueri\Mint\MintView;

require "vendor/autoload.php";

$cache = new Cache(__DIR__ . "/var/cache");
$compiler = new MintCompiler(__DIR__ . "/views");
$view = new MintView(__DIR__ . "/views", $cache, $compiler);

// Optional: variables merged into every render (per-render data overrides the same keys).
$view->share("appName", "My App");
$view->share([
    "supportEmail" => "hello@example.com",
]);

echo $view->render("index.php", ["name" => "Alice"]);
```

## Shared variables

Call `MintView::share()` to register data that is merged into **every** `render()` call. Typical uses are app name, current user, CSRF token, or config snippets you do not want to pass manually each time.

- **Override order:** `array_merge($shared, $renderData)` — keys in the second argument to `render()` win.
- **Propagation:** Shared data is included in `$__mint_data`, so `mint-include`, `mint-extend`, nested layouts, and template-backed `<mod-…>` view modules all see the same variables (module props still override shared keys for that template). For `mint-extend`, the inner markup is merged last as `slot`, so it overrides a `slot` key from the page data if present.
- **Reserved names:** Keys must be valid PHP variable names. Names starting with `__mint_` are rejected; they are reserved for the engine.

```php
$view->share("locale", "en_GB");

$view->share([
    "assetVersion" => "v12",
    "features" => ["beta" => true],
]);

// This render sees locale, assetVersion, features, and name; name is only for this call.
echo $view->render("page.php", ["name" => "Home"]);
```

`shared()` returns the current map (useful for tests or debugging).

## Template syntax

### Echo

`{{ ... }}` compiles to raw output: `<?php echo ...; ?>`.

Use **triple mustache** for escaped output:

```php
{{{ $html }}}
```

### Text directives

```php
@if ($user)
  Hello {{ e($user['name']) }}
@else
  Guest
@endif
```

### DOM directives

```html
<div x:if="{ $isLoggedIn }">Hello</div>

<ul>
  <li x:foreach="{ $users as $u }">{{ e($u['name']) }}</li>
</ul>
```

Repeat a fixed or dynamic count with a **0-based** index (`$i` runs from `0` to `count - 1`):

```html
<ul>
  <li x:repeat="{ $n as $i }">Item {{ $i }}</li>
</ul>
```

```html
<li x:repeat="{ 3 as $k }">{{ $k }}</li>
```

## Custom modules

Modules are PHP classes (extending `Baueri\Mint\Module\Module`) that receive a `Baueri\Mint\Context`.
The `Context` includes a reference to the current `MintView`, so modules can render other templates.
Module tags use the `mod-` prefix to distinguish them from `mint-` directives.

### Self-closing module

Template:

```html
<mod-user-card :user="{ $user }" />
```

Module:

```php
use Baueri\Mint\Module\Module;
use Baueri\Mint\Context;

final class UserCard extends Module
{
    public function render(Context $context): string
    {
        $user = $context->resolve('user');

        return '<div class="card">' . e($user['name'] ?? '') . '</div>';
    }
}
```

Template-backed module (recommended for larger modules):

```php
use Baueri\Mint\Module\Module;
use Baueri\Mint\Context;

final class UserCard extends Module
{
    public function render(Context $context): string
    {
        // Option A: use the helper from the base Module
        return $this->view($context, 'components/user-card.php', [
            'user' => $context->resolve('user'),
        ]);

        // Option B (equivalent): render via the view stored in Context
        // return $context->view()->render('components/user-card.php', [
        //     'user' => $context->resolve('user'),
        // ]);
    }
}
```

`views/components/user-card.php`:

```php
<div class="card">
  <div class="card-title">{{ e($user['name'] ?? '') }}</div>
  <div class="card-meta">{{ e($user['email'] ?? '') }}</div>
</div>
```

Register:

```php
$compiler->registerModule('user-card', UserCard::class);
```

### View-only modules

When a tag only needs a template (no extra PHP class), register the view path directly. The **first argument** is the tag suffix (plain name, hyphens allowed; no `::`). The **second argument** is a logical template path and follows `MintView::render()` (optional `namespace::path.php` — see [View namespaces](#view-namespaces-for-template-paths)):

```php
$compiler->registerViewModule('badge', 'components/badge.php');
$compiler->registerViewModule('acme-pill', 'acme::widgets/pill.php');
```

```html
<mod-badge :label="{ $title }">optional slot</mod-badge>
```

Props, `:props`, slots, and forwarded HTML attributes behave like class-based modules.

### Module names and collisions

Registering the same module suffix twice (`registerModule`, `registerViewModule`, or `registerDirective` with a module directive) throws `InvalidArgumentException`.

Module tag names do not use `::`; only template **paths** do (see below).

### View namespaces (for template paths only)

Register extra directories on `MintView` so logical paths like `acme::partials/x.php` resolve. That applies to `render()`, `mint-include`, `mint-extend`, `Module::view()`, and `registerViewModule`; it does not change module tag names.

```php
$view->registerNamespace('acme', __DIR__ . '/vendor/acme/widget/views');
```

Reference templates with a single `::` separator:

```php
$view->render('acme::partials/pill.php', $data);
```

```html
<mint-include path="acme::partials/pill.php" />
<mint-extend path="acme::layout.php"></mint-extend>
```

Relative paths may not use `..` segments; resolved files must stay under the base views path or the registered namespace directory.

### Layouts (`mint-extend`)

Wrap a page (or fragment) in another template using the same `path` convention as `mint-include` (include the `.php` suffix). Everything inside `<mint-extend>…</mint-extend>` is captured and passed to the layout as the **`slot`** key, so the shell can output it with `{{ $slot }}`. Optional `:prop` attributes are merged into the layout data like other template renders.

Use **`mint-section`** / **`mint-yield`** with a matching **`name`** for extra regions (for example a head block).

### Cache

`$cache->forget('index.php')` drops the compiled file for one logical template (the same name you pass to `render()`). `clear()` removes all compiled files under the cache path.

### `:props` shorthand

List simple PHP variables inside braces; each `$name` becomes context key `name` with that variable as the value (like object shorthand in JS):

```html
<mod-book :props="{$bookTitle, $author, $isbn}" />
```

Only tokens matching `\$\w+` are allowed (no expressions). You can combine with explicit `:attr` values; **explicit attributes override** the same key from `:props`.

### Module with slots

Template:

```html
<mod-alert :type="error">
  some error
</mod-alert>
```

Module (slot content is available via `$context->slot()`):

```php
use Baueri\Mint\Module\Module;
use Baueri\Mint\Context;

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

Template-backed slot module:

```php
use Baueri\Mint\Module\Module;
use Baueri\Mint\Context;

final class Alert extends Module
{
    public function render(Context $context): string
    {
        return $this->view($context, 'components/alert.php', [
            'type' => (string) $context->resolve('type', 'info'),
            // slot is auto-provided as $slot unless you override it
        ]);
    }
}
```

`views/components/alert.php`:

```php
<div class="alert alert-{{ e($type) }}">
  {{ $slot }}
</div>
```

Register:

```php
$compiler->registerModule('alert', Alert::class);
```

## Development

```bash
composer install
vendor/bin/phpunit
```

## Examples

See `examples/README.md`.
