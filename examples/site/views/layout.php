<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title }} · Mint</title>
    <link rel="stylesheet" href="/css/style.css" />
</head>
<body class="{{ $bodyClass ?? '' }}">
<a class="skip-link" href="#main">Skip to content</a>

<div class="site">
    <header class="site-header">
        <div class="site-header__inner">
            <a class="logo" href="/">
                <span class="logo__mark">◆</span>
                <span class="logo__text">Mint</span>
            </a>
            <nav class="nav" aria-label="Primary">
                <a href="/" class="nav__link{{ $nav_active === 'home' ? ' is-active' : '' }}">Showcase</a>
                <a href="/features" class="nav__link{{ $nav_active === 'features' ? ' is-active' : '' }}">Features</a>
            </nav>
        </div>
    </header>

    <main id="main" class="site-main">
        {{ $slot }}
    </main>

    <footer class="site-footer">
        <p>Mint template demo · {{ date('Y') }} · Built with vanilla CSS</p>
    </footer>
</div>
</body>
</html>
