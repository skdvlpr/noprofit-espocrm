define('safehouse-crm:views/opportunity/record/kanban', ['safehouse-crm:views/record/kanban'], function (Dep) {

    const EXTRA_SELECT_ATTRIBUTES = [
        'assignedUserId',
        'assignedUserName',
        'teamsIds',
        'teamsNames',
        'saveToGoogleCalendar',
        'googleCalendarDateSourceList',
    ];

    return Dep.extend({

        handleAttributesOnGroupChange(model, attributes, group) {
            if (this.statusField !== 'stage') {
                return;
            }

            let probability = this.getMetadata()
                .get(['entityDefs', 'Opportunity', 'fields', 'stage', 'probabilityMap', group]);

            probability = parseInt(probability);
            attributes.probability = probability;
        },

        async getSelectAttributeList(callback) {
            const attributeList = await Dep.prototype.getSelectAttributeList.call(this, callback);

            if (!attributeList) {
                return null;
            }

            EXTRA_SELECT_ATTRIBUTES.forEach(name => {
                if (!attributeList.includes(name)) {
                    attributeList.push(name);
                }
            });

            return attributeList;
        },
    });
});
