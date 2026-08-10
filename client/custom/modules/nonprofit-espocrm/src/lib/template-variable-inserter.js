/***
 * Native Segnaposti-style field inserter for Htmlizer templates ({{field}}).
 * Shared by WorkflowEngine and GoogleIntegration (replaces exclusive GCal side panel).
 *
 * Includes computed helpers (recordUrl) and relation name-lists (participants etc.).
 */
define('nonprofit-espocrm:lib/template-variable-inserter', ['ui/select'], function (Select) {

    const EXCLUDED_FIELD_TYPES = [
        'link', 'linkMultiple', 'linkParent', 'file', 'image', 'attachmentMultiple',
        'map', 'wysiwyg', 'foreign',
    ];

    const RELATION_NAME_LIST_TYPES = [
        'belongsTo',
        'belongsToParent',
        'hasOne',
        'hasMany',
        'manyMany',
        'hasChildren',
    ];

    /**
     * @param {object} field
     * @param {string} name
     * @return {boolean}
     */
    function isInsertableField(field, name) {
        if (!field || field.utility || field.notStorable && field.type === 'map') {
            return false;
        }

        if (name.startsWith('googleCalendar')) {
            return false;
        }

        return !EXCLUDED_FIELD_TYPES.includes(field.type);
    }

    /**
     * @param {Function} translate
     * @param {string} key
     * @param {string} fallback
     * @return {string}
     */
    function trGlobal(translate, key, fallback) {
        const value = translate(key, 'labels', 'Global');

        if (!value || value === key) {
            return fallback;
        }

        return value;
    }

    /**
     * Computed / relation helpers shown at the top of the picker.
     *
     * @param {{
     *   metadata: object,
     *   language: object,
     *   entityType: string,
     *   translate: Function,
     * }} ctx
     * @return {{value: string, label: string}[]}
     */
    function buildHelperOptions(ctx) {
        const metadata = ctx.metadata;
        const language = ctx.language;
        const entityType = ctx.entityType;
        const translate = ctx.translate;
        const options = [];
        const seen = new Set();

        const pushUnique = (value, label) => {
            if (!value || seen.has(value)) {
                return;
            }

            seen.add(value);
            options.push({value: value, label: label});
        };

        pushUnique(
            'recordUrl',
            trGlobal(translate, 'templateVarRecordUrl', 'Record URL (CRM link)')
        );

        const fields = metadata.get(['entityDefs', entityType, 'fields']) || {};
        const links = metadata.get(['entityDefs', entityType, 'links']) || {};

        // Prefer CRM link fields that map to participants / related people lists.
        const preferredLinkOrder = ['users', 'contacts', 'leads', 'account', 'parent', 'assignedUser'];

        preferredLinkOrder.forEach(link => {
            const field = fields[link];
            const linkDefs = links[link];

            if (!field && !linkDefs) {
                return;
            }

            const type = (field && field.type) || null;
            const linkType = (linkDefs && linkDefs.type) || null;

            if (
                type && !['link', 'linkMultiple', 'linkParent'].includes(type) &&
                linkType && !RELATION_NAME_LIST_TYPES.includes(linkType)
            ) {
                return;
            }

            if (linkType && !RELATION_NAME_LIST_TYPES.includes(linkType) &&
                type && !['link', 'linkMultiple', 'linkParent'].includes(type)
            ) {
                return;
            }

            const linkLabel = language.translate(link, 'links', entityType)
                || language.translate(link, 'fields', entityType)
                || link;
            const namesSuffix = trGlobal(translate, 'templateVarNamesSuffix', 'names');

            pushUnique(link, linkLabel + ' (' + namesSuffix + ')');
        });

        Object.keys(links).sort((a, b) => {
            return language.translate(a, 'links', entityType)
                .localeCompare(language.translate(b, 'links', entityType));
        }).forEach(link => {
            if (seen.has(link)) {
                return;
            }

            const linkDefs = links[link] || {};

            if (!RELATION_NAME_LIST_TYPES.includes(linkDefs.type)) {
                return;
            }

            // Skip huge / internal panels that are not useful in calendar text.
            if (['teams', 'attachments', 'emails', 'tasks', 'meetings', 'calls', 'notes', 'stream'].includes(link)) {
                return;
            }

            const linkLabel = language.translate(link, 'links', entityType) || link;
            const namesSuffix = trGlobal(translate, 'templateVarNamesSuffix', 'names');

            pushUnique(link, linkLabel + ' (' + namesSuffix + ')');
        });

        return options;
    }

    /**
     * @param {{
     *   metadata: object,
     *   language: object,
     *   entityType: string,
     *   translate?: Function,
     * }} ctx
     * @return {{value: string, label: string}[]}
     */
    function buildFieldOptions(ctx) {
        const metadata = ctx.metadata;
        const language = ctx.language;
        const entityType = ctx.entityType;
        const translate = ctx.translate || ((key) => key);
        const fields = metadata.get(['entityDefs', entityType, 'fields']) || {};
        const links = metadata.get(['entityDefs', entityType, 'links']) || {};
        const options = [];

        buildHelperOptions({
            metadata: metadata,
            language: language,
            entityType: entityType,
            translate: translate,
        }).forEach(item => options.push(item));

        Object.keys(fields).sort((a, b) => {
            return language.translate(a, 'fields', entityType)
                .localeCompare(language.translate(b, 'fields', entityType));
        }).forEach(name => {
            if (!isInsertableField(fields[name], name)) {
                return;
            }

            // Avoid duplicating helpers already listed at the top.
            if (options.some(item => item.value === name)) {
                return;
            }

            options.push({
                value: name,
                label: language.translate(name, 'fields', entityType) || name,
            });
        });

        Object.keys(links).sort((a, b) => {
            return language.translate(a, 'links', entityType)
                .localeCompare(language.translate(b, 'links', entityType));
        }).forEach(link => {
            const linkDefs = links[link] || {};

            if (linkDefs.type !== 'belongsTo' && linkDefs.type !== 'belongsToParent') {
                return;
            }

            const relatedEntity = linkDefs.entity;

            if (!relatedEntity) {
                return;
            }

            const relatedFields = metadata.get(['entityDefs', relatedEntity, 'fields']) || {};
            const linkLabel = language.translate(link, 'links', entityType) || link;

            Object.keys(relatedFields).sort((a, b) => {
                return language.translate(a, 'fields', relatedEntity)
                    .localeCompare(language.translate(b, 'fields', relatedEntity));
            }).forEach(fieldName => {
                if (!isInsertableField(relatedFields[fieldName], fieldName)) {
                    return;
                }

                const fieldLabel = language.translate(fieldName, 'fields', relatedEntity) || fieldName;

                options.push({
                    value: link + '.' + fieldName,
                    label: linkLabel + ' · ' + fieldLabel,
                });
            });

            // Related record deep link when backend resolves {{link.recordUrl}}.
            options.push({
                value: link + '.recordUrl',
                label: linkLabel + ' · ' + trGlobal(translate, 'templateVarRecordUrl', 'Record URL (CRM link)'),
            });
        });

        return options;
    }

    /**
     * Insert {{token}} at caret in the first textarea/input under $inputRoot.
     *
     * @param {JQuery} $inputRoot
     * @param {string} token
     * @param {Backbone.Model|null} model
     * @param {string|null} attribute
     */
    function insertToken($inputRoot, token, model, attribute) {
        const $input = $inputRoot.find('textarea, input[type="text"]').first();

        if (!$input.length || !token) {
            return;
        }

        const element = $input.get(0);
        const value = String($input.val() || '');
        const variable = '{{' + token + '}}';
        const start = element.selectionStart ?? value.length;
        const end = element.selectionEnd ?? start;
        const nextValue = value.slice(0, start) + variable + value.slice(end);

        $input.val(nextValue).trigger('input').trigger('change');

        if (model && attribute) {
            model.set(attribute, nextValue, {ui: true});
        }

        if (typeof element.setSelectionRange === 'function') {
            const cursor = start + variable.length;
            element.setSelectionRange(cursor, cursor);
        }

        $input.trigger('focus');
    }

    /**
     * Render native Segnaposti row under a field.
     *
     * @param {{
     *   $container: JQuery,
     *   entityType: string|null,
     *   metadata: object,
     *   language: object,
     *   translate: Function,
     *   onInsert: Function,
     *   emptyHint?: string,
     * }} options
     * @return {JQuery}
     */
    function render(options) {
        const $container = options.$container;
        const entityType = options.entityType;
        const $helper = $('<div class="nonprofit-template-variable-inserter">')
            .css({marginTop: '8px'});

        if (!entityType) {
            $('<div class="small text-muted">')
                .text(options.emptyHint || options.translate('selectTargetEntityTypeFirst', 'messages', 'WorkflowDefinition'))
                .appendTo($helper);
            $container.append($helper);

            return $helper;
        }

        const fieldOptions = buildFieldOptions({
            metadata: options.metadata,
            language: options.language,
            entityType: entityType,
            translate: options.translate,
        });

        const $row = $('<div class="row">').appendTo($helper);

        const $fieldCol = $('<div class="col-sm-9 col-xs-8">').appendTo($row);
        const $btnCol = $('<div class="col-sm-3 col-xs-4">').appendTo($row);

        const $select = $('<select class="form-control" data-name="templateVariableField">')
            .appendTo($fieldCol);

        $select.append($('<option value="">').text('—'));

        fieldOptions.forEach(item => {
            $select.append(
                $('<option>').attr('value', item.value).text(item.label)
            );
        });

        const $button = $('<button type="button" class="btn btn-default btn-block" data-action="insertTemplateVariable">')
            .text(options.translate('Insert', 'labels'))
            .appendTo($btnCol);

        if (Select && typeof Select.init === 'function') {
            Select.init($select);
        }

        $button.on('click', () => {
            const value = $select.val();

            if (!value) {
                return;
            }

            options.onInsert(value);
        });

        $('<div class="small text-muted" style="margin-top: 4px;">')
            .text(options.translate('insertFieldHelp', 'messages', 'Global') !== 'insertFieldHelp'
                ? options.translate('insertFieldHelp', 'messages', 'Global')
                : options.translate('Insert', 'labels') + ': {{field}}')
            .appendTo($helper);

        $container.append($helper);

        return $helper;
    }

    return {
        buildFieldOptions: buildFieldOptions,
        buildHelperOptions: buildHelperOptions,
        insertToken: insertToken,
        render: render,
    };
});
