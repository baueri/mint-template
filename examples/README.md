## Examples

These examples are meant to replace the current `app/` demo folder.

### Setup

From the project root:

```bash
composer install
```

### Run examples

All examples are plain PHP scripts that render templates using `Baueri\Mint\MintView`.

```bash
php examples/01-basic.php
php examples/02-components.php
php examples/03-layout-wrap.php
```

Each example writes compiled templates to `examples/var/cache-*`.

### Full mini-site (styled)

See [`examples/site/README.md`](site/README.md) for a small two-page demo with vanilla CSS and a documented `php -S` command.

