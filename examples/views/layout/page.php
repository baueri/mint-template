
<mint-extend path="layout.php" :body-class="{'demo-layout'}">
  <mint-section name="heading">
    <h1>{{ $title }}</h1>
    <p>Example layout page rendered through <code>mint-extend</code>.</p>
  </mint-section>

  <article>
    <p x:if="{ $ok }">Status: OK</p>
    <p x:if="{ ! $ok }">Status: not OK</p>

    <h2>Items</h2>
    <ul x:if="{ ! empty($items) }">
      <li x:foreach="{ $items as $item }">{{ $item }}</li>
    </ul>
    <p x:if="{ empty($items) }">No items.</p>
  </article>

  <mint-section name="footer">
    <p>Footer section</p>
  </mint-section>
</mint-extend>
