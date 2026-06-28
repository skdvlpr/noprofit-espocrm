define('nonprofit-espocrm:views/fields/date-array', ['views/fields/array', 'ui/datepicker'], function (Dep, Datepicker) {

    return Dep.extend({

        type: 'array',

        editTemplate: 'nonprofit-espocrm:fields/date-array/edit',

        setup() {
            Dep.prototype.setup.call(this);

            this.displayAsList = true;
            this.allowCustomOptions = true;
            this.params.displayAsList = true;
            this.params.noEmptyString = true;
            this.params.itemsEditable = false;

            this.listenTo(this, 'change', () => {
                if (!this.isEditMode()) {
                    return;
                }

                this.model.set(this.fetch(), {ui: true});
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (!this.isEditMode()) {
                return;
            }

            const input = this.$el.find('input.main-element.select').get(0);

            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            this.datepicker = new Datepicker(input, {
                format: this.getDateTime().dateFormat,
                weekStart: this.getDateTime().weekStart,
                todayButton: this.getConfig().get('datepickerTodayButton') || false,
                onChange: () => {
                    const value = input.value.trim();

                    if (!value) {
                        return;
                    }

                    this.addValueFromUi(value);
                    this.focusOnElement();
                },
            });

            this.$el.find('button.date-picker-btn').on('click', () => {
                this.datepicker.show();
            });
        },

        fetch() {
            if (this.isEditMode() && this.$list) {
                this.fetchFromDom();
            }

            return Dep.prototype.fetch.call(this);
        },

        /**
         * @param {string} value
         * @return {string|null}
         */
        normalizeToIso(value) {
            if (value === null || value === undefined) {
                return null;
            }

            value = value.toString().trim();

            if (!value) {
                return null;
            }

            if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                return value;
            }

            const parsed = this.getDateTime().fromDisplayDate(value);

            if (parsed && /^\d{4}-\d{2}-\d{2}$/.test(parsed)) {
                return parsed;
            }

            return null;
        },

        /**
         * @param {string} value
         */
        addValueFromUi(value) {
            value = value.trim();

            if (this.noEmptyString && value === '') {
                return;
            }

            const iso = this.normalizeToIso(value);

            if (!iso) {
                setTimeout(() => {
                    this.showValidationMessage(
                        this.translate('fieldShouldBeDate', 'messages'),
                        'input.select'
                    );
                }, 10);

                return;
            }

            if (this.selected.indexOf(iso) !== -1) {
                this.$select.val('');
                this.controlAddItemButton();

                return;
            }

            this.addValue(iso);
            this.$select.val('');
            this.controlAddItemButton();
        },

        /**
         * @param {string} value
         * @return {string}
         */
        getItemHtml(value) {
            const iso = this.normalizeToIso(value) || value.toString();
            const text = iso ? this.getDateTime().toDisplayDate(iso) : iso;

            const div = document.createElement('div');

            div.className = 'list-group-item';
            div.dataset.value = iso;
            div.style.cursor = 'default';

            if (!this.params.keepItems) {
                const removeBtn = document.createElement('a');

                removeBtn.role = 'button';
                removeBtn.tabIndex = 0;
                removeBtn.classList.add('pull-right');
                removeBtn.dataset.value = iso;
                removeBtn.dataset.action = 'removeValue';
                removeBtn.innerHTML = '<span class="fas fa-times"></span>';
                div.append(removeBtn);
            }

            if (!this.noDragHandle && !this.params.keepItems) {
                const handle = document.createElement('span');

                handle.className = 'drag-handle';
                handle.innerHTML = '<span class="fas fa-grip fa-sm"></span>';
                div.append(handle);
            }

            const textSpan = document.createElement('span');

            textSpan.classList.add('text');
            textSpan.textContent = text;
            div.append(textSpan);

            return div.outerHTML;
        },

        getValueForDisplay() {
            if (!this.selected || this.selected.length === 0) {
                return '';
            }

            const list = this.selected.map(item => {
                const iso = this.normalizeToIso(item);

                if (!iso) {
                    return $('<span>').text(item).get(0).outerHTML;
                }

                return $('<span>').text(this.getDateTime().toDisplayDate(iso)).get(0).outerHTML;
            });

            if (this.displayAsList) {
                const itemClassName = 'multi-enum-item-container';

                return list
                    .map(item => $('<div>').addClass(itemClassName).html(item).get(0).outerHTML)
                    .join('');
            }

            return list.join(', ');
        },
    });
});
