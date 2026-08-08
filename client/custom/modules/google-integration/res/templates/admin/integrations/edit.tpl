<div class="gi-admin-integration">
    <div class="button-container gi-admin-integration__toolbar">
        <div class="btn-group">
            <button class="btn btn-primary btn-xs-wide" data-action="save">{{translate 'Save'}}</button>
            <button class="btn btn-default btn-xs-wide" data-action="cancel">{{translate 'Cancel'}}</button>
        </div>
    </div>

    <div class="gi-admin-integration__stack">
        {{#if helpText}}
        <div class="gi-help" role="note">
            {{complexText helpText}}
        </div>
        {{/if}}

        <section
            class="gi-panel gi-credentials"
            aria-label="{{translate 'enabled' scope='Integration' category='fields'}}"
        >
            <div class="gi-panel__body panel-body panel-body-form">
                <div class="cell form-group" data-name="enabled">
                    <label
                        class="control-label"
                        data-name="enabled"
                    >{{translate 'enabled' scope='Integration' category='fields'}}</label>
                    <div class="field" data-name="enabled">{{{enabled}}}</div>
                </div>
                {{#each fieldDataList}}
                    <div
                        class="cell form-group"
                        data-name="{{name}}"
                    >
                        <label
                            class="control-label"
                            data-name="{{name}}"
                        >{{label}}</label>
                        <div
                            class="field"
                            data-name="{{name}}"
                        >{{{var name ../this}}}</div>
                    </div>
                {{/each}}
                <div class="cell form-group gi-redirect-uri" data-name="redirectUri">
                    <label
                        class="control-label"
                        data-name="redirectUri"
                    >{{translate 'redirectUri' scope='Integration' category='fields'}}</label>
                    <div class="field" data-name="redirectUri">
                        <input type="text" class="form-control" readonly value="{{redirectUri}}">
                    </div>
                </div>
            </div>
        </section>

        <section class="gi-section google-calendar-admin-config-panels">
            <div class="gi-section__head">
                <h4 class="gi-section__title">{{calendarConfigTitle}}</h4>
                <p class="gi-section__lead">{{calendarConfigHelp}}</p>
            </div>

            {{#if crmCalendarScopes.length}}
            <div class="gi-crm-calendar-scopes">
                <div class="gi-crm-calendar-scopes__head">
                    <span class="gi-crm-calendar-scopes__badge" aria-hidden="true">
                        <span class="fas fa-calendar-check"></span>
                    </span>
                    <div class="gi-crm-calendar-scopes__titles">
                        <div class="gi-crm-calendar-scopes__title">{{crmCalendarScopesTitle}}</div>
                        <p class="gi-crm-calendar-scopes__help">{{crmCalendarScopesHelp}}</p>
                    </div>
                </div>
                <ul class="gi-crm-calendar-scopes__grid">
                    {{#each crmCalendarScopes}}
                    <li class="gi-crm-scope-chip" title="{{entityType}}">
                        <span class="gi-crm-scope-chip__check fas fa-check" aria-hidden="true"></span>
                        <span class="gi-crm-scope-chip__label">{{label}}</span>
                    </li>
                    {{/each}}
                </ul>
                <p class="gi-crm-calendar-scopes__google-note">
                    <span class="fas fa-info-circle" aria-hidden="true"></span>
                    <span>{{crmCalendarScopesGoogleNote}}</span>
                </p>
            </div>
            {{/if}}

            <div class="gi-nav-grid" role="navigation" aria-label="{{calendarConfigTitle}}">
                {{#each calendarNavItems}}
                <a
                    href="{{href}}"
                    class="gi-nav-card gi-nav-card--{{modifier}}"
                >
                    <span class="gi-nav-card__icon" aria-hidden="true">
                        <span class="{{iconClass}}"></span>
                    </span>
                    <span class="gi-nav-card__body">
                        <span class="gi-nav-card__title">{{title}}</span>
                        <span class="gi-nav-card__desc">{{description}}</span>
                    </span>
                    <span class="gi-nav-card__arrow fas fa-chevron-right" aria-hidden="true"></span>
                </a>
                {{/each}}
            </div>
        </section>
    </div>
</div>
