{{#if header}}
<div class="row button-container gi-calendar-toolbar">
    <div class="col-sm-5 col-xs-12 gi-calendar-toolbar__left">
        <div class="btn-group range-switch-group">
            <button class="btn btn-text btn-icon" data-action="prev"><span class="fas fa-chevron-left"></span></button>
            <button class="btn btn-text btn-icon" data-action="next"><span class="fas fa-chevron-right"></span></button>
        </div>
        <div class="btn-group range-switch-group">
            <button class="btn btn-text strong" data-action="today" title="{{todayLabel}}">
                <span class="hidden-xs">{{todayLabel}}</span><span class="visible-xs">{{todayLabelShort}}</span>
            </button>
        </div>

        <div class="btn-group range-switch-group gi-ownership-filter" role="group" aria-label="{{ownershipFilterLabel}}">
            <button
                type="button"
                class="btn btn-text{{#ifEqual ownershipFilter 'my'}} active{{/ifEqual}}"
                data-action="setOwnershipFilter"
                data-filter="my"
                title="{{ownershipFilterMyTitle}}"
            >{{ownershipFilterMy}}</button>
            <button
                type="button"
                class="btn btn-text{{#ifEqual ownershipFilter 'available'}} active{{/ifEqual}}"
                data-action="setOwnershipFilter"
                data-filter="available"
                title="{{ownershipFilterAvailableTitle}}"
            >{{ownershipFilterAvailable}}</button>
            {{#if showTeamOwnershipFilter}}
            <button
                type="button"
                class="btn btn-text{{#ifEqual ownershipFilter 'team'}} active{{/ifEqual}}"
                data-action="setOwnershipFilter"
                data-filter="team"
                title="{{ownershipFilterTeamTitle}}"
            >{{ownershipFilterTeam}}</button>
            {{/if}}
        </div>

        <div class="btn-group range-switch-group gi-overlay-sync-group" title="{{googleOverlaySyncNowTitle}}">
            <button
                type="button"
                class="btn btn-default"
                data-action="syncGoogleOverlay"
                title="{{googleOverlaySyncNowTitle}}"
                aria-label="{{googleOverlaySyncNow}}"
            >
                <span class="fas fa-cloud-download-alt" aria-hidden="true"></span>
                <span class="gi-overlay-sync-label">{{googleOverlaySyncNow}}</span>
            </button>
        </div>

        <button
            class="btn btn-text{{#unless isCustomView}} hidden{{/unless}} btn-icon"
            data-action="editCustomView"
            title="{{translate 'Edit'}}"
        ><span class="fas fa-pencil-alt fa-sm"></span></button>
    </div>

    <div class="date-title col-sm-3 col-xs-12">
        <h4><span style="cursor: pointer;" data-action="refresh" title="{{translate 'Refresh'}}"></span></h4>
        <div class="text-muted small gi-overlay-sync-hint">{{googleOverlaySyncNowHint}}</div>
    </div>

    <div class="col-sm-4 col-xs-12">
        <div class="btn-group pull-right mode-buttons">
            {{{modeButtons}}}
        </div>
    </div>
</div>
{{/if}}

<div class="calendar"></div>
