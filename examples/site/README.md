# Mint example site

A small, styled demo that exercises layouts (`mint-wrap`), sections (`mint-yield`), DOM directives (`x:if`, `x:foreach`), text directives (`@if`), and registered custom components (`mint-alert`, `mint-stat-tile`, `mint-feature-callout`).

## Run locally

From the **repository root** (after `composer install`):

```bash
php -S localhost:8080 -t examples/site/public examples/site/public/index.php
```

The path to `index.php` is the [router script](https://www.php.net/manual/en/features.commandline.webserver.php): PHP tries the requested file first (e.g. `/css/style.css`), and anything else is handled by `index.php` so `/features` works without a real file.

Open [http://localhost:8080](http://localhost:8080) in a browser. Use [http://localhost:8080/features](http://localhost:8080/features) for the second page.

Compiled templates are written to `examples/site/var/` (ignored by git).

## Stack

- Vanilla CSS in `public/css/style.css` (no build step)
- PHP 8.1+ and this library only
- Save `views/**/*.php` as **UTF-8** so punctuation like `—`, `←`, and `·` compile correctly (the compiler tells libxml the markup is UTF-8)
