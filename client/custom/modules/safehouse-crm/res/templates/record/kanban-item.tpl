<div class="panel panel-default safehouse-kanban-card{{#if isStarred}} starred{{/if}}">
    <div class="kanban-card-glass" aria-hidden="true"></div>
    <div class="panel-body">
        {{#unless rowActionsDisabled}}
        <div class="pull-right item-menu-container fix-position">{{{itemMenu}}}</div>
        {{/unless}}

        {{#with titleItem}}
        <div class="kanban-card-title">
            <div class="field kanban-title-value" data-name="{{name}}">{{{var key ../this}}}</div>
        </div>
        {{/with}}

        {{#with amountItem}}
        <div class="kanban-card-hero">
            <div class="field kanban-hero-amount" data-name="{{name}}">{{{var key ../this}}}</div>
        </div>
        {{/with}}

        {{#if hasStatItems}}
        <div class="kanban-props-grid" role="presentation">
            {{#each statItems}}
            <div class="kanban-prop-label kanban-prop-label-{{fieldKind}}{{#if isMuted}} is-muted{{/if}}">{{label}}</div>
            <div class="kanban-prop-value kanban-prop-value-{{fieldKind}}{{#if isAlignRight}} is-align-right{{/if}}{{#if isMuted}} is-muted{{/if}}">
                <div class="field" data-name="{{name}}">{{{var key ../this}}}</div>
            </div>
            {{/each}}
        </div>
        {{/if}}

        {{#if hasDateItems}}
        <footer class="kanban-card-dates" aria-label="Dates">
            <div class="kanban-dates-grid" role="presentation">
                {{#each dateItems}}
                <div class="kanban-date-label{{#if isMuted}} is-muted{{/if}}">{{label}}</div>
                <div class="kanban-date-value-cell{{#if isAlignRight}} is-align-right{{/if}}{{#if isMuted}} is-muted{{/if}}">
                    <div class="field kanban-date-value" data-name="{{name}}">{{{var key ../this}}}</div>
                </div>
                {{/each}}
            </div>
        </footer>
        {{/if}}
    </div>
</div>
