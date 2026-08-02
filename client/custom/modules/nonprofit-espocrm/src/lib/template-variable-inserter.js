/***
 * Native Segnaposti-style field inserter for Htmlizer templates ({{field}}).
 * Shared by WorkflowEngine and GoogleIntegration (replaces exclusive GCal side panel).
 */
define('nonprofit-espocrm:lib/template-variable-inserter', ['ui/select'], function (Select) {

    const EXCLUDED_FIELD_TYPES = [
        'link', 'linkMultiple', 'linkParent', 'file', 'image', 'attachmentMultiple',
        'map', 'wysiwyg', 'foreign',
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
     * @param {{
     *   metadata: object,
     *   language: object,
     *   entityType: string,
     * }} ctx
     * @return {{value: string, label: string}[]}
     */
    function buildFieldOptions(ctx) {
        const metadata = ctx.metadata;
        const language = ctx.language;
        const entityType = ctx.entityType;
        const fields = metadata.get(['entityDefs', entityType, 'fields']) || {};
        const links = metadata.get(['entityDefs', entityType, 'links']) || {};
        const options = [];

        Object.keys(fields).sort((a, b) => {
            return language.translate(a, 'fields', entityType)
                .localeCompare(language.translate(b, 'fields', entityType));
        }).forEach(name => {
            if (!isInsertableField(fields[name], name)) {
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
        insertToken: insertToken,
        render: render,
    };
});
