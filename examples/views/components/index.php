<div>
  <h1>Components demo</h1>

  <mint-user-card :user="{ $user }" />

  <mint-alert :type="error">
    Something went wrong for {{ $user['name'] }}.
  </mint-alert>
</div>

