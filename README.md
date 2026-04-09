# Mint Template (Baueri\Mint)

A tiny PHP template compiler that supports:

- `{{ ... }}` echo expressions
- `@if / @elseif / @else / @endif`
- DOM directives like `x:if`, `x:foreach`, and `mint-` prefixed component tags

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

echo $view->render("index.php", ["name" => "Alice"]);
```

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
$compiler->registerComponentDirective('user-card', UserCard::class);
```

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
$compiler->registerComponentDirective('alert', Alert::class);
```

## Development

```bash
composer install
vendor/bin/phpunit
```

## Examples

See `examples/README.md`.

