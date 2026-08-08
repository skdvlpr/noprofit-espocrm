define('nonprofit-espocrm:views/fields/enum-status-badge', ['views/fields/enum'], function (Dep) {

    /**
     * Enum label with data-status for semantic badge colors.
     *
     * Aurora maps label-primary (and sometimes label-default) to brand red, so
     * Published looked identical to Cancelled. We strip Espo style classes and
     * color exclusively via data-status CSS (see activity-offer.css).
     */
    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:' + this.name, () => {
                if (this.isRendered()) {
                    this.applySemanticBadge();
                }
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.applySemanticBadge();
        },

        applySemanticBadge: function () {
            const value = this.model.get(this.name);

            if (!value || !this.$el || !this.$el.length) {
                return;
            }

            let $label = this.$el.find('.label-state');

            if (!$label.length) {
                if (this.$el.hasClass('label-state') || this.$el.is('.label')) {
                    $label = this.$el;
                } else {
                    $label = this.$el.find('.label').first();
                }
            }

            if (!$label.length) {
                return;
            }

            $label.attr('data-status', value);
            $label.addClass('label-state label-status-semantic');
            $label.removeClass(
                'label-primary label-success label-danger label-warning ' +
                'label-info label-default text-primary text-success ' +
                'text-danger text-warning text-info text-default'
            );
        },
    });
});
