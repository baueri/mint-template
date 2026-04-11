<html>
  <head>
    <meta charset="UTF-8" />
    <mint-yield name="meta-head" />
    <style>
      .demo-components {
        background-color:rgb(240, 240, 240);
        padding: 1rem;
        border-radius: 0.5rem;
        box-shadow: 0 0 10px 0 rgba(0, 0, 0, 0.1);
        margin: 1rem;
        max-width: 1000px;
        margin: 0 auto;
        text-align: center;
      }
    </style>
  </head>
  <body class="{{{ $bodyClass ?? '' }}}">
    <main>
      {{ $slot }}
    </main>
  </body>
</html>

