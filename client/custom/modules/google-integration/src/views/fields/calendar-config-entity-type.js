define('google-integration:views/fields/calendar-config-entity-type', ['exports', 'views/fields/base'], function (_exports, _base) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _base = _interopRequireDefault(_base);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    class CalendarConfigEntityTypeField extends _base.default {
        detailTemplateContent = '<span>{{value}}</span>';

        editTemplateContent = `
            <select class="form-control" data-name="{{name}}">
                {{#each optionList}}
                    <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                {{/each}}
            </select>
        `;

        setup() {
            this.allowedEntityList = null;
            this.labelByEntityType = {};

            this.wait(
                Espo.Ajax.getRequest('GoogleIntegration/calendar/date-capable-entity-types')
                    .then(data => {
                        const list = Array.isArray(data.list) ? data.list : [];
                        this.allowedEntityList = [];

                        list.forEach(item => {
                            const entityType = typeof item.entityType === 'string' ? item.entityType : '';
                            const label = typeof item.label === 'string' ? item.label.trim() : '';

                            if (entityType && label) {
                                this.allowedEntityList.push(entityType);
                                this.labelByEntityType[entityType] = label;
                            }
                        });

                        this.allowedEntityList = [...new Set(this.allowedEntityList)];
                    })
                    .catch(() => {
                        this.allowedEntityList = [];
                    })
            );

            super.setup();
        }

        data() {
            return {
                value: this.getDisplayValue(),
                optionList: this.getOptionList(),
            };
        }

        getOptionList() {
            const source = Array.isArray(this.allowedEntityList) ? this.allowedEntityList : [];
            let options = source.filter(entityType => {
                return this.getMetadata().get(`scopes.${entityType}.entity`)
                    && this.getAcl().checkScope(entityType);
            });

            const current = this.model.get(this.name);

            if (current && this.labelByEntityType[current] && !options.includes(current)) {
                options = [current, ...options];
            }

            return options.map(value => ({
                value,
                label: this.translateOption(value),
                selected: value === current,
            }));
        }

        translateOption(value) {
            return this.labelByEntityType && this.labelByEntityType[value]
                ? this.labelByEntityType[value]
                : '';
        }

        getDisplayValue() {
            const value = this.model.get(this.name);

            if (!value) {
                return '';
            }

            return this.translateOption(value);
        }

        afterRender() {
            super.afterRender();

            if (!this.isEditMode()) {
                return;
            }

            const $select = this.$el.find('select');

            const applySelectValue = value => {
                const normalized = value || null;
                const previous = this.model.get(this.name);

                this.model.set(this.name, normalized, {ui: true});

                if (normalized !== previous) {
                    this.model.set({
                        dateField: null,
                        endDateField: null,
                    }, {ui: true});
                }
            };

            // Browser may show the first option while model value is still empty on create.
            if (!this.model.get(this.name)) {
                const initialValue = $select.val() || null;

                if (initialValue) {
                    applySelectValue(initialValue);
                }
            }

            $select.on('change', e => {
                applySelectValue(e.currentTarget.value || null);
            });
        }

        fetch() {
            if (this.mode !== this.MODE_EDIT) {
                return {};
            }

            const value = this.$el.find('select').val();

            return {
                [this.name]: value || null,
            };
        }
    }

    _exports.default = CalendarConfigEntityTypeField;
});
