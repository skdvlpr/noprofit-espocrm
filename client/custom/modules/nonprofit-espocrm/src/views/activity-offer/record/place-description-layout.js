define('nonprofit-espocrm:views/activity-offer/record/place-description-layout', [], function () {

    /**
     * Layout helper for ActivityOffer Overview:
     * - uniqueAddress off → description full width (100%)
     * - uniqueAddress on  → description 50% + place 50%, matching heights
     */
    return {

        setupPlaceDescriptionLayout: function () {
            this.listenTo(this.model, 'change:uniqueAddress', () => {
                this.syncPlaceDescriptionLayout();
            });
        },

        afterRenderPlaceDescriptionLayout: function () {
            this.syncPlaceDescriptionLayout();

            // Address sub-fields finish layout a tick later (map / Places).
            window.setTimeout(() => this.syncPlaceDescriptionLayout(), 50);
            window.setTimeout(() => this.syncPlaceDescriptionLayout(), 250);
        },

        syncPlaceDescriptionLayout: function () {
            if (!this.$el || !this.$el.length) {
                return;
            }

            const unique = !!this.model.get('uniqueAddress');
            const $place = this.$el.find('.cell[data-name="place"]');
            const $desc = this.$el.find('.cell[data-name="description"]');

            if (!$desc.length) {
                return;
            }

            const half = ['col-sm-6', 'col-md-6'];
            const full = ['col-sm-12', 'col-md-12'];

            const setCols = ($el, mode) => {
                $el.removeClass(half.concat(full).join(' '));

                if (mode === 'full') {
                    $el.addClass(full.join(' '));
                }
                else {
                    $el.addClass(half.join(' '));
                }
            };

            if (unique) {
                if ($place.length) {
                    setCols($place, 'half');
                }

                setCols($desc, 'half');
                this.matchDescriptionToPlaceHeight();
            }
            else {
                setCols($desc, 'full');
                this.resetDescriptionHeight();
            }
        },

        matchDescriptionToPlaceHeight: function () {
            const $placeField = this.$el.find('.cell[data-name="place"] .field');
            const $textarea = this.$el.find('.cell[data-name="description"] textarea');

            if (!$placeField.length || !$placeField.is(':visible')) {
                return;
            }

            const placeH = Math.ceil($placeField.outerHeight() || 0);

            if (placeH < 40) {
                return;
            }

            if ($textarea.length) {
                // Fill the field column to the address block height.
                $textarea.css({
                    height: placeH + 'px',
                    minHeight: placeH + 'px',
                    resize: 'vertical',
                    boxSizing: 'border-box',
                });

                return;
            }

            // Detail (read) mode: stretch the description field box.
            const $descField = this.$el.find('.cell[data-name="description"] .field');

            if ($descField.length) {
                $descField.css({
                    minHeight: placeH + 'px',
                });
            }
        },

        resetDescriptionHeight: function () {
            this.$el.find('.cell[data-name="description"] textarea')
                .css({height: '', minHeight: '', resize: ''});

            this.$el.find('.cell[data-name="description"] .field')
                .css({minHeight: ''});
        },
    };
});
