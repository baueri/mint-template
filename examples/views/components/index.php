<mint-extend path="layout.php" :body-class="{'demo-components'}">
  <mint-section name="meta-head">
    <title>Modules demo</title>
    <meta name="description" content="Modules demo">
    <meta name="keywords" content="Modules, demo, mint">
    <meta name="author" content="Mint">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  </mint-section>
  <div>
    <h1>Modules demo</h1>

    <mod-user-card :user="{ $user }" />

    <mod-alert :type="error" style="border:1px solid red;">
      Something went wrong for {{ $user['name'] }}.
    </mod-alert>
  </div>
</mint-extend>

