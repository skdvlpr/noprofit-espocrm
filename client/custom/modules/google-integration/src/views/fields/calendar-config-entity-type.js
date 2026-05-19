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
                        this.allowedEntityList = list.map(item => item.entityType).filter(Boolean);

                        list.forEach(item => {
                            if (item.entityType) {
                                this.labelByEntityType[item.entityType] = item.label || item.entityType;
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

            if (current && !options.includes(current)) {
                options = [current, ...options];
            }

            return options.map(value => ({
                value,
                label: this.translateOption(value),
                selected: value === current,
            }));
        }

        translateOption(value) {
            if (this.labelByEntityType && this.labelByEntityType[value]) {
                return this.labelByEntityType[value];
            }

            const translated = this.getLanguage().translate(value, 'scopeNames');

            if (translated && translated !== value) {
                return translated;
            }

            return this.getLanguage().translate(value, 'scopeNames', 'Global') || value;
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

            if (this.mode !== this.MODE_EDIT) {
                return;
            }

            this.$el.find('select').on('change', e => {
                this.model.set(this.name, e.currentTarget.value || null, {ui: true});
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
