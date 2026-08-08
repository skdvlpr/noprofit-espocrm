/***
 * Map field: street-only addresses count as displayable, and auto-height
 * never collapses to 0 inside Aurora side/drawer modals (View on Map).
 */
define('nonprofit-espocrm:views/fields/map', [
    'views/fields/map',
], function (Dep) {

    const FALLBACK_HEIGHT_PX = Math.max(320, Math.round(window.innerHeight * 0.6));

    const resolveRendererClass = (mod) => {
        if (!mod) {
            return null;
        }

        if (typeof mod === 'function') {
            return mod;
        }

        if (typeof mod.default === 'function') {
            return mod.default;
        }

        return null;
    };

    return Dep.extend({

        hasAddress() {
            if (Dep.prototype.hasAddress.call(this)) {
                return true;
            }

            return !!this.model.get(this.addressField + 'Street');
        },

        processSetHeight(init) {
            let height = this.height;

            if (this.height === 'auto') {
                const parentHeight = this.$el.parent().height();
                height = parentHeight;

                if (init && height <= 0) {
                    setTimeout(() => this.processSetHeight(true), 50);

                    return;
                }

                // Drawer / modal bodies often report 0 even after the retry.
                if (!height || height < 120) {
                    height = FALLBACK_HEIGHT_PX;
                }
            }

            if (this.$map && this.$map.length) {
                this.$map.css({
                    height: height + 'px',
                    minHeight: Math.min(height, FALLBACK_HEIGHT_PX) + 'px',
                });
            }
        },

        renderMap() {
            this.processSetHeight(true);

            if (this.height === 'auto') {
                $(window).off('resize.' + this.cid);
                $(window).on('resize.' + this.cid, this.processSetHeight.bind(this));
            }

            const rendererId = this.getMetadata()
                .get(['app', 'mapProviders', this.provider, 'renderer']);

            if (rendererId) {
                Espo.loader.require(rendererId, (mod) => {
                    const Renderer = resolveRendererClass(mod);

                    if (!Renderer) {
                        console.error('[map] Invalid renderer module:', rendererId, mod);

                        return;
                    }

                    new Renderer(this).render(this.addressData);
                });

                return;
            }

            const methodName = 'afterRender' + this.provider.replace(/\s+/g, '');

            if (typeof this[methodName] === 'function') {
                this[methodName]();
            }
        },
    });
});
