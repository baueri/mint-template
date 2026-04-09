<html>
  <head>
    <title>{{ $title }}</title>
  </head>
  <body>
    <header>
      <h1>{{ $title }}</h1>
    </header>

    <main>
      <mint-yield name="layout" />
    </main>

    <footer>
      <mint-yield name="footer" />
      Generated at {{ date('c') }}
    </footer>
  </body>
</html>

