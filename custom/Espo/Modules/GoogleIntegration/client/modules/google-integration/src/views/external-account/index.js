define('google-integration:views/external-account/index', ['exports', 'views/external-account/index'], function (_exports, _parent) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    _parent = _interopRequireDefault(_parent);

    function _interopRequireDefault(e) {
        return e && e.__esModule ? e : {default: e};
    }

    /**
     * Uses Integration titles (Admin → Integrations labels) for the sidebar and header,
     * same as {@see views/admin/integrations/edit} / integration list sorting.
     */
    class GoogleIntegrationExternalAccountIndex extends _parent.default {
        template = 'google-integration:external-account/index';

        setup() {
            super.setup();

            this.externalAccountList = this.externalAccountList.map(item => {
                const rawId = String(item.id || '');
                const integrationKey = rawId.includes('__') ? rawId.split('__')[0] : rawId;
                const menuLabel = this.translate(integrationKey, 'titles', 'Integration')
                    || integrationKey;

                return {
                    ...item,
                    menuId: integrationKey,
                    menuLabel: menuLabel,
                };
            });
        }

        renderHeader() {
            const $header = $('#external-account-header');

            if (!this.id) {
                $header.html('');

                return;
            }

            const title = this.translate(this.integration, 'titles', 'Integration')
                || this.integration;

            $header.show().text(title);
        }
    }

    _exports.default = GoogleIntegrationExternalAccountIndex;
});
