# Mint Template (Baueri\Mint)

A tiny PHP template compiler that supports:

- `{{ ... }}` echo expressions
- `@if / @elseif / @else / @endif`
- DOM directives like `x:if`, `x:foreach`, and `mint-` prefixed component tags
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
- **Propagation:** Shared data is included in `$__mint_data`, so `mint-include`, `mint-wrap`, nested layouts, and template-backed `<mint-…>` view components all see the same variables (component props still override shared keys for that template).
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

## Custom components

Custom components are just PHP classes (extending `Baueri\Mint\Component\Component`) that receive a `Baueri\Mint\Context`.
The `Context` includes a reference to the current `MintView`, so components can render other templates.

### Self-closing component

Template:

```html
<mint-user-card :user="{ $user }" />
```

Component:

```php
use Baueri\Mint\Component\Component;
use Baueri\Mint\Context;

final class UserCard extends Component
{
    public function render(Context $context): string
    {
        $user = $context->resolve('user');

        return '<div class="card">' . e($user['name'] ?? '') . '</div>';
    }
}
```

Template-backed component (recommended for larger components):

```php
use Baueri\Mint\Component\Component;
use Baueri\Mint\Context;

final class UserCard extends Component
{
    public function render(Context $context): string
    {
        // Option A: use the helper from the base Component
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
$compiler->registerComponent('user-card', UserCard::class);
```

### View-only components

When a tag only needs a template (no extra PHP class), register the view path directly. The **first argument** is the tag suffix (plain name, hyphens allowed; no `::`). The **second argument** is a logical template path and follows `MintView::render()` (optional `namespace::path.php` — see [View namespaces](#view-namespaces-for-template-paths)):

```php
$compiler->registerViewComponent('badge', 'components/badge.php');
$compiler->registerViewComponent('acme-pill', 'acme::widgets/pill.php');
```

```html
<mint-badge :label="{ $title }">optional slot</mint-badge>
```

Props, `:props`, slots, and forwarded HTML attributes behave like class-based components.

### Component names and collisions

Registering the same component suffix twice (`registerComponent`, `registerViewComponent`, or `registerDirective` with a mint custom-tag directive) throws `InvalidArgumentException`.

These suffixes are reserved for built-in tags: `include`, `wrap`, `section`, `yield`, `attrs`. Third-party packages should use a **vendor prefix** in the suffix (for example `billing-invoice-row` → `<mint-billing-invoice-row>`) to avoid clashes with the app or other packages. Tag suffixes are **not** namespaced with `::`; only template path strings use that form.

### View namespaces (for template paths only)

Register extra view directories on `MintView` (instance-based; pass `MintView` into your package services from the app container). This affects **template resolution only** (`render`, `mint-include`, `mint-wrap`, `Component::view()`, `registerViewComponent`’s template argument). It does **not** change how you choose `registerComponent` / `registerViewComponent` tag names.

```php
$view->registerNamespace('acme', __DIR__ . '/vendor/acme/widget/views');
```

Reference templates with a single `::` separator:

```php
$view->render('acme::partials/pill.php', $data);
```

```html
<mint-include path="acme::partials/pill.php" />
<mint-wrap view="acme::layout"></mint-wrap>
```

Relative paths may not use `..` segments; resolved files must stay under the base views path or the registered namespace directory.

### Compiled cache layout

Under the hood, compiled templates are written to nested subdirectories of the cache path (by hash of the logical template name) so large projects do not put thousands of files in one folder. Callers still use the same logical names and `Cache` API as before.

Each compiled file starts with a short comment naming the **logical template** (as passed to `render()`) and the **absolute source path**, which makes debugging easier.

`Cache::forget($logicalTemplate)` removes one compiled artifact (for example after a deploy hook).

### `:props` shorthand

List simple PHP variables inside braces; each `$name` becomes context key `name` with that variable as the value (like object shorthand in JS):

```html
<mint-book :props="{$bookTitle, $author, $isbn}" />
```

Only tokens matching `\$\w+` are allowed (no expressions). You can combine with explicit `:attr` values; **explicit attributes override** the same key from `:props`.

### Component with slots

Template:

```html
<mint-alert :type="error">
  some error
</mint-alert>
```

Component (slot content is available via `$context->slot()`):

```php
use Baueri\Mint\Component\Component;
use Baueri\Mint\Context;

final class Alert extends Component
{
    public function render(Context $context): string
    {
        $type = (string) $context->resolve('type', 'info');
        $slot = $context->slot() ?? '';

        return '<div class="alert alert-' . e($type) . '">' . $slot . '</div>';
    }
}
```

Template-backed slot component:

```php
use Baueri\Mint\Component\Component;
use Baueri\Mint\Context;

final class Alert extends Component
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
$compiler->registerComponent('alert', Alert::class);
```

## Development

```bash
composer install
vendor/bin/phpunit
```

## Examples

See `examples/README.md`.

