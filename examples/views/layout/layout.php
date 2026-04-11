<html>
  <head>
    <title>{{ $title }}</title>
  </head>
  <body class="{{{ $bodyClass ?? '' }}}">
    <header>
      <nav>
        <a href="#" aria-current="page">Home</a>
        <span>·</span>
        <a href="#">Docs</a>
      </nav>

      <div class="layout-heading">
        <mint-yield name="heading" />
      </div>
    </header>

    <main>
      {{ $slot }}
    </main>

    <footer>
      <mint-yield name="footer" />
      Generated at {{ date('c') }}
    </footer>
  </body>
</html>

