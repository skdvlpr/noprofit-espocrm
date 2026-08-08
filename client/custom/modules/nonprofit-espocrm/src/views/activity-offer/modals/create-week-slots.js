define('nonprofit-espocrm:views/activity-offer/modals/create-week-slots', [
    'views/modal',
    'model',
], function (Dep, Model) {

    return Dep.extend({

        cssName: 'dialog-record',
        className: 'dialog dialog-record',
        backdrop: true,
        fitHeight: true,

        templateContent: `
            <div class="record">
                <div class="middle">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">{{sharedAddressTitle}}</h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="cell col-sm-12 form-group">
                                    <label class="control-label">
                                        <input type="checkbox" data-name="uniqueAddress"
                                               {{#if uniqueAddress}}checked{{/if}}>
                                        {{uniqueAddressLabel}}
                                    </label>
                                    <div class="text-muted small" style="margin-top:4px;">
                                        {{uniqueAddressTooltip}}
                                    </div>
                                </div>
                            </div>
                            <div class="row" data-name="batchPlace"
                                 {{#unless uniqueAddress}}style="display:none;"{{/unless}}>
                                <div class="cell col-sm-12 form-group">
                                    <label class="control-label">{{placeLabel}}</label>
                                    <div class="field" data-name="batchPlaceField"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-body">
                            <div class="cell form-group" data-name="weekSlots">
                                <div class="field" data-name="weekSlots"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `,

        data: function () {
            return {
                uniqueAddress: !!this.editorModel.get('uniqueAddress'),
                sharedAddressTitle: this.translate('Batch address', 'labels', 'ActivityOffer'),
                uniqueAddressLabel: this.translate('uniqueAddress', 'fields', 'ActivityOffer'),
                uniqueAddressTooltip: this.translate('uniqueAddress', 'tooltips', 'ActivityOffer'),
                placeLabel: this.translate('place', 'fields', 'ActivityOfferSlot'),
            };
        },

        setup: function () {
            this.headerText = this.translate('Add shifts', 'labels', 'ActivityOffer');

            this.buttonList = [
                {
                    name: 'save',
                    label: 'Create',
                    style: 'primary',
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                },
            ];

            this.editorModel = new Model();
            this.editorModel.name = 'ActivityOffer';
            this.editorModel.entityType = 'ActivityOffer';
            this.editorModel.urlRoot = 'ActivityOffer';
            this.editorModel.defs = this.model.defs;
            this.editorModel.set({
                weekStart: this.model.get('weekStart'),
                uniqueAddress: false,
                placeStreet: '',
                placeCity: '',
                placeState: '',
                placePostalCode: '',
                placeCountry: '',
                weekSlots: [],
            }, {silent: true});

            this.listenTo(this.editorModel, 'change:uniqueAddress', () => {
                this.syncBatchPlaceVisibility();

                const fieldView = this.getView('weekSlots');

                if (fieldView && typeof fieldView.reRender === 'function') {
                    fieldView.reRender();
                }
            });
        },

        afterRender: function () {
            this.ensureWeekSlotsView();
            this.ensureBatchPlaceView();
            this.syncBatchPlaceVisibility();

            this.$el.find('[data-name="uniqueAddress"]').off('change.weekSlotsModal')
                .on('change.weekSlotsModal', e => {
                    this.syncBatchPlaceFromField();
                    this.editorModel.set('uniqueAddress', !!e.currentTarget.checked);
                });
        },

        ensureWeekSlotsView: function () {
            const $target = this.$el.find('div.field[data-name="weekSlots"]');

            if (!$target.length) {
                return;
            }

            const existing = this.getView('weekSlots');

            if (existing) {
                existing.setElement($target);
                existing.render();

                return;
            }

            this.createView('weekSlots', 'nonprofit-espocrm:views/activity-offer/fields/week-slots', {
                model: this.editorModel,
                name: 'weekSlots',
                mode: 'edit',
                selector: 'div.field[data-name="weekSlots"]',
                defs: {
                    name: 'weekSlots',
                    type: 'jsonArray',
                },
                params: {
                    required: true,
                },
                seedEmpty: true,
            }, view => {
                view.render();
            });
        },

        ensureBatchPlaceView: function () {
            const $target = this.$el.find('div.field[data-name="batchPlaceField"]');

            if (!$target.length) {
                return;
            }

            const existing = this.getView('batchPlace');

            if (existing) {
                existing.setElement($target);
                existing.render();

                return;
            }

            this.createView('batchPlace', 'nonprofit-espocrm:views/fields/address', {
                model: this.editorModel,
                name: 'place',
                mode: 'edit',
                selector: 'div.field[data-name="batchPlaceField"]',
                defs: {
                    name: 'place',
                    type: 'address',
                    params: {
                        viewMap: true,
                    },
                },
                params: {
                    viewMap: true,
                },
            }, view => {
                view.render();
            });
        },

        syncBatchPlaceVisibility: function () {
            const $place = this.$el.find('[data-name="batchPlace"]');

            if (!$place.length) {
                return;
            }

            if (this.editorModel.get('uniqueAddress')) {
                $place.show();

                const placeView = this.getView('batchPlace');

                if (placeView && typeof placeView.reRender === 'function') {
                    // Re-bind Places after becoming visible.
                    window.setTimeout(() => placeView.reRender(), 0);
                }
            }
            else {
                $place.hide();
            }
        },

        syncBatchPlaceFromField: function () {
            const placeView = this.getView('batchPlace');

            if (placeView && typeof placeView.fetch === 'function') {
                const data = placeView.fetch();

                if (data && typeof data === 'object') {
                    this.editorModel.set(data, {silent: true});
                }
            }
        },

        actionSave: function () {
            this.syncBatchPlaceFromField();

            const fieldView = this.getView('weekSlots');

            if (!fieldView) {
                Espo.Ui.error(this.translate('Error occurred'));

                return;
            }

            if (typeof fieldView.fetch === 'function') {
                fieldView.fetch();
            }

            if (typeof fieldView.validateRequired === 'function' && fieldView.validateRequired()) {
                return;
            }

            const rows = this.editorModel.get('weekSlots') || [];
            const uniqueAddress = !!this.editorModel.get('uniqueAddress');

            if (!rows.length) {
                Espo.Ui.error(this.translate('fieldIsRequired', 'messages')
                    .replace('{field}', this.translate('weekSlots', 'fields', 'ActivityOffer')));

                return;
            }

            for (let i = 0; i < rows.length; i++) {
                if (!rows[i].category) {
                    Espo.Ui.error(
                        this.translate('fieldIsRequired', 'messages')
                            .replace('{field}', this.translate('category', 'fields', 'ActivityOfferSlot'))
                    );

                    return;
                }
            }

            if (uniqueAddress) {
                const street = (this.editorModel.get('placeStreet') || '').trim();
                const city = (this.editorModel.get('placeCity') || '').trim();

                if (!street && !city) {
                    Espo.Ui.error(
                        this.translate('fieldIsRequired', 'messages')
                            .replace('{field}', this.translate('place', 'fields', 'ActivityOfferSlot'))
                    );

                    return;
                }
            }

            Espo.Ui.notifyWait();

            Espo.Ajax
                .postRequest('ActivityOffer/action/addWeekSlots', {
                    id: this.model.id,
                    rows: rows,
                    uniqueAddress: uniqueAddress,
                    placeStreet: this.editorModel.get('placeStreet') || '',
                    placeCity: this.editorModel.get('placeCity') || '',
                    placeState: this.editorModel.get('placeState') || '',
                    placePostalCode: this.editorModel.get('placePostalCode') || '',
                    placeCountry: this.editorModel.get('placeCountry') || '',
                })
                .then(response => {
                    Espo.Ui.success(
                        this.translate('addShiftsSuccess', 'messages', 'ActivityOffer')
                            .replace('{createdCount}', String(response.createdCount || rows.length))
                            .replace('{slotCount}', String(response.slotCount || ''))
                    );
                    this.trigger('after:save', response);
                    this.close();
                })
                .catch(() => {});
        },
    });
});
