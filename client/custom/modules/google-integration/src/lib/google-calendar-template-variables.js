define('google-integration:lib/google-calendar-template-variables', [], function () {
    'use strict';

    const EXCLUDED_FIELD_TYPES = ['link', 'linkMultiple', 'linkParent', 'file', 'image'];

    /**
     * @param {object|null|undefined} field
     * @param {string} name
     * @return {boolean}
     */
    function isInsertableField(field, name) {
        if (!field || field.utility || name.startsWith('googleCalendar')) {
            return false;
        }

        return !EXCLUDED_FIELD_TYPES.includes(field.type);
    }

    /**
     * @param {import('metadata').default} metadata
     * @param {string} entityType
     * @param {string} fieldName
     * @param {(key: string, category: string, scope: string) => string} translate
     * @return {string}
     */
    function getFieldLabel(metadata, entityType, fieldName, translate) {
        const translated = translate(fieldName, 'fields', entityType);

        return translated && translated !== fieldName ? translated : '';
    }

    /**
     * @param {(key: string, category: string, scope: string) => string} translate
     * @param {string} entityType
     * @param {string} linkLabel
     * @return {string}
     */
    function formatExternalGroupLabel(translate, entityType, linkLabel) {
        let template = translate('googleCalendarExternalRecordFields', 'labels', entityType);

        if (!template || template === 'googleCalendarExternalRecordFields') {
            template = translate('googleCalendarExternalRecordFields', 'labels', 'Global');
        }

        if (!template || template === 'googleCalendarExternalRecordFields') {
            template = 'External fields ({relation})';
        }

        return template.replace('{relation}', linkLabel);
    }

    /**
     * @param {import('metadata').default} metadata
     * @param {string} entityType
     * @param {(key: string, category: string, scope: string) => string} translate
     * @param {(linkName: string, linkType: string) => boolean} [hasRelatedLink]
     * @return {Array<{name: string, label: string, group: string, groupLabel: string}>}
     */
    function getRelatedFieldList(metadata, entityType, translate, hasRelatedLink) {
        const fields = metadata.get(`entityDefs.${entityType}.fields`) || {};
        const links = metadata.get(`entityDefs.${entityType}.links`) || {};
        const list = [];
        const includeLink = typeof hasRelatedLink === 'function'
            ? hasRelatedLink
            : () => true;

        Object.keys(fields).forEach(linkName => {
            const field = fields[linkName] || {};

            if (!['link', 'linkMultiple'].includes(field.type) || !includeLink(linkName, field.type)) {
                return;
            }

            const relatedEntityType = links[linkName]?.entity;

            if (!relatedEntityType) {
                return;
            }

            const relatedFields = metadata.get(`entityDefs.${relatedEntityType}.fields`) || {};
            const linkLabel = translate(linkName, 'fields', entityType);

            if (!linkLabel || linkLabel === linkName) {
                return;
            }

            const groupLabel = formatExternalGroupLabel(translate, entityType, linkLabel);

            Object.keys(relatedFields)
                .filter(name => isInsertableField(relatedFields[name], name))
                .map(name => {
                    const label = getFieldLabel(metadata, relatedEntityType, name, translate);

                    if (!label) {
                        return null;
                    }

                    return {
                        name: `${linkName}.${name}`,
                        label: `(${linkLabel}) ${label}`,
                        group: `related-${linkName}`,
                        groupLabel,
                    };
                })
                .filter(Boolean)
                .sort((a, b) => a.label.localeCompare(b.label))
                .forEach(item => list.push(item));
        });

        return list;
    }

    /**
     * @param {object} options
     * @param {import('metadata').default} options.metadata
     * @param {string} options.entityType
     * @param {(key: string, category: string, scope: string) => string} options.translate
     * @param {string} [options.currentGroupLabel]
     * @param {(linkName: string, linkType: string) => boolean} [options.hasRelatedLink]
     * @return {Array<{name: string, label: string, group: string, groupLabel: string}>}
     */
    function buildInsertableFieldList(options) {
        const {
            metadata,
            entityType,
            translate,
            currentGroupLabel = translate('googleCalendarCurrentRecordFields', 'labels', entityType),
            hasRelatedLink,
        } = options;

        const fields = metadata.get(`entityDefs.${entityType}.fields`) || {};

        const currentFields = Object.keys(fields)
            .filter(name => isInsertableField(fields[name], name))
            .map(name => {
                const label = getFieldLabel(metadata, entityType, name, translate);

                if (!label) {
                    return null;
                }

                return {
                    name,
                    label,
                    group: 'current',
                    groupLabel: currentGroupLabel,
                };
            })
            .filter(Boolean)
            .sort((a, b) => a.label.localeCompare(b.label));

        return [
            ...currentFields,
            ...getRelatedFieldList(metadata, entityType, translate, hasRelatedLink),
        ];
    }

    return {
        isInsertableField,
        buildInsertableFieldList,
    };
});
