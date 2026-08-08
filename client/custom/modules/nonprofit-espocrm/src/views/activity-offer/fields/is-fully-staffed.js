define('nonprofit-espocrm:views/activity-offer/fields/is-fully-staffed', ['views/fields/bool'], function (Dep) {

    return Dep.extend({

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'edit' && this.mode !== 'detail') {
                return;
            }

            const help = this.translate('isFullyStaffedHelp', 'messages', 'ActivityOffer');

            if (!help || help === 'isFullyStaffedHelp') {
                return;
            }

            const $el = this.$el;

            if (!$el || !$el.length) {
                return;
            }

            $el.find('.is-fully-staffed-help').remove();
            $el.append(
                $('<p>')
                    .addClass('text-muted small is-fully-staffed-help')
                    .css({
                        'margin-top': '0.35rem',
                        'margin-bottom': 0,
                        'max-width': '36rem',
                        'white-space': 'normal',
                    })
                    .text(help)
            );
        },
    });
});
