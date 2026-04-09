<mint-wrap view="layout" :body-class="{'demo-basic'}">
<div>
  <h1>Hello {{ $name }}</h1>

  <p>Escaped output via triple mustache:</p>
  <div>{{{ $name }}}</div>

  <p>Raw output via double mustache:</p>
  <div>{{ $rawHtml }}</div>

  <p>Escaped output html:</p>
  <div>{{{ $rawHtml }}}</div>

  <hr />

  <h2>x:if</h2>
  <p x:if="{ $isLoggedIn }">Welcome back, {{ $userName }}.</p>
  <p x:if="{ ! $isLoggedIn }">Welcome, guest.</p>

  <h2>x:foreach</h2>
  <ul x:if="{ ! empty($products) }">
    <li x:foreach="{ $products as $product }">
      {{ $product['name'] }} — ${{ $product['price'] }}
    </li>
  </ul>

  <h2>x:repeat</h2>
  <ul>
    <li x:repeat="{ $count as $i }">Item {{ $i }}</li>
  </ul>

  <h2>mint-include</h2>
  <div>
    <?php $title = 'Starter plan'; $price = 9; $badge = 'Most popular'; ?>
    <mint-include name="partials/price-card.php" :props="{$title, $price, $badge}" />
  </div>
</div>
</mint-wrap>
