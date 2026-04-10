<html>
  <head>
    <meta charset="UTF-8" />
    <title>{{ $appName }} — Basic example</title>
  </head>
  <body class="{{{ $bodyClass ?? '' }}}">
    <main>
      <mint-yield name="layout" />
    </main>
  </body>
</html>

