<div class="button-container">
    <div class="btn-group">
        <button class="btn btn-primary btn-xs-wide" data-action="save">{{translate 'Save'}}</button>
        <button class="btn btn-default btn-xs-wide" data-action="cancel">{{translate 'Cancel'}}</button>
    </div>
</div>

<div class="row">
    <div class="col-sm-6">
        <div>
            <div class="cell form-group" data-name="enabled">
                <label
                    class="control-label"
                    data-name="enabled"
                >{{translate 'enabled' scope='Integration' category='fields'}}</label>
                <div class="field" data-name="enabled">{{{enabled}}}</div>
            </div>
        </div>
        <div class="data-panel">
            <button
                type="button"
                class="btn btn-danger {{#if isConnected}}hidden{{/if}}"
                data-action="connect"
            >{{translate 'Connect' scope='ExternalAccount'}}</button>
            <span
                class="connected-label label label-success {{#unless isConnected}}hidden{{/unless}}"
            >{{translate 'Connected' scope='ExternalAccount'}}</span>
        </div>
        {{#if showGoogleAccountProfile}}
        <div class="panel panel-default margin-top">
            <div class="panel-body">
                <div class="media">
                    {{#if googleAccountPicture}}
                    <div class="media-left">
                        <img
                            class="img-circle"
                            src="{{googleAccountPicture}}"
                            alt=""
                            style="width: 42px; height: 42px;"
                        >
                    </div>
                    {{/if}}
                    <div class="media-body">
                        <div><strong>{{googleAccountName}}</strong></div>
                        <div class="text-muted small">{{googleAccountEmail}}</div>
                    </div>
                </div>
            </div>
        </div>
        {{else}}
            {{#if googleAccountProfileMissing}}
            <div class="alert alert-warning margin-top">
                <div>{{translate 'googleAccountProfileMissing' scope='ExternalAccount' category='labels'}}</div>
                <button
                    type="button"
                    class="btn btn-default btn-xs-wide margin-top"
                    data-action="connect"
                >{{translate 'reconnectGoogleAccount' scope='ExternalAccount' category='labels'}}</button>
            </div>
            {{/if}}
        {{/if}}
        {{#if showCalendarSyncSettings}}
        <div class="panel panel-default calendar-sync-panel margin-top">
            <div class="panel-body panel-body-form">
                <div class="cell form-group" data-name="calendarSyncMode">
                    <label
                        class="control-label"
                        data-name="calendarSyncMode"
                    >{{translate 'calendarSyncMode' scope='ExternalAccount' category='fields'}}</label>
                    <div class="field" data-name="calendarSyncMode">{{{calendarSyncMode}}}</div>
                </div>
                <p class="help-block text-muted small">{{translate 'calendarSyncModeHelp' scope='ExternalAccount' category='labels'}}</p>
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
