define('nonprofit-espocrm:views/activity-offer/fields/week-slots', ['views/fields/base'], function (Dep) {

    /**
     * WhatsApp-style weekly shift generator (1–7 weekday rows).
     * Edit mode: Espo-standard form rows (no auto-name). Detail: read-only table.
     */
    return Dep.extend({

        detailTemplateContent: `
            {{#if rows.length}}
                <div class="list" style="overflow-x:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{dayText}}</th>
                                <th>{{nameText}}</th>
                                <th>{{requiredText}}</th>
                                <th>{{placeText}}</th>
                                <th>{{timeText}}</th>
                                <th>{{conditionsText}}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{#each rows}}
                                <tr>
                                    <td>{{dayLabel}}</td>
                                    <td>{{previewName}}</td>
                                    <td>{{requiredCount}}</td>
                                    <td class="small">{{placeLabel}}</td>
                                    <td>
                                        {{#if isAllDay}}
                                            {{../allDayText}}
                                        {{else}}
                                            {{timeStart}} – {{timeEnd}}
                                            {{#if durationLabel}} ({{durationLabel}}){{/if}}
                                        {{/if}}
                                    </td>
                                    <td class="small">{{conditionsLabel}}</td>
                                </tr>
                            {{/each}}
                        </tbody>
                    </table>
                </div>
            {{else}}
                <span class="text-muted">{{emptyText}}</span>
            {{/if}}
        `,

        editTemplateContent: `
            <div class="week-slots-editor">
                {{#each rows}}
                    <div class="panel panel-default week-slot-row" data-index="{{@index}}"
                         style="margin-bottom: 1em;">
                        <div class="panel-body" style="padding: 12px 15px;">
                            <div class="row">
                                <div class="cell col-sm-3 form-group">
                                    <label class="control-label">{{../dayText}}</label>
                                    <div class="field">
                                        <select class="form-control" data-name="dayOfWeek">
                                            {{#each dayOptions}}
                                                <option value="{{value}}"{{#if selected}} selected{{/if}}>
                                                    {{label}}
                                                </option>
                                            {{/each}}
                                        </select>
                                    </div>
                                </div>
                                <div class="cell col-sm-3 form-group">
                                    <label class="control-label">{{../categoryText}}</label>
                                    <div class="field">
                                        <select class="form-control" data-name="category">
                                            {{#each categoryOptions}}
                                                <option value="{{value}}"{{#if selected}} selected{{/if}}>
                                                    {{label}}
                                                </option>
                                            {{/each}}
                                        </select>
                                    </div>
                                </div>
                                <div class="cell col-sm-3 form-group">
                                    <label class="control-label">{{../requiredText}}</label>
                                    <div class="field">
                                        <input type="number" class="form-control" data-name="requiredCount"
                                               min="0" max="99" value="{{requiredCount}}">
                                    </div>
                                </div>
                                <div class="cell col-sm-3 form-group">
                                    <label class="control-label">{{../allDayText}}</label>
                                    <div class="field" style="display:flex;align-items:center;gap:8px;min-height:34px;">
                                        <input type="checkbox" data-name="isAllDay"
                                               {{#if isAllDay}}checked{{/if}}>
                                        {{#unless isOnly}}
                                            <button type="button" class="btn btn-default btn-sm"
                                                    data-action="removeRow" title="{{../removeText}}">
                                                <span class="fas fa-times"></span>
                                            </button>
                                        {{/unless}}
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="cell col-sm-12 form-group">
                                    <label class="control-label">{{../placeText}}</label>
                                    <div class="field">
                                        {{#if ../uniqueAddress}}
                                            {{#if ../sharedPlaceFormatted}}
                                                <div class="complex-text">{{breaklines ../sharedPlaceFormatted}}</div>
                                            {{else}}
                                                <span class="none-value">{{../noneText}}</span>
                                            {{/if}}
                                        {{else}}
                                            <textarea
                                                class="form-control auto-height"
                                                data-name="placeStreet"
                                                rows="1"
                                                placeholder="{{../streetPlaceholder}}"
                                                style="resize: none;"
                                            >{{placeStreet}}</textarea>
                                            <div class="row" style="margin-top: 0.35em; margin-left: 0; margin-right: 0;">
                                                <div class="col-sm-4 col-xs-4">
                                                    <input type="text" class="form-control"
                                                           data-name="placeCity"
                                                           value="{{placeCity}}"
                                                           placeholder="{{../cityPlaceholder}}">
                                                </div>
                                                <div class="col-sm-4 col-xs-4">
                                                    <input type="text" class="form-control"
                                                           data-name="placeState"
                                                           value="{{placeState}}"
                                                           placeholder="{{../statePlaceholder}}">
                                                </div>
                                                <div class="col-sm-4 col-xs-4">
                                                    <input type="text" class="form-control"
                                                           data-name="placePostalCode"
                                                           value="{{placePostalCode}}"
                                                           placeholder="{{../postalCodePlaceholder}}">
                                                </div>
                                            </div>
                                            <input type="text" class="form-control"
                                                   data-name="placeCountry"
                                                   value="{{placeCountry}}"
                                                   placeholder="{{../countryPlaceholder}}"
                                                   style="margin-top: 0.35em;">
                                        {{/if}}
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="cell col-sm-4 form-group">
                                    <label class="control-label">{{../startText}}</label>
                                    <div class="field">
                                        <input type="time" class="form-control" data-name="timeStart"
                                               value="{{timeStart}}"
                                               {{#if isAllDay}}disabled{{/if}}>
                                    </div>
                                </div>
                                <div class="cell col-sm-4 form-group">
                                    <label class="control-label">{{../endText}}</label>
                                    <div class="field">
                                        <input type="time" class="form-control" data-name="timeEnd"
                                               value="{{timeEnd}}"
                                               {{#if isAllDay}}disabled{{/if}}>
                                    </div>
                                </div>
                                <div class="cell col-sm-4 form-group">
                                    <label class="control-label">{{../durationText}}</label>
                                    <div class="field">
                                        <select class="form-control" data-name="durationSeconds"
                                                {{#if isAllDay}}disabled{{/if}}>
                                            {{#each durationOptions}}
                                                <option value="{{value}}"{{#if selected}} selected{{/if}}>
                                                    {{label}}
                                                </option>
                                            {{/each}}
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="cell col-sm-12 form-group" style="margin-bottom: 0;">
                                    <label class="control-label">{{../conditionsText}}</label>
                                    <div class="field">
                                        <div class="conditions-list">
                                            {{#each conditions}}
                                                <div class="input-group" style="margin-bottom: 0.35em;"
                                                     data-cond-index="{{@index}}">
                                                    <input type="text" class="form-control" data-name="condition"
                                                           maxlength="200" value="{{this}}">
                                                    <span class="input-group-btn">
                                                        <button type="button" class="btn btn-default"
                                                                data-action="removeCondition">
                                                            <span class="fas fa-times"></span>
                                                        </button>
                                                    </span>
                                                </div>
                                            {{/each}}
                                        </div>
                                        {{#if canAddCondition}}
                                            <button type="button" class="btn btn-link btn-sm"
                                                    data-action="addCondition" style="padding-left: 0;">
                                                <span class="fas fa-plus"></span> {{../addConditionText}}
                                            </button>
                                        {{/if}}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                {{/each}}
                {{#if canAddRow}}
                    <button type="button" class="btn btn-default" data-action="addRow">
                        <span class="fas fa-plus"></span> {{addDayText}}
                    </button>
                {{/if}}
            </div>
        `,

        durationOptionSeconds: [900, 1800, 3600, 7200, 10800, 14400, 28800, 86400],

        data: function () {
            const uniqueAddress = !!this.model.get('uniqueAddress');
            const sharedPlaceFormatted = this.formatAddressParts({
                placeStreet: this.model.get('placeStreet'),
                placeCity: this.model.get('placeCity'),
                placeState: this.model.get('placeState'),
                placePostalCode: this.model.get('placePostalCode'),
                placeCountry: this.model.get('placeCountry'),
            });
            const rows = (this.rows || []).map(row => {
                const conditions = row.conditions || [];
                const durationSeconds = this.resolveDurationSeconds(row);

                return Object.assign({}, row, {
                    previewName: this.buildPreviewName(row),
                    placeLabel: uniqueAddress
                        ? sharedPlaceFormatted
                        : this.formatAddressParts(row),
                    durationLabel: this.formatDurationLabel(durationSeconds),
                    durationOptions: this.getDurationOptions(durationSeconds),
                    conditionsLabel: conditions.join('; '),
                    canAddCondition: conditions.length < 5,
                    isOnly: this.rows.length <= 1,
                    dayLabel: this.dayLabel(row.dayOfWeek),
                    dayOptions: this.getDayOptions(row.dayOfWeek),
                    categoryOptions: this.getCategoryOptions(row.category),
                    isAllDay: !!row.isAllDay,
                });
            });

            return {
                rows: rows,
                uniqueAddress: uniqueAddress,
                sharedPlaceFormatted: sharedPlaceFormatted,
                canAddRow: this.mode === 'edit' && this.rows.length < 7,
                dayText: this.translate('dayOfWeek', 'fields', 'ActivityOfferSlot'),
                categoryText: this.translate('category', 'fields', 'ActivityOfferSlot'),
                nameText: this.translate('name', 'fields', 'ActivityOfferSlot'),
                requiredText: this.translate('requiredCount', 'fields', 'ActivityOfferSlot'),
                placeText: this.translate('place', 'fields', 'ActivityOfferSlot'),
                timeText: this.translate('dateStart', 'fields', 'ActivityOfferSlot'),
                startText: this.translate('dateStart', 'fields', 'ActivityOfferSlot'),
                endText: this.translate('dateEnd', 'fields', 'ActivityOfferSlot'),
                durationText: this.translate('duration', 'fields', 'ActivityOfferSlot'),
                conditionsText: this.translate('conditions', 'fields', 'ActivityOfferSlot'),
                allDayText: this.translate('allDay', 'labels', 'ActivityOffer') ||
                    this.translate('All-Day'),
                addDayText: this.translate('addWeekDay', 'labels', 'ActivityOffer'),
                addConditionText: this.translate('addCondition', 'labels', 'ActivityOffer'),
                removeText: this.translate('Remove'),
                emptyText: this.translate('None'),
                noneText: this.translate('None'),
                streetPlaceholder: this.translate('Street'),
                cityPlaceholder: this.translate('City'),
                statePlaceholder: this.translate('State'),
                postalCodePlaceholder: this.translate('PostalCode'),
                countryPlaceholder: this.translate('Country'),
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.days = [
                'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
            ];

            this.seedEmpty = !!(this.options && this.options.seedEmpty);

            this.rows = this.normalizeRows(this.model.get(this.name));

            if ((!this.rows || this.rows.length === 0) && this.model.id && !this.seedEmpty) {
                this.wait(this.loadExistingSlots());
            } else if (!this.rows || this.rows.length === 0) {
                this.rows = [this.defaultRow('Monday')];
            }

            this.listenTo(
                this.model,
                'change:uniqueAddress change:placeStreet change:placeCity change:placeState ' +
                'change:placePostalCode change:placeCountry change:category change:weekStart',
                () => {
                    if (this.mode === 'edit') {
                        this.reRender();
                    }
                }
            );
        },

        afterRender: function () {
            if (this.mode !== 'edit') {
                return;
            }

            this.$el.find('.week-slot-row').on('change', '[data-name]', e => {
                const $row = $(e.currentTarget).closest('.week-slot-row');
                const index = parseInt($row.attr('data-index'), 10);
                const name = e.currentTarget.getAttribute('data-name');

                this.captureFromDom();

                if (name === 'isAllDay') {
                    this.applyAllDay(index, !!e.currentTarget.checked);
                    this.reRender();

                    return;
                }

                if (name === 'durationSeconds' && !this.rows[index].isAllDay) {
                    this.applyDuration(index, parseInt(e.currentTarget.value, 10));
                    this.reRender();

                    return;
                }

                if ((name === 'timeStart' || name === 'timeEnd') && !this.rows[index].isAllDay) {
                    this.reRender();
                }
            });

            this.$el.find('[data-action="addRow"]').on('click', () => this.actionAddRow());
            this.$el.find('[data-action="removeRow"]').on('click', e => {
                const index = parseInt(
                    $(e.currentTarget).closest('.week-slot-row').attr('data-index'),
                    10
                );
                this.actionRemoveRow(index);
            });
            this.$el.find('[data-action="addCondition"]').on('click', e => {
                const index = parseInt(
                    $(e.currentTarget).closest('.week-slot-row').attr('data-index'),
                    10
                );
                this.actionAddCondition(index);
            });
            this.$el.find('[data-action="removeCondition"]').on('click', e => {
                const $row = $(e.currentTarget).closest('.week-slot-row');
                const rowIndex = parseInt($row.attr('data-index'), 10);
                const condIndex = parseInt(
                    $(e.currentTarget).closest('[data-cond-index]').attr('data-cond-index'),
                    10
                );
                this.actionRemoveCondition(rowIndex, condIndex);
            });
            this.$el.find('[data-name="condition"]').on('keydown', e => {
                if (e.key !== 'Enter' && e.keyCode !== 13) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                const $row = $(e.currentTarget).closest('.week-slot-row');
                const index = parseInt($row.attr('data-index'), 10);

                this.actionAddCondition(index);
            });

            this.initGooglePlacesOnDayCards();
        },

        initGooglePlacesOnDayCards: function () {
            if (this.model.get('uniqueAddress')) {
                return;
            }

            const apiKey = this.getConfig().get('googleMapsApiKey');

            if (!apiKey) {
                return;
            }

            this.loadGooglePlaces(apiKey)
                .then(() => {
                    this.$el.find('.week-slot-row').each((i, el) => {
                        this.setupPlacesAutocompleteForRow($(el));
                    });
                })
                .catch(err => {
                    console.warn('[week-slots] Google Places unavailable:', err && err.message);
                });
        },

        loadGooglePlaces: function (apiKey) {
            if (window.google && window.google.maps && window.google.maps.places) {
                return Promise.resolve();
            }

            if (window.__vadWeekSlotsPlacesPromise) {
                return window.__vadWeekSlotsPlacesPromise;
            }

            window.__vadWeekSlotsPlacesPromise = new Promise((resolve, reject) => {
                const callbackName = '__vadWeekSlotsPlacesLoaded';

                window[callbackName] = () => {
                    delete window[callbackName];
                    resolve();
                };

                const script = document.createElement('script');
                script.async = true;
                script.defer = true;
                script.onerror = () => {
                    window.__vadWeekSlotsPlacesPromise = null;
                    reject(new Error('Google Maps Places script failed to load'));
                };
                script.src = 'https://maps.googleapis.com/maps/api/js?'
                    + 'key=' + encodeURIComponent(apiKey)
                    + '&libraries=places,marker'
                    + '&callback=' + callbackName
                    + '&loading=async';

                document.head.appendChild(script);
            });

            return window.__vadWeekSlotsPlacesPromise;
        },

        setupPlacesAutocompleteForRow: function ($row) {
            const $street = $row.find('[data-name="placeStreet"]');

            if (!$street.length || $street.data('placesBound')) {
                return;
            }

            const input = $street.get(0);

            if (!input || !window.google || !google.maps || !google.maps.places) {
                return;
            }

            $street.data('placesBound', true);

            const autocomplete = new google.maps.places.Autocomplete(input, {
                fields: ['address_components', 'formatted_address'],
                // No types filter: allow streets and localities (e.g. city names).
            });

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();

                if (!place || !place.address_components) {
                    return;
                }

                this.fillDayCardAddressFromComponents($row, place.address_components);
                this.captureFromDom();
            });

            this.once('remove', () => {
                if (google.maps.event) {
                    google.maps.event.clearInstanceListeners(autocomplete);
                }
            });
        },

        fillDayCardAddressFromComponents: function ($row, components) {
            const pick = (type, useShort) => {
                const part = components.find(item => item.types && item.types.includes(type));

                if (!part) {
                    return '';
                }

                return useShort ? part.short_name : part.long_name;
            };

            const street = [pick('route'), pick('street_number')].filter(Boolean).join(' ');
            const city = pick('locality')
                || pick('postal_town')
                || pick('administrative_area_level_3')
                || pick('administrative_area_level_2');
            const state = pick('administrative_area_level_1', true)
                || pick('administrative_area_level_1');
            const postal = pick('postal_code');
            const country = pick('country');

            if (street) {
                $row.find('[data-name="placeStreet"]').val(street);
            }

            if (city) {
                $row.find('[data-name="placeCity"]').val(city);
            }

            if (state) {
                $row.find('[data-name="placeState"]').val(state);
            }

            if (postal) {
                $row.find('[data-name="placePostalCode"]').val(postal);
            }

            if (country) {
                $row.find('[data-name="placeCountry"]').val(country);
            }
        },

        fetch: function () {
            this.captureFromDom();

            const data = {};
            data[this.name] = this.rows.map(row => ({
                id: row.id || null,
                dayOfWeek: row.dayOfWeek,
                category: row.category || this.model.get('category') || '',
                isAllDay: !!row.isAllDay,
                timeStart: row.isAllDay ? '00:00' : row.timeStart,
                timeEnd: row.isAllDay ? '23:59' : row.timeEnd,
                durationSeconds: this.resolveDurationSeconds(row),
                requiredCount: parseInt(row.requiredCount, 10) || 0,
                placeStreet: row.placeStreet || '',
                placeCity: row.placeCity || '',
                placeState: row.placeState || '',
                placePostalCode: row.placePostalCode || '',
                placeCountry: row.placeCountry || '',
                conditions: (row.conditions || []).filter(c => !!String(c).trim()).slice(0, 5),
            }));

            return data;
        },

        validateRequired: function () {
            if (!this.isRequired()) {
                return false;
            }

            this.captureFromDom();

            if (!this.rows || this.rows.length < 1) {
                const msg = this.translate('fieldIsRequired', 'messages')
                    .replace('{field}', this.translate(this.name, 'fields', this.model.entityType));

                this.showValidationMessage(msg);

                return true;
            }

            return false;
        },

        captureFromDom: function () {
            if (this.mode !== 'edit' || !this.$el) {
                return;
            }

            const rows = [];

            this.$el.find('.week-slot-row').each((i, el) => {
                const $el = $(el);
                const prev = this.rows[i] || {};
                const conditions = [];

                $el.find('[data-name="condition"]').each((_, input) => {
                    conditions.push(input.value);
                });

                const isAllDay = $el.find('[data-name="isAllDay"]').is(':checked');

                const placeStreetEl = $el.find('[data-name="placeStreet"]');
                const hasPlaceInputs = placeStreetEl.length > 0;

                rows.push({
                    id: prev.id || null,
                    dayOfWeek: $el.find('[data-name="dayOfWeek"]').val() || 'Monday',
                    category: $el.find('[data-name="category"]').val()
                        || prev.category
                        || this.model.get('category')
                        || '',
                    isAllDay: isAllDay,
                    timeStart: $el.find('[data-name="timeStart"]').val() || prev.timeStart || '10:30',
                    timeEnd: $el.find('[data-name="timeEnd"]').val() || prev.timeEnd || '12:30',
                    requiredCount: $el.find('[data-name="requiredCount"]').val() || 1,
                    placeStreet: hasPlaceInputs
                        ? (placeStreetEl.val() || '')
                        : (prev.placeStreet || ''),
                    placeCity: hasPlaceInputs
                        ? ($el.find('[data-name="placeCity"]').val() || '')
                        : (prev.placeCity || ''),
                    placeState: hasPlaceInputs
                        ? ($el.find('[data-name="placeState"]').val() || '')
                        : (prev.placeState || ''),
                    placePostalCode: hasPlaceInputs
                        ? ($el.find('[data-name="placePostalCode"]').val() || '')
                        : (prev.placePostalCode || ''),
                    placeCountry: hasPlaceInputs
                        ? ($el.find('[data-name="placeCountry"]').val() || '')
                        : (prev.placeCountry || ''),
                    conditions: conditions,
                });
            });

            this.rows = rows;
            this.model.set(this.name, rows, {silent: true});
        },

        applyAllDay: function (index, isAllDay) {
            const row = this.rows[index];

            if (!row) {
                return;
            }

            row.isAllDay = isAllDay;

            if (isAllDay) {
                row.timeStart = '00:00';
                row.timeEnd = '23:59';
            } else if (row.timeStart === '00:00' && row.timeEnd === '23:59') {
                row.timeStart = '10:30';
                row.timeEnd = '12:30';
            }
        },

        applyDuration: function (index, seconds) {
            const row = this.rows[index];

            if (!row || !seconds || row.isAllDay) {
                return;
            }

            const startMins = this.timeToMinutes(row.timeStart);

            if (startMins === null) {
                return;
            }

            const endMins = Math.min(startMins + Math.floor(seconds / 60), 24 * 60 - 1);
            row.timeEnd = this.minutesToTime(endMins);
        },

        actionAddRow: function () {
            this.captureFromDom();

            if (this.rows.length >= 7) {
                return;
            }

            // Same weekday may appear more than once (multiple batches / shifts).
            const lastDay = this.rows.length
                ? this.rows[this.rows.length - 1].dayOfWeek
                : 'Monday';

            this.rows.push(this.defaultRow(lastDay));
            this.reRender();
        },

        actionRemoveRow: function (index) {
            this.captureFromDom();

            if (this.rows.length <= 1) {
                return;
            }

            this.rows.splice(index, 1);
            this.reRender();
        },

        actionAddCondition: function (index) {
            this.captureFromDom();
            const row = this.rows[index];

            if (!row || (row.conditions || []).length >= 5) {
                return;
            }

            row.conditions = row.conditions || [];
            row.conditions.push('');
            this.reRender();

            window.setTimeout(() => {
                const $inputs = this.$el
                    .find('.week-slot-row[data-index="' + index + '"] [data-name="condition"]');

                if ($inputs.length) {
                    $inputs.last().focus();
                }
            }, 0);
        },

        actionRemoveCondition: function (rowIndex, condIndex) {
            this.captureFromDom();
            const row = this.rows[rowIndex];

            if (!row || !row.conditions) {
                return;
            }

            row.conditions.splice(condIndex, 1);
            this.reRender();
        },

        loadExistingSlots: function () {
            return Espo.Ajax
                .getRequest('ActivityOffer/' + this.model.id + '/slots', {
                    maxSize: 20,
                    orderBy: 'dateStart',
                    order: 'asc',
                    select: 'id,name,category,dateStart,dateEnd,requiredCount,placeStreet,placeCity,placeState,placePostalCode,placeCountry,conditions,dayOfWeek,isAllDay',
                })
                .then(response => {
                    const list = (response && response.list) || [];

                    if (!list.length) {
                        this.rows = [this.defaultRow('Monday')];

                        return;
                    }

                    this.rows = list.map(slot => this.slotToRow(slot));
                    this.model.set(this.name, this.rows, {silent: true});
                })
                .catch(() => {
                    this.rows = [this.defaultRow('Monday')];
                });
        },

        slotToRow: function (slot) {
            const start = this.parseDateTimeParts(slot.dateStart);
            const end = this.parseDateTimeParts(slot.dateEnd);
            let conditions = slot.conditions || [];

            if (typeof conditions === 'string') {
                try {
                    conditions = JSON.parse(conditions);
                } catch (e) {
                    conditions = [];
                }
            }

            const isAllDay = !!slot.isAllDay ||
                (start.time === '00:00' && (end.time === '23:59' || end.time === '00:00'));

            return {
                id: slot.id,
                dayOfWeek: slot.dayOfWeek || this.dayFromDate(slot.dateStart) || 'Monday',
                category: slot.category || this.model.get('category') || '',
                isAllDay: isAllDay,
                timeStart: start.time || '10:30',
                timeEnd: end.time || '12:30',
                requiredCount: slot.requiredCount != null ? slot.requiredCount : 1,
                placeStreet: slot.placeStreet || '',
                placeCity: slot.placeCity || '',
                placeState: slot.placeState || '',
                placePostalCode: slot.placePostalCode || '',
                placeCountry: slot.placeCountry || '',
                conditions: Array.isArray(conditions) ? conditions : [],
            };
        },

        parseDateTimeParts: function (value) {
            if (!value) {
                return {date: '', time: ''};
            }

            const m = this.getDateTime().toMoment(value);

            return {
                date: m.format('YYYY-MM-DD'),
                time: m.format('HH:mm'),
            };
        },

        dayFromDate: function (value) {
            if (!value) {
                return null;
            }

            const map = [
                'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
            ];

            return map[this.getDateTime().toMoment(value).day()] || null;
        },

        getCategoryOptions: function (selected) {
            const list = this.getMetadata()
                .get(['entityDefs', 'ActivityOfferSlot', 'fields', 'category', 'options']) || [];

            const options = list.map(value => ({
                value: value,
                label: this.getLanguage().translateOption(value, 'category', 'ActivityOfferSlot'),
                selected: value === selected,
            }));

            if (!selected) {
                options.unshift({
                    value: '',
                    label: '—',
                    selected: true,
                });
            }

            return options;
        },

        defaultRow: function (dayOfWeek) {
            return {
                id: null,
                dayOfWeek: dayOfWeek,
                category: this.model.get('category') || '',
                isAllDay: false,
                timeStart: '10:30',
                timeEnd: '12:30',
                requiredCount: 1,
                placeStreet: '',
                placeCity: '',
                placeState: '',
                placePostalCode: '',
                placeCountry: '',
                conditions: [],
            };
        },

        normalizeRows: function (raw) {
            if (!raw) {
                return [];
            }

            if (typeof raw === 'string') {
                try {
                    raw = JSON.parse(raw);
                } catch (e) {
                    return [];
                }
            }

            if (!Array.isArray(raw)) {
                return [];
            }

            return raw.map(row => ({
                id: row.id || null,
                dayOfWeek: row.dayOfWeek || 'Monday',
                category: row.category || '',
                isAllDay: !!row.isAllDay,
                timeStart: row.timeStart || '10:30',
                timeEnd: row.timeEnd || '12:30',
                requiredCount: row.requiredCount != null ? row.requiredCount : 1,
                placeStreet: row.placeStreet || row.place || '',
                placeCity: row.placeCity || '',
                placeState: row.placeState || '',
                placePostalCode: row.placePostalCode || '',
                placeCountry: row.placeCountry || '',
                conditions: Array.isArray(row.conditions) ? row.conditions : [],
            }));
        },

        getDayOptions: function (selected) {
            return this.days.map(value => ({
                value: value,
                selected: value === selected,
                label: this.getLanguage().translateOption(value, 'dayOfWeek', 'Global') ||
                    this.translateOption(value),
            }));
        },

        getDurationOptions: function (selectedSeconds) {
            return this.durationOptionSeconds.map(value => ({
                value: value,
                selected: value === selectedSeconds,
                label: this.formatDurationLabel(value),
            }));
        },

        resolveDurationSeconds: function (row) {
            if (row.isAllDay) {
                return 86400;
            }

            const start = this.timeToMinutes(row.timeStart);
            const end = this.timeToMinutes(row.timeEnd);

            if (start === null || end === null || end <= start) {
                return 3600;
            }

            const seconds = (end - start) * 60;

            if (this.durationOptionSeconds.includes(seconds)) {
                return seconds;
            }

            // Closest preset for the select (still display real end time).
            let best = this.durationOptionSeconds[0];
            let bestDiff = Math.abs(best - seconds);

            this.durationOptionSeconds.forEach(opt => {
                const diff = Math.abs(opt - seconds);

                if (diff < bestDiff) {
                    best = opt;
                    bestDiff = diff;
                }
            });

            return best;
        },

        formatDurationLabel: function (seconds) {
            if (!seconds) {
                return '';
            }

            if (seconds === 86400) {
                return '1d';
            }

            const mins = Math.floor(seconds / 60);
            const h = Math.floor(mins / 60);
            const m = mins % 60;

            if (h && m) {
                return h + 'h ' + m + 'm';
            }

            if (h) {
                return h + 'h';
            }

            return m + 'm';
        },

        timeToMinutes: function (value) {
            if (!value || !/^\d{1,2}:\d{2}$/.test(value)) {
                return null;
            }

            const parts = value.split(':').map(Number);

            return parts[0] * 60 + parts[1];
        },

        minutesToTime: function (mins) {
            const h = Math.floor(mins / 60);
            const m = mins % 60;

            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        },

        translateOption: function (value) {
            const map = {
                Monday: 'Monday',
                Tuesday: 'Tuesday',
                Wednesday: 'Wednesday',
                Thursday: 'Thursday',
                Friday: 'Friday',
                Saturday: 'Saturday',
                Sunday: 'Sunday',
            };

            return map[value] || value;
        },

        dayLabel: function (dayOfWeek) {
            return this.getLanguage().translateOption(dayOfWeek, 'dayOfWeek', 'Global') ||
                this.translateOption(dayOfWeek);
        },

        sharedPlaceLabel: function () {
            return this.formatAddressParts({
                placeStreet: this.model.get('placeStreet'),
                placeCity: this.model.get('placeCity'),
                placeState: this.model.get('placeState'),
                placePostalCode: this.model.get('placePostalCode'),
                placeCountry: this.model.get('placeCountry'),
            });
        },

        formatAddressParts: function (src) {
            const street = (src.placeStreet || '').trim();
            const cityLine = [
                (src.placePostalCode || '').trim(),
                (src.placeCity || '').trim(),
                (src.placeState || '').trim(),
            ].filter(Boolean).join(' ');
            const country = (src.placeCountry || '').trim();
            const lines = [street, cityLine, country].filter(Boolean);

            return lines.join('\n');
        },

        buildPreviewName: function (row) {
            const category = row.category || this.model.get('category') || '';
            const categoryLabel = category
                ? this.getLanguage().translateOption(category, 'category', 'ActivityOfferSlot')
                : '';
            const weekStart = this.model.get('weekStart');
            let datePart = '';

            if (weekStart && row.dayOfWeek) {
                const offset = this.days.indexOf(row.dayOfWeek);
                const m = this.getDateTime().toMoment(weekStart + ' 00:00:00');

                if (offset >= 0 && m.isValid()) {
                    datePart = m.clone().add(offset, 'days').format('DD.MM.YYYY');
                }
            }

            let place = '';

            if (this.model.get('uniqueAddress')) {
                place = this.model.get('placeStreet') || this.model.get('placeCity') || '';
            } else {
                place = row.placeStreet || row.placeCity || '';
            }

            return [categoryLabel, datePart, place].filter(Boolean).join(' | ');
        },
    });
});
