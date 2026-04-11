# Mint example site

Small styled demo: layouts (`mint-wrap`, `{{ $slot }}`), named sections (`mint-yield`), DOM directives (`x:if`, `x:foreach`), text directives (`@if`), and registered components (`mint-alert`, `mint-stat-tile`, `mint-feature-callout`).

## Run locally

From the repository root (after `composer install`):

```bash
php -S localhost:8080 -t examples/site/public examples/site/public/index.php
```

The second argument is PHP’s router script: files under `public/` are served directly; everything else goes through `index.php` (so `/features` works without a dedicated file).

- [http://localhost:8080](http://localhost:8080)
- [http://localhost:8080/features](http://localhost:8080/features)

## Stack

- Vanilla CSS in `public/css/style.css` (no build step)
- PHP 8.1+ and this library only
- Save views as **UTF-8** so punctuation like `—`, `←`, and `·` compiles correctly
