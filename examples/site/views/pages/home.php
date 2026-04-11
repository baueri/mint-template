<mint-wrap path="layout.php" :body-class="{'site-home'}">
    <section class="hero">
        <p class="hero__eyebrow">Template compiler</p>
        <h1 class="hero__title">Mint, in full color</h1>
        <p class="hero__lead">
            Layouts, slots, DOM directives, and PHP components working together in one small example.
        </p>
        <div class="hero__actions">
            <a class="button button--primary" href="/features">See what ships</a>
            <a class="button button--ghost" href="#components-heading">Browse components</a>
        </div>
    </section>

    <section class="section" aria-labelledby="stats-heading">
        <h2 id="stats-heading" class="section__title">At a glance</h2>
        <div class="stats-grid">
            <mint-stat-tile :label="{'Public templates'}" :value="{ (string) $stat_repos }" />
            <mint-stat-tile :label="{'Community stars'}" :value="{ $stat_stars }" />
            <mint-stat-tile :label="{'Uptime target'}" :value="{ $stat_uptime }" />
        </div>
    </section>

    <section class="section" aria-labelledby="team-heading">
        <div class="section__head">
            <h2 id="team-heading" class="section__title">Team</h2>
            <p class="section__meta">Rendered with <code>x:foreach</code></p>
        </div>

        <ul class="team-list" x:if="{ ! empty($team) }">
            <li x:foreach="{ $team as $member }" class="team-card">
                <div class="team-card__avatar" aria-hidden="true"></div>
                <div class="team-card__body">
                    <div class="team-card__name">{{ $member['name'] }}</div>
                    <div class="team-card__role">{{ $member['role'] }}</div>
                </div>
                <span class="badge badge--{{ $member['status'] === 'online' ? 'success' : 'muted' }}">
                    {{ $member['status'] }}
                </span>
            </li>
        </ul>
    </section>
    <section class="section section--split">
        <div>
            <h2 id="team-heading" class="section__title">Loop</h2>
            <p class="section__meta">Rendered with <code>x:repeat</code></p>
        </div>
        
        <ul class="team-list">
            <li x:repeat="{ 3 as $i }" class="team-card">
                <div class="team-card__avatar" aria-hidden="true"></div>
                <div class="team-card__body">
                    <div class="team-card__name">Item {{ $i }}</div>
                </div>
            </li>
        </ul>
    </section>

    <section class="section section--split" aria-labelledby="components-heading">
        <div>
            <h2 id="components-heading" class="section__title">Components</h2>
            <p class="section__lead">
                Self-closing tags skip <code>ob_start</code>. Use a body for HTML slots, or
                <code>:slot="…"</code> for a string slot without nested markup.
            </p>

            <mint-alert x:if="{ $preview }" :type="{'success'}" :slot="Action successfully executed"/>

            <mint-alert :type="{'warning'}">
                <strong>Heads up:</strong> this message uses a <em>default slot</em> for rich HTML.
            </mint-alert>
        </div>

        <div>
            <mint-feature-callout
                :title="{'Feature callouts'}"
                :subtitle="{'Nested views + slot forwarding'}"
            >
                <p>
                    This block is compiled as a custom tag. Inner markup becomes <code>$slot</code> in the
                    component view.
                </p>
            </mint-feature-callout>

            <?php $title = 'Feature callouts 2'; $subtitle = 'Convenient shorthand to pass properties'; ?>
            <mint-feature-callout :props="{$title, $subtitle}" style="margin-top:1rem;">
                <p>
                    This is a feature callout with a title and subtitle like this: <br />
                    <code>:props="{$title, $subtitle}"</code> <br/> instead of <br/> <code>:title="{$title} :subtitle="{$subtitle}"</code>.
                </p>
            </mint-feature-callout>
        </div>
    </section>

    <section class="section section--panel" aria-labelledby="text-heading">
        <h2 id="text-heading" class="section__title">Text directives</h2>
        @if($preview)
            <p class="panel-ok">Preview mode is <strong>on</strong> — <code>@if</code> chose this branch.</p>
        @else
            <p>Preview mode is off.</p>
        @endif
    </section>
</mint-wrap>
