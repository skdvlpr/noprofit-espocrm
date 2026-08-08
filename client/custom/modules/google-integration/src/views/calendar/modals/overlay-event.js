define('google-integration:views/calendar/modals/overlay-event', ['views/modal'], function (Dep) {
    'use strict';

    return Dep.extend({
        className: 'dialog dialog-record',
        backdrop: true,
        fitHeight: false,
        escapeToClose: true,

        templateContent: `
            <div class="gi-overlay-event-modal">
                <div class="gi-overlay-event-row">
                    <div class="gi-overlay-event-label">{{nameLabel}}</div>
                    <div class="gi-overlay-event-value"><strong>{{eventName}}</strong></div>
                </div>
                <div class="gi-overlay-event-row">
                    <div class="gi-overlay-event-label">{{whenLabel}}</div>
                    <div class="gi-overlay-event-value">{{whenText}}</div>
                </div>
                {{#if htmlLink}}
                <p class="text-muted small gi-overlay-event-note">{{openHint}}</p>
                {{else}}
                <p class="text-muted small gi-overlay-event-note">{{noLinkHint}}</p>
                {{/if}}
            </div>
        `,

        data() {
            return {
                nameLabel: this.translate('name', 'fields', 'Global'),
                whenLabel: this.translate('googleOverlayEventWhen', 'labels', 'Calendar'),
                eventName: this.options.eventName || '—',
                whenText: this.options.whenText || '—',
                htmlLink: this.options.htmlLink || null,
                openHint: this.translate('googleOverlayEventOpenHint', 'labels', 'Calendar'),
                noLinkHint: this.translate('googleOverlayEventNoLinkHint', 'labels', 'Calendar'),
            };
        },

        setup() {
            this.headerText = this.translate('googleOverlayEventModalTitle', 'labels', 'Calendar');

            this.buttonList = [
                {
                    name: 'close',
                    label: 'Close',
                },
            ];

            if (this.options.htmlLink) {
                this.buttonList.unshift({
                    name: 'openGoogle',
                    label: this.translate('googleOverlayEventOpenInGoogle', 'labels', 'Calendar'),
                    style: 'primary',
                    onClick: () => {
                        window.open(this.options.htmlLink, '_blank', 'noopener,noreferrer');
                    },
                });
            }
        },
    });
});
