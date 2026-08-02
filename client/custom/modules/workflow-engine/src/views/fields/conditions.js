define('workflow-engine:views/fields/conditions', ['views/fields/base'], function (Dep) {

    /**
     * Vtiger-style entry criteria: All (AND) + Any (OR).
     * Opens Espo-native dynamic-logic modal (same as Field Manager / royalacademy).
     * Persists conditionGroup as jsonArray for ConditionEvaluator.
     */
    return Dep.extend({

        editTemplateContent: `
<div class="workflow-engine-conditions">
    <div class="workflow-engine-conditions-section">
        <div class="clearfix">
            <div class="pull-left">
                <strong>{{translate 'AllConditions' category='labels' scope='WorkflowDefinition'}}</strong>
            </div>
            <div class="pull-right">
                {{#if hasTargetEntityType}}
                <a
                    role="button"
                    tabindex="0"
                    data-action="editAllConditions"
                >{{translate 'Edit'}}</a>
                {{/if}}
            </div>
        </div>
        {{#if hasTargetEntityType}}
        <div class="all-group-string-container" style="margin-top: 6px;"></div>
        {{else}}
        <div class="small text-muted" style="margin-top: 6px;">
            {{translate 'selectTargetEntityTypeFirst' category='messages' scope='WorkflowDefinition'}}
        </div>
        {{/if}}
    </div>
    <div class="workflow-engine-conditions-section" style="margin-top: 12px;">
        <div class="clearfix">
            <div class="pull-left">
                <strong>{{translate 'AnyConditions' category='labels' scope='WorkflowDefinition'}}</strong>
            </div>
            <div class="pull-right">
                {{#if hasTargetEntityType}}
                <a
                    role="button"
                    tabindex="0"
                    data-action="editAnyConditions"
                >{{translate 'Edit'}}</a>
                {{/if}}
            </div>
        </div>
        {{#if hasTargetEntityType}}
        <div class="any-group-string-container" style="margin-top: 6px;"></div>
        {{else}}
        <div class="small text-muted" style="margin-top: 6px;">
            {{translate 'selectTargetEntityTypeFirst' category='messages' scope='WorkflowDefinition'}}
        </div>
        {{/if}}
    </div>
</div>
`,

        detailTemplateContent: `
<div class="workflow-engine-conditions">
    <div class="workflow-engine-conditions-section">
        <div>
            <strong>{{translate 'AllConditions' category='labels' scope='WorkflowDefinition'}}</strong>
        </div>
        {{#if hasTargetEntityType}}
        <div class="all-group-string-container" style="margin-top: 6px;"></div>
        {{else}}
        <div class="small text-muted" style="margin-top: 6px;">—</div>
        {{/if}}
    </div>
    <div class="workflow-engine-conditions-section" style="margin-top: 12px;">
        <div>
            <strong>{{translate 'AnyConditions' category='labels' scope='WorkflowDefinition'}}</strong>
        </div>
        {{#if hasTargetEntityType}}
        <div class="any-group-string-container" style="margin-top: 6px;"></div>
        {{else}}
        <div class="small text-muted" style="margin-top: 6px;">—</div>
        {{/if}}
    </div>
</div>
`,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.addActionHandler('editAllConditions', () => this.editConditions('all'));
            this.addActionHandler('editAnyConditions', () => this.editConditions('any'));

            this.scope = this.getTargetEntityType();
            this.allConditionGroup = [];
            this.anyConditionGroup = [];
            this.loadConditionGroups();

            this.listenTo(this.model, 'change:targetEntityType', async () => {
                const nextScope = this.getTargetEntityType();

                if (nextScope === this.scope) {
                    return;
                }

                this.scope = nextScope;
                this.allConditionGroup = [];
                this.anyConditionGroup = [];
                this.syncModel();

                if (this.isRendered()) {
                    await this.reRender();
                }
            });
        },

        data: function () {
            return {
                ...Dep.prototype.data.call(this),
                hasTargetEntityType: !!this.scope,
            };
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.scope) {
                return;
            }

            this.renderConditionStringViews();
        },

        fetch: function () {
            const data = {};
            const conditionGroup = this.buildConditionGroup();

            data[this.name] = conditionGroup.length ? conditionGroup : null;

            return data;
        },

        /**
         * Same path as royalacademy: views/admin/dynamic-logic/modals/edit
         * (native AND + dropdown → Field / AND / OR / NOT / $User).
         */
        editConditions: async function (kind) {
            if (!this.scope) {
                Espo.Ui.warning(
                    this.translate('selectTargetEntityTypeFirst', 'messages', 'WorkflowDefinition')
                );

                return;
            }

            const conditionGroup = kind === 'any' ? this.anyConditionGroup : this.allConditionGroup;

            await this.createView('modal', 'views/admin/dynamic-logic/modals/edit', {
                conditionGroup: Espo.Utils.cloneDeep(conditionGroup || []),
                scope: this.scope,
            }, view => {
                // Espo detail views use the same pattern for panel dropdowns:
                // mousedown on menu items must not bubble or Bootstrap/dialog
                // swallow the subsequent click (AND + → Field/AND/OR).
                this.listenToOnce(view, 'after:render', () => {
                    view.$el.on(
                        'mousedown.workflow-engine-dl',
                        '.dynamic-logic-expression-container .dropdown-menu',
                        e => e.stopPropagation()
                    );
                });

                this.listenToOnce(view, 'apply', async updatedConditionGroup => {
                    const next = Array.isArray(updatedConditionGroup) ? updatedConditionGroup : [];

                    if (kind === 'any') {
                        this.anyConditionGroup = next;
                    }
                    else {
                        this.allConditionGroup = next;
                    }

                    this.syncModel();
                    this.trigger('change');

                    if (this.isRendered()) {
                        await this.reRender();
                    }
                });

                this.listenToOnce(view, 'close remove', () => {
                    view.$el.off('.workflow-engine-dl');
                });

                view.render();
            });
        },

        syncModel: function () {
            const conditionGroup = this.buildConditionGroup();

            this.model.set(this.name, conditionGroup.length ? conditionGroup : null, {silent: true});
        },

        renderConditionStringViews: function () {
            this.renderConditionGroupView(
                'allConditionGroupView',
                '.all-group-string-container',
                this.allConditionGroup,
                'and'
            );
            this.renderConditionGroupView(
                'anyConditionGroupView',
                '.any-group-string-container',
                this.anyConditionGroup,
                'or'
            );
        },

        renderConditionGroupView: function (viewName, selector, conditionGroup, operator) {
            if (this.hasView(viewName)) {
                this.clearView(viewName);
            }

            this.createView(viewName, 'views/admin/dynamic-logic/conditions-string/group-base', {
                selector: selector,
                itemData: {
                    value: conditionGroup || [],
                },
                operator: operator,
                scope: this.scope,
            }, view => {
                if (this.isRendered()) {
                    view.render();
                }
            });
        },

        getTargetEntityType: function () {
            const value = this.model.get('targetEntityType');

            return value ? String(value) : null;
        },

        loadConditionGroups: function () {
            const normalized = this.normalizeStoredConditions(this.model.get(this.name));

            this.allConditionGroup = normalized.allConditionGroup;
            this.anyConditionGroup = normalized.anyConditionGroup;
        },

        normalizeStoredConditions: function (value) {
            if (!value) {
                return {allConditionGroup: [], anyConditionGroup: []};
            }

            if (!Array.isArray(value)) {
                return {allConditionGroup: [], anyConditionGroup: []};
            }

            if (value.length === 0) {
                return {allConditionGroup: [], anyConditionGroup: []};
            }

            // Canonical Vtiger-shaped storage: [ {type:'and', value:[…]}, {type:'or', value:[…]} ]
            if (
                value.length === 2 &&
                value[0] && value[0].type === 'and' && Array.isArray(value[0].value) &&
                value[1] && value[1].type === 'or' && Array.isArray(value[1].value)
            ) {
                return {
                    allConditionGroup: Espo.Utils.cloneDeep(value[0].value),
                    anyConditionGroup: Espo.Utils.cloneDeep(value[1].value),
                };
            }

            if (value.length === 1 && value[0] && value[0].type === 'or' && Array.isArray(value[0].value)) {
                return {
                    allConditionGroup: [],
                    anyConditionGroup: Espo.Utils.cloneDeep(value[0].value),
                };
            }

            if (value.length === 1 && value[0] && value[0].type === 'and' && Array.isArray(value[0].value)) {
                return {
                    allConditionGroup: Espo.Utils.cloneDeep(value[0].value),
                    anyConditionGroup: [],
                };
            }

            // Legacy flat leaf list → All
            return {
                allConditionGroup: Espo.Utils.cloneDeep(value),
                anyConditionGroup: [],
            };
        },

        buildConditionGroup: function () {
            const group = [];

            if (this.allConditionGroup.length) {
                group.push({
                    type: 'and',
                    value: Espo.Utils.cloneDeep(this.allConditionGroup),
                });
            }

            if (this.anyConditionGroup.length) {
                group.push({
                    type: 'or',
                    value: Espo.Utils.cloneDeep(this.anyConditionGroup),
                });
            }

            return group;
        },
    });
});
