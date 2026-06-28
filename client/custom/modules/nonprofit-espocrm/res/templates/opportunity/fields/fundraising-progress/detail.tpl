<div class="fundraising-progress-field">
    <div class="fundraising-progress-label text-muted">{{label}}</div>
    {{#if hasTarget}}
        <div class="fundraising-progress-amounts">
            <span class="fundraising-progress-collected">{{collectedFormatted}}</span>
            <span class="fundraising-progress-separator"> / </span>
            <span class="fundraising-progress-target">{{targetFormatted}}</span>
        </div>
        <div class="progress fundraising-progress-bar">
            <div
                class="progress-bar fundraising-progress-fill"
                role="progressbar"
                aria-valuenow="{{percent}}"
                aria-valuemin="0"
                aria-valuemax="100"
                style="width: {{percent}}%;"
            >
                {{percent}}%
            </div>
        </div>
    {{else}}
        <div class="text-soft">{{noTargetLabel}}</div>
    {{/if}}
</div>
