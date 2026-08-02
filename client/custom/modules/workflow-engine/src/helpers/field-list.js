define('workflow-engine:helpers/field-list', [], function () {

    /**
     * Builds target/source field option lists for workflow value assignment.
     * Own fields first (alpha), then related "(Link) Field" (alpha by link then field).
     */
    return class FieldListHelper {

        constructor(view) {
            this.view = view;
        }

        getMetadata() {
            return this.view.getMetadata();
        }

        getLanguage() {
            return this.view.getLanguage();
        }

        getFieldManager() {
            return this.view.getFieldManager();
        }

        /**
         * @return {{list: string[], labels: Object.<string, string>}}
         */
        getTargetFieldOptions(entityType) {
            return this.buildFieldOptions(entityType, true);
        }

        /**
         * @return {{list: string[], labels: Object.<string, string>}}
         */
        getSourceFieldOptions(entityType) {
            return this.buildFieldOptions(entityType, false);
        }

        buildFieldOptions(entityType, writableOnly) {
            const list = [];
            const labels = {};

            if (!entityType) {
                return {list, labels};
            }

            const own = [];
            const fields = this.getMetadata().get(['entityDefs', entityType, 'fields']) || {};

            Object.keys(fields).sort((a, b) => a.localeCompare(b)).forEach(field => {
                const defs = fields[field] || {};

                if (this.isSkippedField(defs, writableOnly)) {
                    return;
                }

                this.collectAttributes(entityType, field, defs, writableOnly).forEach(item => {
                    own.push(item);
                });
            });

            own.sort((a, b) => a.label.localeCompare(b.label));
            own.forEach(item => {
                list.push(item.name);
                labels[item.name] = item.label;
            });

            const links = this.getMetadata().get(['entityDefs', entityType, 'links']) || {};
            const related = [];

            Object.keys(links).sort((a, b) => a.localeCompare(b)).forEach(link => {
                const linkDefs = links[link] || {};

                if (!['belongsTo', 'belongsToParent'].includes(linkDefs.type)) {
                    return;
                }

                const relatedType = linkDefs.entity;

                if (!relatedType) {
                    return;
                }

                const linkLabel = this.getLanguage().translate(link, 'links', entityType) ||
                    this.getLanguage().translate(link, 'fields', entityType) ||
                    link;

                const relatedFields = this.getMetadata().get(['entityDefs', relatedType, 'fields']) || {};

                Object.keys(relatedFields).sort((a, b) => a.localeCompare(b)).forEach(field => {
                    const defs = relatedFields[field] || {};

                    if (this.isSkippedField(defs, writableOnly)) {
                        return;
                    }

                    // Related targets: only simple attributes (name, status, …), not nested links.
                    if (['link', 'linkParent', 'linkMultiple', 'attachmentMultiple', 'file', 'image',
                        'jsonArray', 'jsonObject', 'map', 'wysiwyg'].includes(defs.type)) {
                        return;
                    }

                    if (writableOnly && (defs.readOnly || defs.notStorable)) {
                        return;
                    }

                    const fieldLabel = this.getLanguage().translate(field, 'fields', relatedType) || field;
                    const name = link + '.' + field;

                    related.push({
                        name: name,
                        label: '(' + linkLabel + ') ' + fieldLabel,
                        sortKey: linkLabel.toLowerCase() + '|' + fieldLabel.toLowerCase(),
                    });
                });
            });

            related.sort((a, b) => a.sortKey.localeCompare(b.sortKey));
            related.forEach(item => {
                list.push(item.name);
                labels[item.name] = item.label;
            });

            return {list, labels};
        }

        collectAttributes(entityType, field, defs, writableOnly) {
            const type = defs.type || 'varchar';
            const items = [];
            const fieldLabel = this.getLanguage().translate(field, 'fields', entityType) || field;

            if (['linkMultiple', 'attachmentMultiple', 'file', 'image', 'jsonArray', 'jsonObject',
                'map', 'foreign', 'wysiwyg'].includes(type)) {
                return items;
            }

            if (writableOnly && (defs.readOnly || defs.notStorable)) {
                return items;
            }

            if (type === 'link' || type === 'linkParent') {
                items.push({
                    name: field + 'Id',
                    label: fieldLabel + ' (ID)',
                });

                return items;
            }

            if (type === 'personName') {
                ['salutation' + this.capitalize(field), 'first' + this.capitalize(field),
                    'last' + this.capitalize(field), field].forEach(attr => {
                    // personName stores firstName/lastName — use FieldManager when available
                });

                try {
                    const attrs = this.getFieldManager().getEntityTypeFieldAttributeList(entityType, field) || [];

                    attrs.forEach(attr => {
                        items.push({
                            name: attr,
                            label: this.getLanguage().translate(attr, 'fields', entityType) || attr,
                        });
                    });
                } catch (e) {
                    items.push({name: field, label: fieldLabel});
                }

                return items;
            }

            if (type === 'phone' || type === 'email') {
                items.push({name: field, label: fieldLabel});

                return items;
            }

            items.push({name: field, label: fieldLabel});

            if (type === 'currency') {
                items.push({
                    name: field + 'Currency',
                    label: fieldLabel + ' (Currency)',
                });
            }

            return items;
        }

        isSkippedField(defs, writableOnly) {
            if (!defs || !defs.type) {
                return true;
            }

            if (writableOnly && (defs.readOnly || defs.notStorable)) {
                return true;
            }

            return false;
        }

        capitalize(value) {
            if (!value) {
                return '';
            }

            return value.charAt(0).toUpperCase() + value.slice(1);
        }

        getFieldType(entityType, attribute) {
            if (!entityType || !attribute) {
                return 'varchar';
            }

            if (attribute.includes('.')) {
                const [link, field] = attribute.split('.', 2);
                const linkDefs = this.getMetadata().get(['entityDefs', entityType, 'links', link]) || {};
                const relatedType = linkDefs.entity;

                if (!relatedType) {
                    return 'varchar';
                }

                return this.getMetadata().get(['entityDefs', relatedType, 'fields', field, 'type']) || 'varchar';
            }

            const fields = this.getMetadata().get(['entityDefs', entityType, 'fields']) || {};

            if (fields[attribute]) {
                return fields[attribute].type || 'varchar';
            }

            // link Id attribute
            if (attribute.endsWith('Id')) {
                const link = attribute.slice(0, -2);

                if (fields[link] && (fields[link].type === 'link' || fields[link].type === 'linkParent')) {
                    return 'varchar';
                }
            }

            return 'varchar';
        }

        getEnumOptions(entityType, attribute) {
            if (!entityType || !attribute) {
                return [];
            }

            let scope = entityType;
            let field = attribute;

            if (attribute.includes('.')) {
                const [link, relatedField] = attribute.split('.', 2);
                const linkDefs = this.getMetadata().get(['entityDefs', entityType, 'links', link]) || {};

                scope = linkDefs.entity || entityType;
                field = relatedField;
            }

            return this.getMetadata().get(['entityDefs', scope, 'fields', field, 'options']) || [];
        }
    };
});
