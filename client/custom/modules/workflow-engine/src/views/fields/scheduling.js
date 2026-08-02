define('workflow-engine:views/fields/scheduling', ['views/fields/base', 'lib!cronstrue'], function (Dep, Cronstrue) {

    /**
     * Vtiger-like visual schedule planner → cron string in `scheduling`.
     * Presets: hourly / daily / weekly / monthly + manual cron.
     */
    return Dep.extend({

        detailTemplateContent: `
<div class="workflow-engine-schedule-detail">
    {{#if value}}
        <div class="workflow-engine-schedule-cron"><code>{{value}}</code></div>
        {{#if human}}<div class="small text-success">{{human}}</div>{{/if}}
    {{else}}
        <span class="text-muted">—</span>
    {{/if}}
</div>`,

        editTemplateContent: `
<div class="workflow-engine-schedule">
    <div class="workflow-engine-schedule-row">
        <label class="control-label">
            {{translate 'schedulePreset' category='fields' scope='WorkflowDefinition'}}
        </label>
        <select data-name="schedulePreset" class="form-control">
            {{#each presetOptions}}
                <option value="{{value}}" {{#if selected}}selected{{/if}}>{{label}}</option>
            {{/each}}
        </select>
    </div>
    <div class="workflow-engine-schedule-grid">
        {{#unless isCron}}
            <div class="workflow-engine-schedule-row">
                <label class="control-label">
                    {{translate 'scheduleMinute' category='fields' scope='WorkflowDefinition'}}
                </label>
                <select data-name="scheduleMinute" class="form-control">
                    {{#each minuteOptions}}
                        <option value="{{value}}" {{#if selected}}selected{{/if}}>{{label}}</option>
                    {{/each}}
                </select>
            </div>
            {{#unless isHourly}}
                <div class="workflow-engine-schedule-row">
                    <label class="control-label">
                        {{translate 'scheduleHour' category='fields' scope='WorkflowDefinition'}}
                    </label>
                    <select data-name="scheduleHour" class="form-control">
                        {{#each hourOptions}}
                            <option value="{{value}}" {{#if selected}}selected{{/if}}>{{label}}</option>
                        {{/each}}
                    </select>
                </div>
            {{/unless}}
            {{#if isWeekly}}
                <div class="workflow-engine-schedule-row workflow-engine-schedule-weekdays">
                    <label class="control-label">
                        {{translate 'scheduleWeekdays' category='fields' scope='WorkflowDefinition'}}
                    </label>
                    <div class="workflow-engine-weekday-list">
                        {{#each weekdayOptions}}
                            <label class="workflow-engine-weekday">
                                <input type="checkbox" data-weekday="{{value}}" {{#if checked}}checked{{/if}}>
                                <span>{{label}}</span>
                            </label>
                        {{/each}}
                    </div>
                </div>
            {{/if}}
            {{#if isMonthly}}
                <div class="workflow-engine-schedule-row">
                    <label class="control-label">
                        {{translate 'scheduleMonthDay' category='fields' scope='WorkflowDefinition'}}
                    </label>
                    <select data-name="scheduleMonthDay" class="form-control">
                        {{#each monthDayOptions}}
                            <option value="{{value}}" {{#if selected}}selected{{/if}}>{{label}}</option>
                        {{/each}}
                    </select>
                </div>
            {{/if}}
        {{/unless}}
        <div class="workflow-engine-schedule-row">
            <label class="control-label">
                {{#if isCron}}
                    {{translate 'scheduling' category='fields' scope='WorkflowDefinition'}}
                {{else}}
                    {{translate 'Cron' category='labels' scope='WorkflowDefinition'}}
                {{/if}}
            </label>
            <input
                type="text"
                data-name="scheduling"
                class="form-control"
                value="{{value}}"
                {{#unless isCron}}readonly{{/unless}}
                autocomplete="off"
                spellcheck="false"
            >
            {{#if human}}
                <div class="small text-success workflow-engine-schedule-human">{{human}}</div>
            {{/if}}
            <div class="text-muted small">
                {{translate 'schedulingHelp' category='messages' scope='WorkflowDefinition'}}
            </div>
        </div>
    </div>
</div>`,

        data: function () {
            const preset = this.getPreset();
            const weekdays = this.getWeekdays();
            const minute = String(this.model.get('scheduleMinute') || '0');
            const hour = String(this.model.get('scheduleHour') || '9');
            const monthDay = String(this.model.get('scheduleMonthDay') || '1');
            const value = this.model.get(this.name) || '';

            return {
                value: value,
                human: this.getHumanText(value),
                isCron: preset === 'cron',
                isHourly: preset === 'hourly',
                isWeekly: preset === 'weekly',
                isMonthly: preset === 'monthly',
                presetOptions: this.optionList('schedulePreset', [
                    'hourly', 'daily', 'weekly', 'monthly', 'cron'
                ], preset),
                minuteOptions: this.numericOptions(this.minuteList, minute),
                hourOptions: this.numericOptions(this.hourList, hour),
                monthDayOptions: this.monthDayList.map(item => ({
                    value: item,
                    label: item === 'last'
                        ? this.translate('LastDayOfMonth', 'labels', 'WorkflowDefinition')
                        : item,
                    selected: item === monthDay,
                })),
                weekdayOptions: this.weekdayDefs.map(item => ({
                    value: item.value,
                    label: this.translate(item.value, 'options', 'WorkflowDefinition') ||
                        this.getLanguage().translateOption(
                            item.value,
                            'scheduleWeekdays',
                            'WorkflowDefinition'
                        ) || item.label,
                    checked: weekdays.indexOf(item.value) !== -1,
                })),
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.minuteList = ['0', '5', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
            this.hourList = [];

            for (let i = 0; i < 24; i++) {
                this.hourList.push(String(i));
            }

            this.monthDayList = [];

            for (let i = 1; i <= 28; i++) {
                this.monthDayList.push(String(i));
            }

            this.monthDayList.push('last');

            this.weekdayDefs = [
                {value: '1', label: 'Mon'},
                {value: '2', label: 'Tue'},
                {value: '3', label: 'Wed'},
                {value: '4', label: 'Thu'},
                {value: '5', label: 'Fri'},
                {value: '6', label: 'Sat'},
                {value: '0', label: 'Sun'},
            ];

            this.Cronstrue = Cronstrue;

            if (!this.model.get('schedulePreset')) {
                this.model.set(
                    'schedulePreset',
                    this.detectPreset(this.model.get(this.name)) || 'daily',
                    {silent: true}
                );
            }

            if (!this.model.get('scheduleMinute')) {
                this.model.set('scheduleMinute', '0', {silent: true});
            }

            if (!this.model.get('scheduleHour')) {
                this.model.set('scheduleHour', '9', {silent: true});
            }

            if (!this.model.get('scheduleWeekdays')) {
                this.model.set('scheduleWeekdays', ['1', '2', '3', '4', '5'], {silent: true});
            }

            if (!this.model.get('scheduleMonthDay')) {
                this.model.set('scheduleMonthDay', '1', {silent: true});
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.isEditMode()) {
                return;
            }

            this.$el.find('[data-name="schedulePreset"]').on('change', e => {
                this.model.set('schedulePreset', e.currentTarget.value);
                this.syncFromPlanner();
                this.reRender();
            });

            this.$el.find(
                '[data-name="scheduleMinute"], [data-name="scheduleHour"], [data-name="scheduleMonthDay"]'
            ).on('change', e => {
                const name = e.currentTarget.getAttribute('data-name');
                this.model.set(name, e.currentTarget.value);
                this.syncFromPlanner();
            });

            this.$el.find('[data-weekday]').on('change', () => {
                const selected = [];

                this.$el.find('[data-weekday]:checked').each((i, el) => {
                    selected.push(el.getAttribute('data-weekday'));
                });

                this.model.set('scheduleWeekdays', selected.length ? selected : ['1']);
                this.syncFromPlanner();
            });

            this.$el.find('[data-name="scheduling"]').on('change input', e => {
                if (this.getPreset() !== 'cron') {
                    return;
                }

                this.model.set(this.name, String(e.currentTarget.value || '').trim());
                this.updateHumanOnly();
            });

            this.syncFromPlanner();
        },

        fetch: function () {
            const data = {
                schedulePreset: this.getPreset(),
                scheduleMinute: String(this.model.get('scheduleMinute') || '0'),
                scheduleHour: String(this.model.get('scheduleHour') || '9'),
                scheduleWeekdays: this.getWeekdays(),
                scheduleMonthDay: String(this.model.get('scheduleMonthDay') || '1'),
            };

            if (data.schedulePreset === 'cron') {
                const $input = this.$el.find('[data-name="scheduling"]');
                data[this.name] = $input.length
                    ? String($input.val() || '').trim()
                    : (this.model.get(this.name) || '');
            } else {
                data[this.name] = this.buildCron();
            }

            return data;
        },

        syncFromPlanner: function () {
            if (this.getPreset() === 'cron') {
                this.updateHumanOnly();

                return;
            }

            const cron = this.buildCron();
            this.model.set(this.name, cron, {silent: true});

            const $input = this.$el.find('[data-name="scheduling"]');

            if ($input.length) {
                $input.val(cron);
            }

            this.updateHumanOnly();
        },

        updateHumanOnly: function () {
            const text = this.getHumanText(this.model.get(this.name));
            const $human = this.$el.find('.workflow-engine-schedule-human');

            if ($human.length) {
                $human.text(text || '');
            }
        },

        getPreset: function () {
            return String(this.model.get('schedulePreset') || 'daily');
        },

        getWeekdays: function () {
            let weekdays = this.model.get('scheduleWeekdays');

            if (typeof weekdays === 'string' && weekdays !== '') {
                weekdays = weekdays.split(',').map(v => v.trim()).filter(Boolean);
            }

            if (!Array.isArray(weekdays) || weekdays.length === 0) {
                return ['1', '2', '3', '4', '5'];
            }

            return weekdays.map(String);
        },

        buildCron: function () {
            const preset = this.getPreset();
            const minute = String(this.model.get('scheduleMinute') || '0');
            const hour = String(this.model.get('scheduleHour') || '9');
            const monthDay = String(this.model.get('scheduleMonthDay') || '1');
            const weekdays = this.getWeekdays().join(',');

            if (preset === 'hourly') {
                return minute + ' * * * *';
            }

            if (preset === 'daily') {
                return minute + ' ' + hour + ' * * *';
            }

            if (preset === 'weekly') {
                return minute + ' ' + hour + ' * * ' + weekdays;
            }

            if (preset === 'monthly') {
                const day = monthDay === 'last' ? 'L' : monthDay;

                return minute + ' ' + hour + ' ' + day + ' * *';
            }

            return this.model.get(this.name) || '';
        },

        detectPreset: function (cron) {
            if (!cron || typeof cron !== 'string') {
                return null;
            }

            const parts = cron.trim().split(/\s+/);

            if (parts.length !== 5) {
                return 'cron';
            }

            const [minute, hour, day, month, weekday] = parts;

            if (month !== '*') {
                return 'cron';
            }

            // Match only planner-shaped crons (concrete minute; no "* * * * *").
            if (minute !== '*' && hour === '*' && day === '*' && weekday === '*') {
                return 'hourly';
            }

            if (minute !== '*' && hour !== '*' && day === '*' && weekday === '*') {
                return 'daily';
            }

            if (minute !== '*' && hour !== '*' && day === '*' && weekday !== '*') {
                return 'weekly';
            }

            if (minute !== '*' && hour !== '*' && day !== '*' && weekday === '*') {
                return 'monthly';
            }

            return 'cron';
        },

        getHumanText: function (exp) {
            if (!exp || !this.Cronstrue) {
                return '';
            }

            let locale = 'en';
            const locales = (this.Cronstrue.default && this.Cronstrue.default.locales) ||
                this.Cronstrue.locales ||
                {};
            const localeList = Object.keys(locales);
            const language = this.getLanguage().name;

            if (localeList.indexOf(language) !== -1) {
                locale = language;
            } else if (localeList.indexOf(language.split('_')[0]) !== -1) {
                locale = language.split('_')[0];
            }

            try {
                const api = this.Cronstrue.default || this.Cronstrue;
                const toString = api.toString.bind(api);

                return toString(exp, {
                    use24HourTimeFormat: !this.getDateTime().hasMeridian(),
                    locale: locale,
                });
            } catch (e) {
                return this.translate('Not valid');
            }
        },

        optionList: function (field, values, selected) {
            return values.map(value => ({
                value: value,
                label: this.getLanguage().translateOption(value, field, 'WorkflowDefinition') || value,
                selected: value === selected,
            }));
        },

        numericOptions: function (values, selected) {
            return values.map(value => ({
                value: value,
                label: value,
                selected: value === selected,
            }));
        },
    });
});
