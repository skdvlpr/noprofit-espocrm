define('safehouse-crm:views/opportunity/record/kanban', ['crm:views/opportunity/record/kanban'], function (Dep) {

    return Dep.extend({
        itemViewName: 'safehouse-crm:views/record/kanban-item',
    });
});
