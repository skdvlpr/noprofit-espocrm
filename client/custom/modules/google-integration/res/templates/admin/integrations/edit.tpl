<div class="button-container">
    <div class="btn-group">
        <button class="btn btn-primary btn-xs-wide" data-action="save">{{translate 'Save'}}</button>
        <button class="btn btn-default btn-xs-wide" data-action="cancel">{{translate 'Cancel'}}</button>
    </div>
</div>

<div class="row">
    <div class="col-sm-6">
        <div class="panel panel-default">
            <div class="panel-body panel-body-form">
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
                <div class="cell form-group" data-name="redirectUri">
                    <label
                        class="control-label"
                        data-name="redirectUri"
                    >{{translate 'redirectUri' scope='Integration' category='fields'}}</label>
                    <div class="field" data-name="redirectUri">
                        <input type="text" class="form-control" readonly value="{{redirectUri}}">
                    </div>
                </div>
            </div>
        </div>

        {{#if templateButtonList.length}}
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">{{templatesTitle}}</h4>
            </div>
            <div class="panel-body">
                <p class="text-muted small">{{templatesHelp}}</p>
                <div class="btn-group-vertical" style="width: 100%;">
                    {{#each templateButtonList}}
                        <button
                            type="button"
                            class="btn btn-default text-left"
                            style="margin-bottom: 6px; white-space: normal;"
                            data-action="openTemplateModal"
                            data-entity-type="{{entityType}}"
                            data-field-name="{{fieldName}}"
                        >
                            <strong>{{entityLabel}}</strong>
                            <span class="text-muted small"> — {{statusLabel}}</span>
                        </button>
                    {{/each}}
                </div>
            </div>
        </div>
        {{/if}}
    </div>
    <div class="col-sm-6">
        {{#if helpText}}
        <div class="well">
            {{complexText helpText}}
        </div>
        {{/if}}
    </div>
</div>

<div class="row google-calendar-admin-config-panels">
    <div class="col-sm-12">
        <div class="panel panel-default" data-role="calendar-config-panel">
            <div class="panel-heading">
                <h4 class="panel-title">{{dateSourcesTitle}}</h4>
            </div>
            <div class="panel-body">
                <p class="text-muted small">{{dateSourcesHelp}}</p>
                <div data-role="date-sources-list"></div>
            </div>
        </div>
        <div class="panel panel-default" data-role="calendar-config-panel">
            <div class="panel-heading">
                <h4 class="panel-title">{{calendarTemplatesTitle}}</h4>
            </div>
            <div class="panel-body">
                <p class="text-muted small">{{calendarTemplatesHelp}}</p>
                <div data-role="calendar-templates-list"></div>
            </div>
        </div>
    </div>
</div>
