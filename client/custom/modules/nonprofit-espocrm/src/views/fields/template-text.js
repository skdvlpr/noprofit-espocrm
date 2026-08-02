/***
 * Text/varchar field with native Segnaposti-style {{field}} inserter.
 * options.templateEntityType — entity scope for field list.
 */
define('nonprofit-espocrm:views/fields/template-text', [
    'views/fields/text',
    'nonprofit-espocrm:lib/template-variable-inserter',
], function (Dep, Inserter) {

    return Dep.extend({

        setup: function () {
            if (this.model.getFieldType(this.name) === 'varchar') {
                this.editTemplate = 'fields/varchar/edit';
                this.detailTemplate = 'fields/varchar/detail';
                this.listTemplate = 'fields/varchar/list';
            }

            Dep.prototype.setup.call(this);

            this.templateEntityType = this.options.templateEntityType
                || (this.options.params && this.options.params.templateEntityType)
                || null;

            this.listenTo(this.model, 'change:targetEntityType', () => {
                if (this.isEditMode() && this.isRendered()) {
                    this.renderInserter();
                }
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.isEditMode()) {
                return;
            }

            this.renderInserter();
        },

        getTemplateEntityType: function () {
            return this.templateEntityType
                || this.options.templateEntityType
                || this.model.get('templateEntityType')
                || this.model.get('targetEntityType')
                || null;
        },

        setTemplateEntityType: function (entityType) {
            this.templateEntityType = entityType || null;

            if (this.isEditMode() && this.isRendered()) {
                this.renderInserter();
            }
        },

        renderInserter: function () {
            this.$el.find('.nonprofit-template-variable-inserter').remove();

            Inserter.render({
                $container: this.$el,
                entityType: this.getTemplateEntityType(),
                metadata: this.getMetadata(),
                language: this.getLanguage(),
                translate: (key, category, scope) => this.translate(key, category, scope),
                emptyHint: this.translate('selectTargetEntityTypeFirst', 'messages', 'WorkflowDefinition'),
                onInsert: token => {
                    Inserter.insertToken(this.$el, token, this.model, this.name);
                    this.trigger('change');
                },
            });
        },
    });
});
