<div class="panel panel-default safehouse-kanban-card{{#if isStarred}} starred{{/if}}{{#if stageStyle}} kanban-stage-{{stageStyle}}{{/if}}">
    <div class="kanban-card-accent" aria-hidden="true"></div>
    <div class="kanban-card-glass" aria-hidden="true"></div>
    <div class="panel-body">
        {{#unless rowActionsDisabled}}
        <div class="pull-right item-menu-container fix-position">{{{itemMenu}}}</div>
        {{/unless}}

        {{#if stageEmoji}}
        <div class="kanban-stage-chip" title="{{stageLabel}}" aria-label="{{stageLabel}}">
            <span class="kanban-stage-chip-emoji" aria-hidden="true">{{stageEmoji}}</span>
        </div>
        {{/if}}

        {{#with titleItem}}
        <div class="kanban-card-title{{#if ../stageEmoji}} has-stage-chip{{/if}}">
            <div class="field kanban-title-value" data-name="{{name}}">{{{var key ../this}}}</div>
        </div>
        {{/with}}

        {{#with amountItem}}
        <div class="kanban-card-hero">
            {{#if ../heroEmoji}}
            <span class="kanban-hero-emoji" aria-hidden="true">{{../heroEmoji}}</span>
            {{/if}}
            <div class="field kanban-hero-amount" data-name="{{name}}">{{{var key ../this}}}</div>
        </div>
        {{/with}}

        {{#if hasStatItems}}
        <div class="kanban-props-grid" role="presentation">
            {{#each statItems}}
            <div class="kanban-prop-label kanban-prop-label-{{fieldKind}}{{#if isMuted}} is-muted{{/if}}">
                {{#if emoji}}<span class="kanban-emoji" aria-hidden="true">{{emoji}}</span>{{/if}}
                <span class="kanban-label-text">{{label}}</span>
            </div>
            <div class="kanban-prop-value kanban-prop-value-{{fieldKind}}{{#if probabilityTier}} kanban-prob-pill kanban-prob-{{probabilityTier}}{{/if}}{{#if isAlignRight}} is-align-right{{/if}}{{#if isMuted}} is-muted{{/if}}">
                <div class="field" data-name="{{name}}">{{{var key ../this}}}</div>
            </div>
            {{/each}}
        </div>
        {{/if}}

        {{#if hasDateItems}}
        <footer class="kanban-card-dates" aria-label="Dates">
            <div class="kanban-dates-grid" role="presentation">
                {{#each dateItems}}
                <div class="kanban-date-label{{#if isMuted}} is-muted{{/if}}">
                    {{#if emoji}}<span class="kanban-emoji" aria-hidden="true">{{emoji}}</span>{{/if}}
                    <span class="kanban-label-text">{{label}}</span>
                </div>
                <div class="kanban-date-value-cell{{#if isAlignRight}} is-align-right{{/if}}{{#if isMuted}} is-muted{{/if}}">
                    <div class="field kanban-date-value" data-name="{{name}}">{{{var key ../this}}}</div>
                </div>
                {{/each}}
            </div>
        </footer>
        {{/if}}
    </div>
</div>
