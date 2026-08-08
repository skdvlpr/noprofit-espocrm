<div class="gi-ea-oauth2">
    <div class="button-container gi-ea-oauth2__toolbar">
        <div class="btn-group">
            <button class="btn btn-primary btn-xs-wide" data-action="save">{{translate 'Save'}}</button>
            <button class="btn btn-default btn-xs-wide" data-action="cancel">{{translate 'Cancel'}}</button>
        </div>
    </div>

    <div class="gi-ea-oauth2__stack">
        {{#if helpText}}
        <div class="gi-ea-help well" role="note">
            {{complexText helpText}}
        </div>
        {{/if}}

        <section class="panel panel-default gi-ea-panel" aria-label="{{translate 'enabled' scope='Integration' category='fields'}}">
            <div class="panel-body">
                <div class="gi-ea-cell cell form-group" data-name="enabled">
                    <label
                        class="control-label"
                        data-name="enabled"
                    >{{translate 'enabled' scope='Integration' category='fields'}}</label>
                    <div class="field" data-name="enabled">{{{enabled}}}</div>
                </div>
            </div>
        </section>

        <section class="panel panel-warning oauth-safety-panel gi-ea-panel">
            <div class="panel-heading">
                <h4 class="panel-title">{{translate 'googleConnectWarningTitle' scope='ExternalAccount' category='labels'}}</h4>
            </div>
            <div class="panel-body">
                <div class="oauth-safety-body">{{complexText oauthSafetyBody}}</div>
                <label class="checkbox oauth-safety-ack-label">
                    <input
                        type="checkbox"
                        data-name="oauthSafetyAck"
                        {{#if oauthSafetyAck}}checked{{/if}}
                        {{#if oauthSafetyAckLocked}}disabled{{/if}}
                    >
                    {{translate 'googleConnectRiskCheckboxLabel' scope='ExternalAccount' category='labels'}}
                </label>
                <p class="text-muted small gi-ea-muted">
                    {{translate 'oauthSafetyAckHint' scope='ExternalAccount' category='labels'}}
                </p>
            </div>
        </section>

        {{!--
          Do NOT use class "data-panel": Espo core oauth2 afterRender hides
          .data-panel when Enabled is unchecked.
        --}}
        <div class="gi-ea-connect oauth-connect-panel">
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
            <div class="btn-group" role="group">
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
        <section class="panel panel-default gi-ea-panel gi-ea-profile">
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
        </section>
        {{else}}
            {{#if googleAccountProfileMissing}}
            <div class="alert alert-warning">
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
        <section class="panel panel-default gi-ea-panel">
            <div class="panel-heading">
                <h4 class="panel-title">{{translate 'googleCalendarUserSettings' scope='ExternalAccount' category='labels'}}</h4>
            </div>
            <div class="panel-body">
                <div class="gi-ea-cell cell form-group" data-name="calendarRoutingMode">
                    <label class="control-label">
                        {{translate 'calendarRoutingMode' scope='ExternalAccount' category='fields'}}
                        <span
                            class="fas fa-info-circle text-muted"
                            title="{{translate 'calendarRoutingMode' scope='ExternalAccount' category='tooltips'}}"
                        ></span>
                    </label>
                    <div class="field" data-name="calendarRoutingMode">{{{calendarRoutingMode}}}</div>
                    <div
                        class="gi-ea-routing-help"
                        data-name="calendarRoutingModeHelp"
                    >{{complexText calendarRoutingModeHelp}}</div>
                </div>
                <div class="gi-ea-cell cell form-group" data-name="overlayCalendarIdList">
                    <label class="control-label">
                        {{translate 'overlayCalendarIdList' scope='ExternalAccount' category='fields'}}
                        <span
                            class="fas fa-info-circle text-muted"
                            title="{{translate 'overlayCalendarIdList' scope='ExternalAccount' category='tooltips'}}"
                        ></span>
                    </label>
                    <div class="field" data-name="overlayCalendarIdList">{{{overlayCalendarIdList}}}</div>
                    <p class="help-block text-muted small gi-ea-muted">
                        {{translate 'overlayCalendarIdListHelp' scope='ExternalAccount' category='labels'}}
                    </p>
                </div>
                <p class="help-block text-muted small gi-ea-muted">
                    {{complexText calendarSyncModeHelp}}
                </p>
            </div>
        </section>
        {{/if}}
    </div>
</div>
