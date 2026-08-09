/***
 * Custom list record view with inline cell editing.
 *
 * Double-click any editable cell (enum, varchar, int, float, bool, date,
 * currency) to switch it to edit mode in place.
 * Enum fields also react to a single click for quick status changes.
 *
 * - Enter  → save
 * - Escape → cancel
 * - Click outside cell → save
 * - Select change (enum) → save immediately
 */
define('nonprofit-espocrm:views/record/list-inline-edit', [
    'views/record/list',
    'nonprofit-espocrm:lib/quick-view-navigation',
], function (Dep, QuickViewNavigation) {

    const EDITABLE_TYPES = new Set([
        'enum', 'varchar', 'int', 'float', 'bool',
        'date', 'currency', 'multiEnum',
    ]);

    return Dep.extend({

        /** @private Currently active inline edit state, or null. */
        _ilEdit: null,

        setup: function () {
            Dep.prototype.setup.apply(this, arguments);

            if (QuickViewNavigation.isRelationshipList(this)) {
                // Respect clientDefs.quickDetailDisabled (ActivityOffer / Slot = full page).
                if (QuickViewNavigation.applyQuickDetailPolicy(this) === 'quick') {
                    QuickViewNavigation.patchListLinkClick(this);
                }
            }

            QuickViewNavigation.bindAfterSaveRefresh(this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this._injectInlineEditStyles();
            this._bindInlineEditEvents();
            this._markEditableCells();
        },

        // -- bootstrap --------------------------------------------------------

        _injectInlineEditStyles: function () {
            if (document.getElementById('sh-inline-edit-css')) {
                return;
            }

            var style = document.createElement('style');
            style.id = 'sh-inline-edit-css';
            style.textContent = [
                '.list-row td.cell.sh-editable { cursor: pointer; transition: background-color 0.15s; }',
                '.list-row td.cell.sh-editable:hover { background-color: var(--table-hover-bg, rgba(0,0,0,0.03)); }',
                '.list-row td.cell.sh-editing { background-color: var(--table-active-bg, rgba(0,0,0,0.06));' +
                    ' position: relative; z-index: 10; overflow: visible !important; }',
                '.list-row td.cell.sh-editing select.form-control,' +
                    '.list-row td.cell.sh-editing input.form-control {' +
                    ' height: auto; padding: 2px 8px; font-size: inherit; min-width: 80px; }',
                '.list-row td.cell.sh-editing select.form-control { position: relative; z-index: 11; }',
                '.list-row td.cell.sh-editing select.form-control option {' +
                    ' background: var(--form-element-bg-color, var(--secondary-bg-color, #fff));' +
                    ' color: var(--form-element-color, var(--text-color, #333)); }',
                /* overflow-x:auto forces overflow-y:auto and clips native <select> */
                '.list:has(.sh-editing), .list-container:has(.sh-editing),' +
                    '.panel:has(.sh-editing), .panel-body:has(.sh-editing) {' +
                    ' overflow: visible !important; }',
            ].join('\n');

            document.head.appendChild(style);
        },

        _bindInlineEditEvents: function () {
            this.$el.off('.sh-ile');

            this.$el.on('dblclick.sh-ile', 'td.cell.sh-editable', function (e) {
                if (this._shouldIgnoreTarget(e)) {
                    return;
                }
                this._activateCell(e.currentTarget);
            }.bind(this));

            this.$el.on('click.sh-ile', 'td.cell.sh-editable.sh-enum', function (e) {
                if (this._shouldIgnoreTarget(e)) {
                    return;
                }
                this._activateCell(e.currentTarget);
            }.bind(this));
        },

        _markEditableCells: function () {
            var self = this;
            this.$el.find('td.cell[data-name]').each(function () {
                var $cell = $(this);
                var fieldName = $cell.data('name');
                var $row = $cell.closest('tr.list-row');
                var modelId = $row.data('id');

                if (!modelId || !fieldName) {
                    return;
                }

                if (!self._isCellEditable(modelId, fieldName)) {
                    return;
                }

                $cell.addClass('sh-editable');

                var fieldDefs = self.getMetadata().get(
                    ['entityDefs', self.scope, 'fields', fieldName]
                ) || {};

                if (fieldDefs.type === 'enum') {
                    $cell.addClass('sh-enum');
                }
            });
        },

        // -- helpers ----------------------------------------------------------

        _shouldIgnoreTarget: function (e) {
            return $(e.target).closest('a[href], button, .action, .dropdown-menu').length > 0;
        },

        _isCellEditable: function (modelId, fieldName) {
            var model = this.collection.get(modelId);
            if (!model) {
                return false;
            }
            if (!this.getAcl().checkModel(model, 'edit')) {
                return false;
            }

            var fieldDefs = this.getMetadata().get(
                ['entityDefs', this.scope, 'fields', fieldName]
            ) || {};

            if (fieldDefs.readOnly) {
                return false;
            }
            if (fieldDefs.inlineEditDisabled) {
                return false;
            }
            if (fieldDefs.notStorable) {
                return false;
            }
            if (!EDITABLE_TYPES.has(fieldDefs.type)) {
                return false;
            }

            // Online-payment rows: provider-sourced attrs are system-owned
            // (detail dynamicLogic alone does not stop list double-click).
            if (this._isOnlinePaymentFieldLocked(model, fieldName)) {
                return false;
            }

            if (this._isDynamicLogicReadOnly(model, fieldName)) {
                return false;
            }

            var rowView = this.getView(modelId);
            if (!rowView) {
                return false;
            }

            var fieldView = rowView.getView(fieldName + 'Field');
            if (!fieldView) {
                return false;
            }

            if (fieldView.mode === 'listLink') {
                return false;
            }

            return true;
        },

        /**
         * Providers that own settlement/status data (Stripe now; Satispay/etc. later).
         * Manual / empty provider rows stay list-editable.
         */
        _onlinePaymentProviders: function () {
            return ['Stripe', 'Satispay', 'Revolut', 'BankTransfer', 'BankApp'];
        },

        _onlinePaymentLockedFields: function () {
            return [
                'amount', 'amountCurrency', 'amountGross', 'amountGrossCurrency',
                'commissionAmount', 'commissionAmountCurrency', 'commissionPercent',
                'amountIn', 'amountInCurrency', 'amountOut', 'amountOutCurrency',
                'entryType', 'transactionDate', 'internalClassification',
                'description', 'name',
                'donationPaymentProvider', 'donationPaymentReference',
                'donationDonorCategory', 'donationFrequency', 'donationComment',
                'paymentStatus', 'financingId',
                'subjectName', 'subjectPartyId', 'subjectPartyType',
                'subjectEmailAddress', 'subjectPhoneNumber',
                'beneficiaryName', 'beneficiaryPartyId', 'beneficiaryPartyType',
                'beneficiaryEmailAddress', 'beneficiaryPhoneNumber',
                'stripePaymentCreatedAt', 'stripeChargeId', 'stripeBalanceTransactionId',
                'stripePaymentMethodType', 'stripeCardBrand', 'stripeCardLast4',
                'stripeReceiptUrl', 'stripeReceiptEmail', 'stripeBillingEmail',
                'stripeBillingPhone', 'stripeFeeDetailsJson', 'stripeLivemode',
                'stripeRadarRiskLevel', 'stripeStatementDescriptor',
                'stripeCustomerId', 'stripeSubscriptionId',
                'stripeInvoiceId', 'stripeInvoiceNumber',
                'stripePayoutId', 'stripePayoutPaidAt',
            ];
        },

        _isOnlinePaymentFieldLocked: function (model, fieldName) {
            if (this.scope !== 'PrimaNota') {
                return false;
            }

            var provider = String(model.get('donationPaymentProvider') || '');
            if (!provider || this._onlinePaymentProviders().indexOf(provider) === -1) {
                return false;
            }

            return this._onlinePaymentLockedFields().indexOf(fieldName) !== -1;
        },

        /**
         * Honour clientDefs.dynamicLogic.fields.{name}.readOnly when present.
         */
        _isDynamicLogicReadOnly: function (model, fieldName) {
            var logic = this.getMetadata().get(
                ['clientDefs', this.scope, 'dynamicLogic', 'fields', fieldName, 'readOnly']
            );

            if (!logic || !logic.conditionGroup || !logic.conditionGroup.length) {
                return false;
            }

            // Minimal equals-only evaluator (PrimaNota locks use type=equals).
            return logic.conditionGroup.every(function (cond) {
                if (!cond || cond.type !== 'equals') {
                    return false;
                }

                return String(model.get(cond.attribute) || '') === String(cond.value || '');
            });
        },

        _getFieldView: function (modelId, fieldName) {
            var rowView = this.getView(modelId);

            return rowView ? rowView.getView(fieldName + 'Field') : null;
        },

        // -- activation -------------------------------------------------------

        _activateCell: function (cellEl) {
            if (this._ilEdit) {
                return;
            }

            var $cell = $(cellEl);
            var fieldName = $cell.data('name');
            var $row = $cell.closest('tr.list-row');
            var modelId = $row.data('id');

            if (!modelId || !fieldName) {
                return;
            }

            var model = this.collection.get(modelId);
            if (!model) {
                return;
            }

            var fieldView = this._getFieldView(modelId, fieldName);
            if (!fieldView) {
                return;
            }

            var origMode = fieldView.mode;

            this._ilEdit = {
                modelId: modelId,
                fieldName: fieldName,
                $cell: $cell,
                model: model,
                fieldView: fieldView,
                origMode: origMode,
                origAttrs: Espo.Utils.cloneDeep(model.attributes),
            };

            $cell.addClass('sh-editing');
            this._unlockOverflowAncestors($cell);

            fieldView.setMode('edit');

            var reRenderResult = fieldView.reRender();
            var promise = (reRenderResult && reRenderResult.then)
                ? reRenderResult
                : Promise.resolve();

            promise.then(function () {
                this._afterEditRender();
            }.bind(this));
        },

        /**
         * Native <select> popups are clipped by any ancestor with overflow != visible.
         * Temporarily force visible up to the panel (restore on finish).
         */
        _unlockOverflowAncestors: function ($cell) {
            var restored = [];
            var el = $cell && $cell[0] ? $cell[0].parentElement : null;
            var stopAt = this.$el && this.$el[0] ? this.$el[0].closest('.panel') : null;

            while (el && el !== document.body) {
                var style = window.getComputedStyle(el);
                var ox = style.overflowX;
                var oy = style.overflowY;

                if ((ox && ox !== 'visible') || (oy && oy !== 'visible')) {
                    restored.push({
                        el: el,
                        overflow: el.style.overflow,
                        overflowX: el.style.overflowX,
                        overflowY: el.style.overflowY,
                    });
                    el.style.overflow = 'visible';
                    el.style.overflowX = 'visible';
                    el.style.overflowY = 'visible';
                }

                if (stopAt && el === stopAt) {
                    break;
                }

                el = el.parentElement;
            }

            if (this._ilEdit) {
                this._ilEdit.overflowRestores = restored;
            }
        },

        _restoreOverflowAncestors: function () {
            if (!this._ilEdit || !this._ilEdit.overflowRestores) {
                return;
            }

            this._ilEdit.overflowRestores.forEach(function (item) {
                item.el.style.overflow = item.overflow;
                item.el.style.overflowX = item.overflowX;
                item.el.style.overflowY = item.overflowY;
            });

            this._ilEdit.overflowRestores = null;
        },

        _afterEditRender: function () {
            if (!this._ilEdit) {
                return;
            }

            var $cell = this._ilEdit.$cell;
            var $input = $cell.find('input, select, textarea').first();

            if ($input.length) {
                $input.focus();

                if ($input.is('input[type="text"], input[type="number"]')) {
                    $input[0].select();
                }

                // Size native select so options paint inside the cell/panel, not clipped.
                if ($input.is('select') && !$input.attr('size')) {
                    var optionCount = Math.min($input.find('option').length || 1, 8);
                    $input.attr('size', optionCount);
                    $input.css({
                        height: 'auto',
                        minHeight: (optionCount * 1.6) + 'em',
                        position: 'absolute',
                        left: 0,
                        top: 0,
                        zIndex: 40,
                        minWidth: Math.max($cell.outerWidth() || 120, 120) + 'px',
                    });
                    $cell.css({ minHeight: (optionCount * 1.6) + 'em' });
                }
            }

            $cell.find('input, select, textarea')
                .off('.sh-ile-cell')
                .on('keydown.sh-ile-cell', function (e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        e.stopPropagation();
                        this._cancel();
                    }
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this._save();
                    }
                }.bind(this));

            $cell.find('select')
                .off('change.sh-ile-cell')
                .on('change.sh-ile-cell', function () {
                    this._save();
                }.bind(this));

            $(document).off('mousedown.sh-ile-away');
            $(document).on('mousedown.sh-ile-away', function (e) {
                if (this._ilEdit && !this._ilEdit.$cell[0].contains(e.target)) {
                    this._save();
                }
            }.bind(this));
        },

        // -- save / cancel ----------------------------------------------------

        _save: function () {
            if (!this._ilEdit) {
                return;
            }

            var state = this._ilEdit;
            var data = state.fieldView.fetch();

            state.model.set(data, {silent: true});

            if (state.fieldView.validate()) {
                return;
            }

            var self = this;

            Espo.Ui.notify(' ');

            state.model.save(data, {patch: true})
                .then(function () {
                    Espo.Ui.success(self.translate('Saved'));
                    self._finish();
                })
                .catch(function () {
                    state.model.set(state.origAttrs, {silent: true});
                    self._finish();
                });
        },

        _cancel: function () {
            if (!this._ilEdit) {
                return;
            }

            this._ilEdit.model.set(this._ilEdit.origAttrs, {silent: true});
            this._finish();
        },

        _finish: function () {
            if (!this._ilEdit) {
                return;
            }

            var state = this._ilEdit;

            $(document).off('mousedown.sh-ile-away');
            this._restoreOverflowAncestors();
            state.$cell.removeClass('sh-editing');
            state.$cell.css({ minHeight: '' });
            state.$cell.find('input, select, textarea').off('.sh-ile-cell');

            state.fieldView.setMode(state.origMode);
            state.fieldView.reRender();

            this._ilEdit = null;
        },
    });
});
