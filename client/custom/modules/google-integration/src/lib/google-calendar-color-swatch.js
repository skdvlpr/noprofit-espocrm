define('google-integration:lib/google-calendar-color-swatch', [], function () {
    'use strict';

    const COLOR_MAP = {
        '': '#9aa0a6',
        '1': '#7986cb',
        '2': '#33b679',
        '3': '#8e24aa',
        '4': '#e67c73',
        '5': '#f6c026',
        '6': '#f4511e',
        '7': '#039be5',
        '8': '#616161',
        '9': '#3f51b5',
        '10': '#0b8043',
        '11': '#d50000',
    };

    function optionClassName(value) {
        return 'google-calendar-color-opt-' + (value === '' ? 'default' : value);
    }

    function markSelectOptions($select) {
        $select.find('option').each((i, element) => {
            const value = String(element.getAttribute('value') ?? '');

            element.classList.add(optionClassName(value));
        });
    }

    function createSwatch(value) {
        return $('<span>')
            .addClass('google-calendar-color-swatch')
            .css({
                display: 'inline-block',
                width: '12px',
                height: '12px',
                borderRadius: '50%',
                backgroundColor: COLOR_MAP[value] || COLOR_MAP[''],
                border: '1px solid rgba(255, 255, 255, 0.55)',
                boxShadow: '0 0 0 1px rgba(0, 0, 0, 0.18)',
            });
    }

    function decorateSelectize($root, getValue) {
        const select = $root.find('select').get(0) || ($root.is('select') ? $root.get(0) : null);
        const selectize = select ? select.selectize : null;

        if (!selectize) {
            return;
        }

        const decorate = () => {
            const value = String(getValue() || '');
            const $input = $root.find('.selectize-input').first();

            if ($input.length) {
                $input.find('.google-calendar-color-swatch').remove();
                createSwatch(value)
                    .css({marginRight: '6px', verticalAlign: 'middle'})
                    .prependTo($input);
            }

            $root.find('.selectize-dropdown-content [data-selectable], .selectize-dropdown-content .option').each((i, element) => {
                const $option = $(element);
                const optionValue = String($option.attr('data-value') ?? '');

                $option.addClass(optionClassName(optionValue));
                $option.find('.google-calendar-color-swatch').remove();
                createSwatch(optionValue)
                    .css({marginRight: '8px', verticalAlign: 'middle'})
                    .prependTo($option);
            });
        };

        decorate();

        if (!selectize.googleCalendarColorDecorated) {
            selectize.googleCalendarColorDecorated = true;
            selectize.on('dropdown_open', decorate);
        }
    }

    return {
        COLOR_MAP,
        optionClassName,
        markSelectOptions,
        createSwatch,
        decorateSelectize,
    };
});
