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

        <div class="panel panel-warning oauth-safety-panel margin-bottom">
            <div class="panel-heading">
                <h4 class="panel-title">{{translate 'googleConnectWarningTitle' scope='ExternalAccount' category='labels'}}</h4>
            </div>
            <div class="panel-body">
                <div class="oauth-safety-body">{{complexText oauthSafetyBody}}</div>
                <label class="checkbox oauth-safety-ack-label" style="display:block;margin-top:12px;">
                    <input
                        type="checkbox"
                        data-name="oauthSafetyAck"
                        {{#if oauthSafetyAck}}checked{{/if}}
                        {{#if oauthSafetyAckLocked}}disabled{{/if}}
                    >
                    {{translate 'googleConnectRiskCheckboxLabel' scope='ExternalAccount' category='labels'}}
                </label>
                <p class="text-muted small" style="margin-top:8px;margin-bottom:0;">
                    {{translate 'oauthSafetyAckHint' scope='ExternalAccount' category='labels'}}
                </p>
            </div>
        </div>

        <div class="data-panel">
            <button
                type="button"
                class="btn btn-danger {{#if isConnected}}hidden{{/if}} {{#unless oauthSafetyAck}}disabled{{/unless}}"
                data-action="connect"
                {{#unless oauthSafetyAck}}disabled{{/unless}}
            >{{translate 'Connect' scope='ExternalAccount'}}</button>
            <span
                class="connected-label label label-success {{#unless isConnected}}hidden{{/unless}}"
            >{{translate 'Connected' scope='ExternalAccount'}}</span>
            {{#if isConnected}}
            <div class="btn-group margin-top" role="group">
                <button
                    type="button"
                    class="btn btn-default btn-xs-wide {{#unless oauthSafetyAck}}disabled{{/unless}}"
                    data-action="connect"
                    {{#unless oauthSafetyAck}}disabled{{/unless}}
                >{{translate 'reconnectGoogleAccount' scope='ExternalAccount' category='labels'}}</button>
                <button
                    type="button"
                    class="btn btn-default btn-xs-wide"
                    data-action="disconnect"
                >{{translate 'Disconnect' scope='ExternalAccount'}}</button>
            </div>
            {{/if}}
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
                    class="btn btn-default btn-xs-wide margin-top {{#unless oauthSafetyAck}}disabled{{/unless}}"
                    data-action="connect"
                    {{#unless oauthSafetyAck}}disabled{{/unless}}
                >{{translate 'reconnectGoogleAccount' scope='ExternalAccount' category='labels'}}</button>
            </div>
            {{/if}}
        {{/if}}

        {{#if showCalendarSettings}}
        <div class="panel panel-default margin-top">
            <div class="panel-heading">
                <h4 class="panel-title">{{translate 'googleCalendarUserSettings' scope='ExternalAccount' category='labels'}}</h4>
            </div>
            <div class="panel-body">
                <div class="cell form-group" data-name="calendarRoutingMode">
                    <label class="control-label">
                        {{translate 'calendarRoutingMode' scope='ExternalAccount' category='fields'}}
                        <span
                            class="fas fa-info-circle text-muted"
                            title="{{translate 'calendarRoutingMode' scope='ExternalAccount' category='tooltips'}}"
                        ></span>
                    </label>
                    <div class="field" data-name="calendarRoutingMode">{{{calendarRoutingMode}}}</div>
                </div>
                <div class="cell form-group" data-name="overlayCalendarIdList">
                    <label class="control-label">
                        {{translate 'overlayCalendarIdList' scope='ExternalAccount' category='fields'}}
                        <span
                            class="fas fa-info-circle text-muted"
                            title="{{translate 'overlayCalendarIdList' scope='ExternalAccount' category='tooltips'}}"
                        ></span>
                    </label>
                    <div class="field" data-name="overlayCalendarIdList">{{{overlayCalendarIdList}}}</div>
                    <p class="help-block text-muted small">
                        {{translate 'overlayCalendarIdListHelp' scope='ExternalAccount' category='labels'}}
                    </p>
                </div>
            </div>
        </div>
        {{/if}}

        {{#if isConnected}}
        <p class="help-block text-muted small margin-top">{{translate 'calendarSyncModeHelp' scope='ExternalAccount' category='labels'}}</p>
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
