
<mint-wrap view="layout">
asdasd  
    <ul x:if="{!empty($items)}">
      <li x:foreach="{$items as $item}">{{ $item }}</li>
    </ul>

    <h1 x:if="{$ok}">OK</h1>

    <mint-section name="footer">
      <p>Footer section</p>
    </mint-section>
    
    asdasd
</mint-wrap>
