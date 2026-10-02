(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.ScheduleTime = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const hours = [];
    const minutes = [];

    for (let hour = 0; hour <= 23; hour += 1) {
        hours.push(String(hour).padStart(2, '0'));
    }

    for (let minute = 0; minute <= 55; minute += 5) {
        minutes.push(String(minute).padStart(2, '0'));
    }

    function parse(value) {
        const match = /^(\d{2}):(\d{2})/.exec(String(value || '').trim());

        if (!match) {
            return null;
        }

        const hour = Number(match[1]);
        const minute = Number(match[2]);

        if (hour > 23 || minute > 59) {
            return null;
        }

        return {
            hour: match[1],
            minute: match[2],
        };
    }

    function compose(hour, minute) {
        const parsed = parse(String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0'));

        return parsed ? parsed.hour + ':' + parsed.minute : '';
    }

    function presentation(value) {
        const parsed = parse(value);

        if (!parsed) {
            return { hour: '', minute: '', custom: false, value: '' };
        }

        return {
            hour: parsed.hour,
            minute: parsed.minute,
            custom: minutes.indexOf(parsed.minute) === -1,
            value: parsed.hour + ':' + parsed.minute,
        };
    }

    function ensureChoice(select, value, listed) {
        Array.from(select.querySelectorAll('option[data-extra]')).forEach(function (option) {
            if (option.value !== value) {
                option.remove();
            }
        });

        if (!value || listed.indexOf(value) !== -1) {
            if (value) {
                select.value = value;
            }
            return;
        }

        if (!Array.from(select.options).some(function (option) { return option.value === value; })) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            option.dataset.extra = '1';
            select.appendChild(option);
        }

        select.value = value;
    }

    function markChip(field, minute) {
        field.querySelectorAll('[data-minute]').forEach(function (button) {
            button.classList.toggle('is-selected', button.dataset.minute === minute);
        });
    }

    function write(field) {
        const input = field.querySelector('input[type="hidden"]');
        const hour = field.querySelector('[data-part="hour"]').value;
        const minuteSelect = field.querySelector('[data-part="minute"]');
        const custom = field.querySelector('[data-part="custom"]');
        const minute = minuteSelect.value === 'other' ? custom.value : minuteSelect.value;
        const next = compose(hour, minute);

        if (!input || !next || input.value === next) {
            return;
        }

        input.value = next;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function sync(input) {
        if (!input) {
            return;
        }

        const field = input.closest('[data-schedule-time]');
        const view = presentation(input.value);

        if (!field || !view.value) {
            return;
        }

        const minute = field.querySelector('[data-part="minute"]');
        const customWrap = field.querySelector('[data-part="custom-wrap"]');
        const custom = field.querySelector('[data-part="custom"]');

        ensureChoice(field.querySelector('[data-part="hour"]'), view.hour, hours);
        customWrap.hidden = !view.custom;
        custom.value = view.custom ? view.minute : '';

        if (view.custom) {
            minute.value = 'other';
            markChip(field, '');
            return;
        }

        minute.value = view.minute;
        markChip(field, view.minute);
    }

    function bind(scope) {
        if (!scope) {
            return;
        }

        scope.querySelectorAll('[data-schedule-time]').forEach(function (field) {
            if (field.dataset.bound === '1') {
                return;
            }

            field.dataset.bound = '1';
            const minute = field.querySelector('[data-part="minute"]');
            const customWrap = field.querySelector('[data-part="custom-wrap"]');
            const custom = field.querySelector('[data-part="custom"]');

            field.querySelector('[data-part="hour"]').addEventListener('change', function () {
                write(field);
            });
            minute.addEventListener('change', function () {
                customWrap.hidden = minute.value !== 'other';
                if (minute.value !== 'other') {
                    custom.value = '';
                    write(field);
                    markChip(field, minute.value);
                } else {
                    markChip(field, '');
                    custom.focus();
                }
            });
            custom.addEventListener('input', function () {
                custom.value = custom.value.replace(/\D/g, '').slice(0, 2);
                if (/^\d{2}$/.test(custom.value) && Number(custom.value) <= 59) {
                    write(field);
                }
            });
            field.querySelectorAll('[data-minute]').forEach(function (button) {
                button.addEventListener('click', function () {
                    minute.value = button.dataset.minute;
                    customWrap.hidden = true;
                    custom.value = '';
                    write(field);
                    markChip(field, button.dataset.minute);
                });
            });
        });
    }

    return {
        hours: hours,
        minutes: minutes,
        parse: parse,
        compose: compose,
        presentation: presentation,
        sync: sync,
        bind: bind,
    };
}));
