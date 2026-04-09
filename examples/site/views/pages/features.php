<mint-wrap view="layout">
    <article class="article">
        <header class="article__header">
            <h1 class="article__title">What you can build</h1>
            <p class="article__deck">
                Mint focuses on a predictable compile step: HTML-ish templates become PHP includes, with a
                handful of directives and first-class components.
            </p>
        </header>

        <ul class="feature-list">
            <li>
                <h2 class="feature-list__title">Layouts with <code>mint-wrap</code></h2>
                <p>Buffer page content, inject it into a parent layout via the shared <code>portal</code> section, and render navigation once.</p>
            </li>
            <li>
                <h2 class="feature-list__title">Sections &amp; yields</h2>
                <p><code>mint-yield</code> pulls named fragments from the render context so shells stay declarative.</p>
            </li>
            <li>
                <h2 class="feature-list__title">Conditional &amp; loops</h2>
                <p><code>x:if</code> and <code>x:foreach</code> compile to plain PHP control structures around real markup.</p>
            </li>
            <li>
                <h2 class="feature-list__title">Custom components</h2>
                <p>Register <code>mint-*</code> tags that map to PHP classes; pass props with <code>:</code> attributes and optional slots.</p>
            </li>
        </ul>

        <p class="article__cta">
            <a class="button button--primary" href="/">← Back to showcase</a>
        </p>
    </article>
</mint-wrap>
