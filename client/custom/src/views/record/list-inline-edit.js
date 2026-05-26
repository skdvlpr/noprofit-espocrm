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
define('custom:views/record/list-inline-edit', ['views/record/list'], function (Dep) {

    const EDITABLE_TYPES = new Set([
        'enum', 'varchar', 'int', 'float', 'bool',
        'date', 'currency', 'multiEnum',
    ]);

    return Dep.extend({

        /** @private Currently active inline edit state, or null. */
        _ilEdit: null,

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
                    ' position: relative; z-index: 10; overflow: visible; }',
                '.list-row td.cell.sh-editing select.form-control,' +
                    '.list-row td.cell.sh-editing input.form-control {' +
                    ' height: auto; padding: 2px 8px; font-size: inherit; min-width: 80px; }',
                '.list-row td.cell.sh-editing select.form-control { position: relative; z-index: 11; }',
                '.list-row td.cell.sh-editing select.form-control option {' +
                    ' background: var(--form-element-bg-color, var(--secondary-bg-color, #fff));' +
                    ' color: var(--form-element-color, var(--text-color, #333)); }',
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
            if (fieldDefs.notStorable) {
                return false;
            }
            if (!EDITABLE_TYPES.has(fieldDefs.type)) {
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

            fieldView.setMode('edit');

            var reRenderResult = fieldView.reRender();
            var promise = (reRenderResult && reRenderResult.then)
                ? reRenderResult
                : Promise.resolve();

            promise.then(function () {
                this._afterEditRender();
            }.bind(this));
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
            state.$cell.removeClass('sh-editing');
            state.$cell.find('input, select, textarea').off('.sh-ile-cell');

            state.fieldView.setMode(state.origMode);
            state.fieldView.reRender();

            this._ilEdit = null;
        },
    });
});
