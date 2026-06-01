define('google-integration:views/admin/integrations/embedded-record-list', ['exports', 'views/record/list'], function (_exports, _list) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _list = _interopRequireDefault(_list);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    /**
     * Compact record list for Admin → Integrations (no page header / search panel).
     */
    class GoogleIntegrationEmbeddedRecordList extends _list.default {
        searchPanel = false;

        headerDisabled = true;

        showTotalCount = false;

        checkbox = false;

        massActionsDisabled = true;

        rowActionsDisabled = false;

        setup() {
            this.buttonsDisabled = false;
            this.menuDisabled = true;

            super.setup();
        }

        setupActions() {
            this.actionList = [{
                name: 'create',
                label: 'Create',
                style: 'default',
                acl: 'create',
            }];
        }
    }

    _exports.default = GoogleIntegrationEmbeddedRecordList;
});
