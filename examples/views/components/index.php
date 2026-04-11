<mint-extend path="layout.php" :body-class="{'demo-components'}">
  <mint-section name="meta-head">
    <title>Components demo</title>
    <meta name="description" content="Components demo">
    <meta name="keywords" content="Components, demo, mint">
    <meta name="author" content="Mint">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  </mint-section>
  <div>
    <h1>Components demo</h1>

    <mint-user-card :user="{ $user }" />

    <mint-alert :type="error" style="border:1px solid red;">
      Something went wrong for {{ $user['name'] }}.
    </mint-alert>
  </div>
</mint-extend>

