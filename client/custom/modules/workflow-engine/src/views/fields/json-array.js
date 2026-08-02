define('workflow-engine:views/fields/json-array', ['views/fields/text'], function (Dep) {
    /**
     * Editable JSON array until W4 condition/action builders ship.
     * ORM type stays jsonArray (PHP list); UI edits pretty-printed JSON text.
     */
    return Dep.extend({

        detailTemplateContent: `
            {{#if isNotEmpty}}
            <div class="complex-text no-indent">
                <pre class="pre-scrollable" style="max-height: 24em; white-space: pre-wrap;">{{valueFormatted}}</pre>
            </div>
            {{else}}
            <span class="text-muted">{{translate 'None'}}</span>
            {{/if}}
        `,

        data: function () {
            const data = Dep.prototype.data.call(this);
            const raw = this.model.get(this.name);
            const isNotEmpty = raw !== null && raw !== undefined &&
                !(Array.isArray(raw) && raw.length === 0);

            data.isNotEmpty = isNotEmpty;
            data.valueFormatted = isNotEmpty ? this.formatJson(raw) : '';

            return data;
        },

        formatJson: function (value) {
            try {
                return JSON.stringify(value, null, 2);
            }
            catch (e) {
                return String(value);
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'edit') {
                return;
            }

            if (!this.$element || !this.$element.length) {
                return;
            }

            const raw = this.model.get(this.name);

            if (raw === null || raw === undefined) {
                this.$element.val('');

                return;
            }

            this.$element.val(this.formatJson(raw));
        },

        validate: function () {
            if (Dep.prototype.validate.call(this)) {
                return true;
            }

            if (this.mode !== 'edit' || !this.$element || !this.$element.length) {
                return false;
            }

            const text = (this.$element.val() || '').trim();

            if (text === '') {
                return false;
            }

            let parsed;

            try {
                parsed = JSON.parse(text);
            }
            catch (e) {
                const msg = this.translate('invalidJson', 'messages', 'WorkflowDefinition') ||
                    'Invalid JSON';
                this.showValidationMessage(msg);

                return true;
            }

            if (parsed !== null && !Array.isArray(parsed)) {
                const msg = this.translate('jsonMustBeArray', 'messages', 'WorkflowDefinition') ||
                    'Value must be a JSON array';
                this.showValidationMessage(msg);

                return true;
            }

            return false;
        },

        fetch: function () {
            const data = {};
            const text = (this.$element.val() || '').trim();

            if (text === '') {
                data[this.name] = null;

                return data;
            }

            try {
                const parsed = JSON.parse(text);
                data[this.name] = Array.isArray(parsed) ? parsed : null;
            }
            catch (e) {
                data[this.name] = this.model.get(this.name);
            }

            return data;
        },
    });
});
