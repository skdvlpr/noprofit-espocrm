define('google-integration:lib/google-calendar-variable-panel', [], function () {
    'use strict';

    const stateMap = {};
    let isFocusTrackingInitialized = false;
    let lastFocusedEditable = null;
    let bodyShiftState = null;

    function initFocusTracking() {
        if (isFocusTrackingInitialized) {
            return;
        }

        isFocusTrackingInitialized = true;

        $(document).on('focusin.google-calendar-variable-panel', 'input, textarea, [contenteditable="true"]', event => {
            const el = event.target;

            if (!(el instanceof HTMLElement)) {
                return;
            }

            if (el.closest('.google-calendar-variable-panel')) {
                return;
            }

            lastFocusedEditable = el;
        });
    }

    function toAnchorNode(anchorEl) {
        if (anchorEl && anchorEl.jquery && anchorEl.length) {
            const node = anchorEl.get(0);

            if (node instanceof HTMLElement) {
                return node;
            }
        }

        if (anchorEl instanceof HTMLElement) {
            return anchorEl;
        }

        if (lastFocusedEditable instanceof HTMLElement && document.contains(lastFocusedEditable)) {
            return lastFocusedEditable;
        }

        return null;
    }

    function isScrollable(element) {
        if (!(element instanceof HTMLElement)) {
            return false;
        }

        const style = window.getComputedStyle(element);
        const overflowY = style.overflowY || '';
        const canScroll = overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay';

        return canScroll && element.scrollHeight > element.clientHeight + 1;
    }

    function resolveScrollContainer(anchorNode) {
        if (!(anchorNode instanceof HTMLElement)) {
            return window;
        }

        let parent = anchorNode.parentElement;

        while (parent && parent !== document.body) {
            if (isScrollable(parent)) {
                return parent;
            }

            parent = parent.parentElement;
        }

        return window;
    }

    function alignAnchorAbovePanel(anchorNode, panelNode) {
        if (!(anchorNode instanceof HTMLElement) || !(panelNode instanceof HTMLElement)) {
            return;
        }

        const container = resolveScrollContainer(anchorNode);
        const panelRect = panelNode.getBoundingClientRect();

        if (container === window) {
            const anchorRect = anchorNode.getBoundingClientRect();
            const targetBottom = panelRect.top - 10;
            const delta = anchorRect.bottom - targetBottom;

            if (Math.abs(delta) > 6) {
                window.scrollBy({
                    top: delta,
                    left: 0,
                    behavior: 'smooth',
                });
            }

            return;
        }

        const containerRect = container.getBoundingClientRect();
        const anchorRect = anchorNode.getBoundingClientRect();
        const targetBottom = panelRect.top - containerRect.top + container.scrollTop - 10;
        const anchorBottom = anchorRect.bottom - containerRect.top + container.scrollTop;
        const delta = anchorBottom - targetBottom;

        if (Math.abs(delta) > 6) {
            container.scrollTo({
                top: container.scrollTop + delta,
                left: 0,
                behavior: 'smooth',
            });
        }
    }

    function alignAnchorForSidePanel(anchorNode, panelNode) {
        if (!(anchorNode instanceof HTMLElement) || !(panelNode instanceof HTMLElement)) {
            return;
        }

        const container = resolveScrollContainer(anchorNode);
        const panelRect = panelNode.getBoundingClientRect();
        const anchorRect = anchorNode.getBoundingClientRect();
        const verticalPadding = 10;

        if (container === window) {
            let deltaY = 0;

            if (anchorRect.top < verticalPadding) {
                deltaY = anchorRect.top - verticalPadding;
            } else if (anchorRect.bottom > window.innerHeight - verticalPadding) {
                deltaY = anchorRect.bottom - (window.innerHeight - verticalPadding);
            }

            if (Math.abs(deltaY) > 4) {
                window.scrollBy({
                    top: deltaY,
                    left: 0,
                    behavior: 'smooth',
                });
            }

            return;
        }

        const containerRect = container.getBoundingClientRect();
        const anchorTop = anchorRect.top - containerRect.top + container.scrollTop;
        const anchorBottom = anchorRect.bottom - containerRect.top + container.scrollTop;
        const visibleTop = container.scrollTop + verticalPadding;
        const visibleBottom = container.scrollTop + container.clientHeight - verticalPadding;

        let deltaY = 0;

        if (anchorTop < visibleTop) {
            deltaY = anchorTop - visibleTop;
        } else if (anchorBottom > visibleBottom) {
            deltaY = anchorBottom - visibleBottom;
        }

        if (Math.abs(deltaY) > 4) {
            container.scrollTo({
                top: container.scrollTop + deltaY,
                left: 0,
                behavior: 'smooth',
            });
        }
    }

    function applyBodyShift(useDesktopLeftPanel, width) {
        if (!bodyShiftState) {
            bodyShiftState = {
                paddingLeft: document.body.style.paddingLeft,
                paddingRight: document.body.style.paddingRight,
                transition: document.body.style.transition,
            };
        }

        document.body.style.transition = 'padding 160ms ease';
        if (useDesktopLeftPanel) {
            document.body.style.paddingLeft = `${width}px`;
            document.body.style.paddingRight = '0px';
            return;
        }

        document.body.style.paddingLeft = bodyShiftState.paddingLeft;
        document.body.style.paddingRight = bodyShiftState.paddingRight;
    }

    function restoreBodyShift() {
        if (!bodyShiftState) {
            return;
        }

        document.body.style.paddingLeft = bodyShiftState.paddingLeft;
        document.body.style.paddingRight = bodyShiftState.paddingRight;
        document.body.style.transition = bodyShiftState.transition;
        bodyShiftState = null;
    }

    return {
        /**
         * @param {object} options
         * @param {string} options.stateKey
         * @param {JQuery} [options.anchorEl]
         * @param {Array<{name:string,label:string,group:string,groupLabel:string}>} options.fieldList
         * @param {function(string):void} options.onSelect
         * @param {function(string,string):string} options.translate
         * @param {function():void} [options.onClose]
         */
        open(options) {
            initFocusTracking();

            const stateKey = options.stateKey || 'default';
            const state = stateMap[stateKey] || {search: '', scrollTop: 0};
            const anchorNode = toAnchorNode(options.anchorEl);

            restoreBodyShift();
            $('.google-calendar-variable-panel-backdrop').remove();

            const useDesktopLeftPanel = window.innerWidth >= 768;
            const panelWidth = Math.max(300, Math.min(460, Math.round(window.innerWidth * 0.32)));
            const panelHeight = '50vh';

            const $backdrop = $('<div>')
                .addClass('google-calendar-variable-panel-backdrop')
                .css({
                    position: 'fixed',
                    inset: 0,
                    zIndex: 2000,
                    background: 'transparent',
                    display: 'flex',
                    flexDirection: useDesktopLeftPanel ? 'row' : 'column',
                    justifyContent: useDesktopLeftPanel ? 'flex-start' : 'flex-end',
                    alignItems: useDesktopLeftPanel ? 'flex-start' : 'stretch',
                    padding: '0',
                    pointerEvents: 'none',
                })
                .appendTo('body');

            const $panel = $('<div>')
                .addClass('google-calendar-variable-panel')
                .css({
                    width: useDesktopLeftPanel ? `${panelWidth}px` : '100vw',
                    maxWidth: useDesktopLeftPanel ? `${panelWidth}px` : '100vw',
                    height: useDesktopLeftPanel ? '100vh' : panelHeight,
                    maxHeight: useDesktopLeftPanel ? '100vh' : panelHeight,
                    background: 'var(--dialog-bg, var(--panel-bg, var(--body-bg, #fff)))',
                    backdropFilter: 'blur(10px)',
                    WebkitBackdropFilter: 'blur(10px)',
                    border: '1px solid var(--border-soft-color, rgba(0, 0, 0, 0.12))',
                    borderRadius: useDesktopLeftPanel ? '0' : '12px 12px 0 0',
                    boxShadow: '0 12px 48px rgba(0, 0, 0, 0.18)',
                    display: 'flex',
                    flexDirection: 'column',
                    pointerEvents: 'auto',
                })
                .appendTo($backdrop);

            // Keep edited field visible by shifting the app workspace away from side panel.
            applyBodyShift(useDesktopLeftPanel, panelWidth);

            // Keep edited field visible next to side panel.
            if (anchorNode) {
                const panelNode = $panel.get(0);
                const alignAnchor = () => {
                    if (useDesktopLeftPanel) {
                        alignAnchorForSidePanel(anchorNode, panelNode);
                        return;
                    }

                    alignAnchorAbovePanel(anchorNode, panelNode);
                };

                setTimeout(alignAnchor, 0);
                setTimeout(alignAnchor, 120);
            }

            const close = () => {
                const $list = $panel.find('[data-role="google-calendar-variable-list"]');
                state.scrollTop = $list.length ? $list.scrollTop() : 0;
                stateMap[stateKey] = state;
                $backdrop.remove();
                restoreBodyShift();
                if (typeof options.onClose === 'function') {
                    options.onClose();
                }
            };

            const closeOnContextMenu = event => {
                event.preventDefault();
                close();
            };

            $backdrop.on('contextmenu', closeOnContextMenu);
            $panel.on('contextmenu', closeOnContextMenu);

            const $header = $('<div>')
                .css({
                    padding: '14px 18px',
                    borderBottom: '1px solid var(--border-soft-color, rgba(0, 0, 0, 0.12))',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    gap: '8px',
                    flexShrink: 0,
                })
                .appendTo($panel);

            const $titleWrap = $('<div>')
                .css({display: 'flex', flexDirection: 'column', gap: '2px'})
                .appendTo($header);

            $('<strong>')
                .css({fontSize: '15px'})
                .text(options.title || options.translate('googleCalendarTemplateVariables', 'labels'))
                .appendTo($titleWrap);

            const closeHint = options.translate('googleCalendarPanelCloseHint', 'labels');

            if (closeHint && closeHint !== 'googleCalendarPanelCloseHint') {
                $('<span>')
                    .addClass('text-muted')
                    .css({fontSize: '12px'})
                    .text(closeHint)
                    .appendTo($titleWrap);
            }

            $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-default btn-sm')
                .html('&times;')
                .on('click', close)
                .appendTo($header);

            const $body = $('<div>')
                .css({
                    padding: '14px 18px 18px',
                    overflow: 'hidden',
                    display: 'flex',
                    flexDirection: 'column',
                    flex: '1 1 auto',
                    minHeight: 0,
                })
                .appendTo($panel);

            const $searchRow = $('<div>')
                .css({display: 'flex', gap: '8px', marginBottom: '12px', flexShrink: 0})
                .appendTo($body);

            const $search = $('<input>')
                .attr('type', 'search')
                .attr('placeholder', options.translate('googleCalendarTemplateVariableSearch', 'labels'))
                .addClass('form-control')
                .val(state.search || '')
                .css({flex: '1 1 auto'})
                .appendTo($searchRow);

            $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-default btn-sm')
                .text(options.translate('googleCalendarClearSearch', 'labels'))
                .on('click', () => {
                    $search.val('').trigger('input').trigger('focus');
                })
                .appendTo($searchRow);

            const $list = $('<div>')
                .attr('data-role', 'google-calendar-variable-list')
                .css({
                    overflowY: 'auto',
                    flex: '1 1 auto',
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: '10px',
                    alignContent: 'flex-start',
                    WebkitOverflowScrolling: 'touch',
                    paddingRight: '4px',
                })
                .appendTo($body);

            const renderList = () => {
                state.search = String($search.val() || '');
                stateMap[stateKey] = state;

                const query = state.search.trim().toLowerCase();
                const visibleList = options.fieldList.filter(item => {
                    const haystack = `${item.label} ${item.name} ${item.groupLabel}`.toLowerCase();
                    return !query || haystack.includes(query);
                });

                $list.empty();

                if (!visibleList.length) {
                    $('<div>')
                        .addClass('text-muted small')
                        .text(options.translate('googleCalendarNoVariables', 'labels'))
                        .appendTo($list);
                    return;
                }

                let previousGroup = null;

                visibleList.forEach(item => {
                    if (previousGroup !== item.group) {
                        previousGroup = item.group;
                        $('<div>')
                            .addClass('text-muted small')
                            .css({
                                width: '100%',
                                marginTop: previousGroup === null ? '0' : '10px',
                                marginBottom: '4px',
                                fontWeight: 600,
                            })
                            .text(item.groupLabel)
                            .appendTo($list);
                    }

                    $('<button>')
                        .attr('type', 'button')
                        .addClass('btn btn-default btn-sm')
                        .attr('title', `{{${item.name}}}`)
                        .css({
                            borderRadius: '999px',
                            maxWidth: '100%',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                        })
                        .text(item.label)
                        .on('click', () => {
                            options.onSelect(item.name);
                        })
                        .appendTo($list);
                });
            };

            $search.on('input', renderList);
            renderList();
            $list.scrollTop(state.scrollTop || 0);
            $search.trigger('focus');
        },
    };
});
