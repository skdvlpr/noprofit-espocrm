define('google-integration:controllers/external-account', ['exports', 'controllers/external-account'], function (_exports, _parent) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    _parent = _interopRequireDefault(_parent);

    function _interopRequireDefault(e) {
        return e && e.__esModule ? e : {default: e};
    }

    const INDEX_VIEW = 'google-integration:views/external-account/index';

    class GoogleIntegrationExternalAccountController extends _parent.default {
        actionList() {
            this.collectionFactory.create('ExternalAccount', collection => {
                collection.once('sync', () => {
                    this.main(INDEX_VIEW, {
                        collection: collection,
                    });
                });

                collection.fetch();
            });
        }

        actionEdit(options) {
            const id = options.id;

            this.collectionFactory.create('ExternalAccount', collection => {
                collection.once('sync', () => {
                    this.main(INDEX_VIEW, {
                        collection: collection,
                        id: id,
                    });
                });

                collection.fetch();
            });
        }
    }

    _exports.default = GoogleIntegrationExternalAccountController;
});
