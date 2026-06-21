/**
 * Home dashlets live inside grid-stack (transformed ancestors). Portal the menu to
 * document.body while open so it is not clipped and uses real viewport coords.
 */
(function () {
    'use strict';

    const PORTAL_Z = 2140;
    const EVENT_NS = '.safehouseDashletMenu';

    const boot = () => {
        const $ = window.jQuery;

        if (!$) {
            return;
        }

        const placeMenu = ($menu, $toggle) => {
            const rect = $toggle[0].getBoundingClientRect();
            const menuWidth = $menu.outerWidth();
            const menuHeight = $menu.outerHeight();
            let top = rect.bottom;
            let left = rect.right - menuWidth;

            if (left < 4) {
                left = rect.left;
            }

            if (top + menuHeight > window.innerHeight - 4) {
                top = Math.max(4, rect.top - menuHeight);
            }

            $menu.css({
                position: 'fixed',
                top,
                left,
                right: 'auto',
                bottom: 'auto',
                zIndex: PORTAL_Z,
                display: 'block',
            });
        };

        const restoreMenu = ($group, $menu) => {
            const $placeholder = $group.children('.safehouse-dashlet-menu-placeholder');

            if ($placeholder.length) {
                $placeholder.before($menu);
                $placeholder.remove();
            } else {
                $group.append($menu);
            }

            $menu.removeClass('safehouse-dashlet-dropdown-portal');
            $menu.removeData('safehouse-portal');
            $menu.css({
                position: '',
                top: '',
                left: '',
                right: '',
                bottom: '',
                zIndex: '',
                display: '',
            });
        };

        $(document).on('shown.bs.dropdown', '.panel.dashlet .panel-heading .btn-group', function () {
            const $group = $(this);
            const $menu = $group.children('.dropdown-menu').first();
            const $toggle = $group.find('.dropdown-toggle').first();

            if (!$menu.length || !$toggle.length || $menu.data('safehouse-portal')) {
                return;
            }

            $menu.data('safehouse-portal', true);
            $menu.addClass('safehouse-dashlet-dropdown-portal');
            $group.append('<span class="safehouse-dashlet-menu-placeholder" style="display:none;"></span>');
            $('body').append($menu);

            const reposition = () => placeMenu($menu, $toggle);

            reposition();
            $(window).on('resize' + EVENT_NS + ' scroll' + EVENT_NS, reposition);

            $group.one('hidden.bs.dropdown', () => {
                $(window).off(EVENT_NS);
                restoreMenu($group, $menu);
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
