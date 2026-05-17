define('google-integration:views/fields/google-calendar-reminders', ['exports', 'views/fields/base'], function (_exports, _base) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _base = _interopRequireDefault(_base);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    const METHOD_LIST = ['popup', 'email'];
    const UNIT_LIST = ['minutes', 'hours', 'days', 'weeks'];
    const MAX_ITEMS = 5;
    const MAX_MINUTES = 40320;

    class GoogleCalendarRemindersField extends _base.default {
        detailTemplateContent = `
            {{#if itemList.length}}
                <ul class="list-unstyled">
                    {{#each itemList}}
                        <li>{{methodLabel}}: {{amount}} {{unitLabel}}</li>
                    {{/each}}
                </ul>
            {{else}}
                <span class="none-value">{{translate 'None'}}</span>
            {{/if}}
        `;

        editTemplateContent = `
            <div data-role="google-calendar-reminders">
                <div class="list-container">
                    {{#each itemList}}
                        <div class="google-calendar-reminder-row row margin-bottom-sm" data-index="{{@index}}">
                            <div class="col-sm-3">
                                <select class="form-control input-sm" data-role="method">
                                    {{#each methodOptionList}}
                                        <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                                    {{/each}}
                                </select>
                            </div>
                            <div class="col-sm-3">
                                <input class="form-control input-sm" type="number" min="0" data-role="amount" value="{{amount}}">
                            </div>
                            <div class="col-sm-4">
                                <select class="form-control input-sm" data-role="unit">
                                    {{#each unitOptionList}}
                                        <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                                    {{/each}}
                                </select>
                            </div>
                            <div class="col-sm-2">
                                <button type="button" class="btn btn-default btn-sm" data-action="removeReminder" data-index="{{@index}}">
                                    <span class="fas fa-times"></span>
                                </button>
                            </div>
                        </div>
                    {{/each}}
                </div>
                <button type="button" class="btn btn-default btn-sm" data-action="addReminder">
                    {{translate 'Add'}}
                </button>
                <div class="text-muted small margin-top">
                    Max 5 reminders. Max offset is 4 weeks.
                </div>
            </div>
        `;

        setup() {
            super.setup();

            this.addActionHandler('addReminder', () => this.addReminder());
            this.addActionHandler('removeReminder', (e, target) => this.removeReminder(Number(target.dataset.index)));
        }

        data() {
            const list = this.getList();

            return {
                itemList: list.map(item => ({
                    ...item,
                    methodLabel: this.translateOption(item.method),
                    unitLabel: this.translateOption(item.unit),
                    methodOptionList: this.getMethodOptionList(item.method),
                    unitOptionList: this.getUnitOptionList(item.unit),
                })),
                methodOptionList: this.getMethodOptionList('popup'),
                unitOptionList: this.getUnitOptionList('days'),
            };
        }

        afterRender() {
            super.afterRender();

            if (this.mode !== 'edit') {
                return;
            }

            this.$el.find('[data-role="google-calendar-reminders"]').on('change input', () => {
                this.model.set(this.name, this.readRows(), {ui: true});
            });
        }

        fetch() {
            const data = {};
            data[this.name] = this.readRows();

            return data;
        }

        validate() {
            if (super.validate()) {
                return true;
            }

            const rows = this.readRows();

            if (rows.length > MAX_ITEMS) {
                this.showValidationMessage(`Max ${MAX_ITEMS} reminders.`);
                return true;
            }

            for (const row of rows) {
                if (!METHOD_LIST.includes(row.method) || !UNIT_LIST.includes(row.unit)) {
                    this.showValidationMessage('Invalid reminder.');
                    return true;
                }

                const minutes = this.toMinutes(row);

                if (minutes < 0 || minutes > MAX_MINUTES) {
                    this.showValidationMessage('Reminder must be between 0 minutes and 4 weeks.');
                    return true;
                }
            }

            return false;
        }

        addReminder() {
            const list = this.getList();

            if (list.length >= MAX_ITEMS) {
                return;
            }

            list.push({method: 'popup', amount: 1, unit: 'days'});
            this.model.set(this.name, list, {ui: true});
            this.reRender();
        }

        removeReminder(index) {
            const list = this.getList();
            list.splice(index, 1);
            this.model.set(this.name, list, {ui: true});
            this.reRender();
        }

        getList() {
            const value = this.model.get(this.name);

            if (!Array.isArray(value)) {
                return [];
            }

            return value
                .filter(item => item && typeof item === 'object')
                .slice(0, MAX_ITEMS)
                .map(item => ({
                    method: METHOD_LIST.includes(item.method) ? item.method : 'popup',
                    amount: Math.max(0, Number.parseInt(item.amount, 10) || 0),
                    unit: UNIT_LIST.includes(item.unit) ? item.unit : 'days',
                }));
        }

        readRows() {
            const rows = [];

            this.$el.find('.google-calendar-reminder-row').each((i, element) => {
                const $row = $(element);
                rows.push({
                    method: $row.find('[data-role="method"]').val() || 'popup',
                    amount: Math.max(0, Number.parseInt($row.find('[data-role="amount"]').val(), 10) || 0),
                    unit: $row.find('[data-role="unit"]').val() || 'days',
                });
            });

            return rows.slice(0, MAX_ITEMS);
        }

        toMinutes(row) {
            const amount = Number.parseInt(row.amount, 10) || 0;

            if (row.unit === 'weeks') {
                return amount * 7 * 24 * 60;
            }

            if (row.unit === 'days') {
                return amount * 24 * 60;
            }

            if (row.unit === 'hours') {
                return amount * 60;
            }

            return amount;
        }

        getMethodOptionList(selected) {
            return METHOD_LIST.map(value => ({
                value,
                label: this.translateOption(value),
                selected: value === selected,
            }));
        }

        getUnitOptionList(selected) {
            return UNIT_LIST.map(value => ({
                value,
                label: this.translateOption(value),
                selected: value === selected,
            }));
        }

        translateOption(value) {
            return this.getLanguage().translateOption(value, this.name, this.model.entityType);
        }
    }

    _exports.default = GoogleCalendarRemindersField;
});
