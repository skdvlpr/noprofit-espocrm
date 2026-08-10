<div class="gi-select-share-teams">
    <div class="gi-select-share-teams__toolbar">
        <div class="gi-select-share-teams__search">
            <span class="fas fa-search" aria-hidden="true"></span>
            <input
                type="search"
                class="form-control"
                data-name="teamSearch"
                placeholder="{{searchPlaceholder}}"
                autocomplete="off"
            >
        </div>
        <div class="gi-select-share-teams__meta text-muted">
            <span data-name="selectedCount">{{selectedCount}}</span>
            {{translate 'Selected'}}
        </div>
    </div>

    {{#unless teams.length}}
    <div class="gi-select-share-teams__empty text-muted">{{emptyText}}</div>
    {{/unless}}

    <div class="gi-select-share-teams__list" role="list">
        {{#each teams}}
        <article
            class="gi-share-team-card{{#if selected}} is-selected{{/if}}{{#unless hasGoogle}} is-no-google{{/unless}}{{#if collapsed}} is-collapsed{{/if}}"
            role="listitem"
            data-id="{{id}}"
        >
            <div class="gi-share-team-card__main">
                <label class="gi-share-team-card__check">
                    <input
                        type="checkbox"
                        data-role="team-check"
                        data-id="{{id}}"
                        {{#if selected}}checked{{/if}}
                    >
                    <span class="gi-share-team-card__name">{{name}}</span>
                </label>
                <span
                    class="gi-share-badge{{#unless hasGoogle}} gi-share-badge--muted{{/unless}}"
                    title="{{../googleLabel}}"
                >{{googleRatio}} {{../googleLabel}}</span>
                <button
                    type="button"
                    class="btn btn-link btn-sm action gi-share-team-card__toggle"
                    data-action="toggleMembers"
                    aria-expanded="{{#if collapsed}}false{{else}}true{{/if}}"
                >
                    <span class="fas fa-chevron-down" aria-hidden="true"></span>
                    {{../membersLabel}}
                </button>
            </div>
            <div class="gi-share-team-card__members">
                {{#each members}}
                <span
                    class="gi-share-member-chip{{#if googleConnected}} gi-share-member-chip--google{{else}} gi-share-member-chip--muted{{/if}}"
                    title="{{userName}}"
                >
                    {{#if googleConnected}}
                    <span class="gi-share-member-chip__icon fas fa-check" aria-hidden="true"></span>
                    {{/if}}
                    {{name}}
                </span>
                {{/each}}
                {{#unless members.length}}
                <span class="text-muted small">—</span>
                {{/unless}}
            </div>
        </article>
        {{/each}}
    </div>
</div>
