(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.ScheduleWorkspace = api;

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', api.mount);
    }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const pad = value => String(value).padStart(2, '0');

    function scheduleMoveText(props, proposal, impactMessage) {
        const date = value => String(value || '').split('-').reverse().join('/');
        return date(props.occurrence_date) + ' · ' + props.start_time + '–' + props.end_time
            + ' → ' + date(proposal.date) + ' · ' + proposal.start + '–' + proposal.end + '. ' + impactMessage;
    }

    function refreshedSelection(selected, events) {
        if (!selected) { return null; }
        return events.find(event => String(event.id) === String(selected.id)) || null;
    }

    function dateOnly(value) {
        if (typeof value === 'string') {
            return value.slice(0, 10);
        }

        return [value.getFullYear(), pad(value.getMonth() + 1), pad(value.getDate())].join('-');
    }

    function timeOnly(value) {
        return [pad(value.getHours()), pad(value.getMinutes())].join(':');
    }

    function addDays(value, days) {
        const date = new Date(value.getTime());
        date.setDate(date.getDate() + days);
        return date;
    }

    function isoWeekday(value) {
        const day = value.getDay();
        return day === 0 ? 7 : day;
    }

    function selectedWeekDates(reference, weekdays) {
        const base = new Date(reference + 'T12:00:00');
        const monday = addDays(base, 1 - isoWeekday(base));

        return weekdays
            .map(Number)
            .sort((left, right) => left - right)
            .map(day => dateOnly(addDays(monday, day - 1)));
    }

    function isDoctorScheduleProps(props) {
        if (!props) {
            return false;
        }

        return props.doctor_name != null && props.doctor_name !== ''
            && props.start_time != null && props.start_time !== ''
            && props.end_time != null && props.end_time !== ''
            && props.appointment_duration != null && props.appointment_duration !== '';
    }

    function scheduleEventLines(props) {
        if (!isDoctorScheduleProps(props)) {
            return null;
        }

        const initial = props.doctor_initial == null ? '' : props.doctor_initial;

        return {
            time: initial + ' · ' + props.start_time + '–' + props.end_time,
            doctor: props.doctor_name,
            cadence: props.appointment_duration + ' min por cita',
        };
    }

    function scheduleEventText(props, viewType) {
        if (!isDoctorScheduleProps(props)) {
            return '';
        }

        const time = props.start_time + '–' + props.end_time;
        const doctor = props.doctor_name;

        if (viewType === 'dayGridMonth') {
            return time + ' ' + doctor;
        }

        return time + ' ' + doctor + ' · ' + props.appointment_duration + ' min';
    }

    function clickSelection(date) {
        const start = new Date(date.getTime());
        const end = new Date(start.getTime());
        end.setMinutes(end.getMinutes() + 60);

        return {
            start: start,
            end: end,
            writes: false,
        };
    }

    // FullCalendar's end is exclusive. A range that merely touches the next
    // midnight still belongs to the day where the gesture started.
    function clampScheduleSelection(start, end) {
        const originStart = new Date(start.getTime());
        const rawEnd = new Date(end.getTime());
        const lastInstant = new Date(rawEnd.getTime() - 1);
        const spansDays = dateOnly(originStart) !== dateOnly(lastInstant);

        if (!spansDays) {
            return {
                start: originStart,
                end: rawEnd,
                spansDays: false,
                opensModal: rawEnd > originStart,
                writes: false,
                message: '',
            };
        }

        const clampedEnd = new Date(originStart.getTime());
        clampedEnd.setHours(rawEnd.getHours(), rawEnd.getMinutes(), rawEnd.getSeconds(), rawEnd.getMilliseconds());

        if (clampedEnd < originStart) {
            return {
                start: originStart,
                end: originStart,
                spansDays: true,
                opensModal: false,
                writes: false,
                message: 'El horario debe quedar dentro del mismo día. No se crean turnos que crucen la medianoche.',
            };
        }

        if (clampedEnd.getTime() === originStart.getTime()) {
            const click = clickSelection(originStart);

            return {
                start: click.start,
                end: click.end,
                spansDays: true,
                opensModal: true,
                writes: false,
                message: '',
            };
        }

        return {
            start: originStart,
            end: clampedEnd,
            spansDays: true,
            opensModal: true,
            writes: false,
            message: '',
        };
    }

    function needsExplicitConfirmation(action) {
        return ['mass-create', 'drag', 'resize', 'update', 'delete'].includes(action);
    }

    function weekdayField(scope, weekdays) {
        return scope === 'single' ? {} : { weekdays: weekdays };
    }

    const DOCTOR_REQUIRED_MESSAGE = 'Selecciona un médico.';

    function explicitChoice(value) {
        if (value == null) {
            return '';
        }

        const text = String(value).trim();

        if (!text || text.toLowerCase() === 'todos' || text.toLowerCase() === 'todas') {
            return '';
        }

        return text;
    }

    // Day view is one time column, not one column per doctor. A column id is
    // used only when the caller already knows it; otherwise the filter stands.
    function resolveScheduleDoctor(filterDoctorId, columnDoctorId) {
        const fromFilter = explicitChoice(filterDoctorId);

        if (fromFilter) {
            return fromFilter;
        }

        return explicitChoice(columnDoctorId);
    }

    function clockText(value) {
        if (value == null || value === '') {
            return '';
        }

        if (typeof value === 'string') {
            return value.slice(0, 5);
        }

        return timeOnly(value);
    }

    function buildScheduleCreateContext(source) {
        const keepTimes = source.keepTimes === true;
        const timed = keepTimes || (source.timed !== false && source.view !== 'dayGridMonth');
        const anchor = source.date || source.start;
        let start = source.start || null;
        let end = source.end || null;

        if (!timed) {
            start = null;
            end = null;
        }

        return {
            view: source.view || '',
            date: dateOnly(anchor),
            start: start ? clockText(start) : '',
            end: end ? clockText(end) : '',
            duration: source.duration == null || source.duration === '' ? null : Number(source.duration),
            notice: source.notice || '',
            doctorId: resolveScheduleDoctor(source.doctorId, source.columnDoctorId),
            siteId: explicitChoice(source.siteId),
            writes: false,
            opensModal: true,
            kind: 'create',
            dates: Array.isArray(source.dates) ? source.dates.slice() : null,
        };
    }

    function toolbarScheduleContext(view, anchorDate, filters) {
        const filtersSafe = filters || {};

        if (view === 'dayGridMonth') {
            return buildScheduleCreateContext({
                view: view,
                date: anchorDate,
                timed: false,
                doctorId: filtersSafe.doctorId,
                siteId: filtersSafe.siteId,
            });
        }

        const start = new Date(anchorDate.getTime());
        start.setHours(9, 0, 0, 0);
        const end = new Date(start.getTime());
        end.setHours(13, 0, 0, 0);

        return buildScheduleCreateContext({
            view: view,
            date: start,
            start: start,
            end: end,
            timed: true,
            doctorId: filtersSafe.doctorId,
            siteId: filtersSafe.siteId,
        });
    }

    function shiftNavigationDate(anchorDate, view, direction) {
        const date = new Date(anchorDate.getTime());
        const amount = direction === 'prev' ? -1 : 1;

        if (direction === 'today') {
            return date;
        }

        if (view === 'dayGridMonth') {
            date.setMonth(date.getMonth() + amount);
            return date;
        }

        date.setDate(date.getDate() + ((view === 'timeGridWeek' ? 7 : 1) * amount));
        return date;
    }

    function preservedFilters(filters) {
        return {
            siteId: filters.siteId,
            specialtyId: filters.specialtyId,
            doctorId: filters.doctorId,
        };
    }

    function scheduleInteractionKind(interaction) {
        return interaction === 'eventClick' ? 'edit' : 'create';
    }

    const DOCTOR_COLORS = [
        '#176B87', '#2D7A5E', '#765AA5', '#9B5E32', '#4B6FAE',
        '#8A4F68', '#2B7D7B', '#6C6F35', '#5A6B7A', '#7A5B3A',
    ];

    function initialScheduleView() {
        return 'dayGridMonth';
    }

    function doctorColor(doctorId) {
        const index = (Number(doctorId) - 1) % DOCTOR_COLORS.length;
        return DOCTOR_COLORS[(index + DOCTOR_COLORS.length) % DOCTOR_COLORS.length];
    }

    function doctorInitials(name) {
        const withoutTitle = String(name || '').trim().replace(/^dr(?:a)?\.?\s+/iu, '');
        return withoutTitle.split(/\s+/).filter(Boolean).slice(0, 2)
            .map(word => word.charAt(0).toLocaleUpperCase('es'))
            .join('');
    }

    function visibleScheduleEvents(events, hiddenDoctorIds) {
        const hidden = new Set((hiddenDoctorIds || []).map(id => String(id)));
        return (events || []).filter(event => {
            const props = event && event.extendedProps;
            return !(props && hidden.has(String(props.doctor_id)));
        });
    }

    function doctorComparisonState(doctors, filterDoctorId, hiddenDoctorIds) {
        const selected = explicitChoice(filterDoctorId);
        const source = selected
            ? (doctors || []).filter(doctor => String(doctor.id) === selected)
            : (doctors || []).slice();
        const hidden = new Set((hiddenDoctorIds || []).map(id => String(id)));

        return {
            mode: selected ? 'single' : 'compare',
            doctors: source.map(doctor => ({
                id: String(doctor.id),
                name: doctor.name,
                initial: doctor.initial || doctorInitials(doctor.name),
                color: doctor.color || doctorColor(doctor.id),
                visible: selected ? true : !hidden.has(String(doctor.id)),
            })),
        };
    }

    function doctorComparisonSummary(state) {
        if (!state || state.mode !== 'compare') {
            return { shown: false, title: '', text: '' };
        }

        const doctors = state.doctors || [];
        const shown = doctors.filter(doctor => doctor.visible).length;

        return {
            shown: true,
            title: 'Comparar médicos',
            text: 'Mostrando ' + shown + ' de ' + doctors.length,
        };
    }

    const PAINTED_DATE_REQUIRED_MESSAGE = 'Selecciona al menos una fecha.';

    function reduceMonthPaint(state, action) {
        const current = state || { painting: false, dates: [] };
        const dates = (current.dates || []).slice();
        const idle = {
            painting: Boolean(current.painting),
            dates: dates,
            opensModal: false,
            writes: false,
            preventMenu: false,
            changed: false,
        };

        if (action.type === 'down') {
            if (action.onEvent || !action.inMonth || !action.date) {
                return { painting: false, dates: dates, opensModal: false, writes: false, preventMenu: false, changed: false };
            }

            if (!dates.includes(action.date)) {
                dates.push(action.date);
            }

            return { painting: true, dates: dates, opensModal: false, writes: false, preventMenu: false, changed: true };
        }

        if (action.type === 'enter') {
            if (!current.painting || action.onEvent || !action.inMonth || !action.date || dates.includes(action.date)) {
                return idle;
            }

            dates.push(action.date);
            return { painting: true, dates: dates, opensModal: false, writes: false, preventMenu: false, changed: true };
        }

        if (action.type === 'up') {
            return {
                painting: false,
                dates: dates,
                opensModal: false,
                writes: false,
                preventMenu: false,
                changed: false,
            };
        }

        if (action.type === 'remove') {
            const next = dates.filter(date => date !== action.date);
            return {
                painting: false,
                dates: next,
                opensModal: false,
                writes: false,
                preventMenu: false,
                changed: next.length !== dates.length,
            };
        }

        if (action.type === 'remove-weekday') {
            const weekday = Number(action.weekday);
            const next = dates.filter(date => weekdayFromIsoDate(date) !== weekday);
            return {
                painting: false,
                dates: next,
                opensModal: false,
                writes: false,
                preventMenu: false,
                changed: next.length !== dates.length,
            };
        }

        if (action.type === 'clear') {
            return { painting: false, dates: [], opensModal: false, writes: false, preventMenu: false, changed: dates.length > 0 };
        }

        if (action.type === 'contextmenu') {
            if (!action.date || !dates.includes(action.date)) {
                return idle;
            }

            return {
                painting: false,
                dates: dates.filter(date => date !== action.date),
                opensModal: false,
                writes: false,
                preventMenu: true,
                changed: true,
            };
        }

        return idle;
    }

    function paintedDateLabel(isoDate) {
        const parts = String(isoDate).split('-');
        return parts[2] + '/' + parts[1];
    }

    function paintedDateChips(dates) {
        return (dates || []).map(date => ({ date: date, label: paintedDateLabel(date) }));
    }

    const WEEKDAY_NAMES = {
        1: { short: 'LUN', plural: 'lunes' },
        2: { short: 'MAR', plural: 'martes' },
        3: { short: 'MIE', plural: 'miércoles' },
        4: { short: 'JUE', plural: 'jueves' },
        5: { short: 'VIE', plural: 'viernes' },
        6: { short: 'SAB', plural: 'sábados' },
        7: { short: 'DOM', plural: 'domingos' },
    };

    function weekdayFromIsoDate(isoDate) {
        const date = new Date(String(isoDate) + 'T12:00:00');
        return Number.isNaN(date.getTime()) ? null : isoWeekday(date);
    }

    function paintedDateGroups(dates) {
        const groups = new Map();

        paintedDateChips(dates).forEach(chip => {
            const weekday = weekdayFromIsoDate(chip.date);
            const meta = WEEKDAY_NAMES[weekday];
            if (!meta) return;

            if (!groups.has(weekday)) {
                groups.set(weekday, {
                    weekday: weekday,
                    label: meta.short,
                    plural: meta.plural,
                    dates: [],
                });
            }

            groups.get(weekday).dates.push(chip);
        });

        return Array.from(groups.values())
            .sort((left, right) => left.weekday - right.weekday)
            .map(group => {
                group.dates.sort((left, right) => left.date.localeCompare(right.date));
                return group;
            });
    }

    function paintedDateCountLabel(dates) {
        const count = (dates || []).length;
        return count + (count === 1 ? ' fecha seleccionada' : ' fechas seleccionadas');
    }

    function paintedSaveDecision(dates) {
        const list = dates || [];

        if (!list.length) {
            return { ok: false, persist: false, writes: false, message: PAINTED_DATE_REQUIRED_MESSAGE };
        }

        if (list.length === 1) {
            return { ok: true, persist: true, writes: false, scope: 'single', fecha_cita: list[0] };
        }

        return {
            ok: true,
            persist: true,
            writes: false,
            scope: 'dates',
            dates: list.slice(),
        };
    }

    function concreteDatesPayload(fields) {
        const site = fields.siteId;

        return {
            doctor_id: Number(fields.doctorId),
            site_id: site === '' || site == null ? null : Number(site),
            scope: 'dates',
            dates: (fields.dates || []).slice(),
            hora_inicio: fields.start,
            hora_fin: fields.end,
            duracion_cita: Number(fields.duration),
        };
    }

    function paintedConfirmationText(doctorName, dates, start, end, duration) {
        const list = dates || [];
        const blocks = list.length === 1
            ? 'Se creará 1 bloque.'
            : 'Se crearán ' + list.length + ' bloques.';

        return [doctorName, '', start + '–' + end, duration + ' min por cita', '', 'Fechas:']
            .concat(list.map(paintedDateLabel))
            .concat(['', blocks])
            .join('\n');
    }

    const PRESET_DRAG_MIME = 'application/x-schedule-preset';
    const PRESET_EMPTY_MESSAGE = 'Aún no hay horarios frecuentes para este médico en el período visible.';
    const PRESET_NEEDS_DATE_MESSAGE = 'Selecciona una fecha del calendario o arrastra este horario a un día.';

    function presetFrequencyLabel(count) {
        return 'usado ' + count + (Number(count) === 1 ? ' vez' : ' veces');
    }

    function frequentSchedulePresets(events, doctorId) {
        const selected = explicitChoice(doctorId);

        if (!selected) {
            return { visible: false, presets: [], message: '' };
        }

        const groups = new Map();
        (events || []).forEach(event => {
            const props = event && event.extendedProps ? event.extendedProps : null;
            if (!props || String(props.doctor_id) !== selected) {
                return;
            }
            if (props.estado && String(props.estado).toUpperCase() !== 'ACTIVO') {
                return;
            }

            const start = String(props.start_time || '').slice(0, 5);
            const end = String(props.end_time || '').slice(0, 5);
            const duration = Number(props.appointment_duration);
            if (!start || !end || !duration) {
                return;
            }

            const key = start + '|' + end + '|' + duration;
            const current = groups.get(key);
            if (current) {
                current.count += 1;
                return;
            }

            groups.set(key, {
                doctor_id: selected,
                doctor_name: props.doctor_name || '',
                hora_inicio: start,
                hora_fin: end,
                duracion_cita: duration,
                count: 1,
            });
        });

        const presets = Array.from(groups.values()).sort((left, right) => {
            if (right.count !== left.count) {
                return right.count - left.count;
            }
            if (left.hora_inicio !== right.hora_inicio) {
                return left.hora_inicio < right.hora_inicio ? -1 : 1;
            }
            if (left.hora_fin !== right.hora_fin) {
                return left.hora_fin < right.hora_fin ? -1 : 1;
            }
            return left.duracion_cita - right.duracion_cita;
        }).slice(0, 3);

        return {
            visible: true,
            presets: presets,
            message: presets.length ? '' : PRESET_EMPTY_MESSAGE,
        };
    }

    function monthPresetTarget(cell) {
        const date = cell && cell.date ? cell.date : '';
        const inMonth = Boolean(cell && cell.inMonth && date);

        return { date: inMonth ? date : '', valid: inMonth };
    }

    function presetDropDecision(input) {
        const selected = (input.selectedDates || []).slice();
        const target = input.target || { valid: false, date: '' };
        const preset = input.preset;

        if (!preset || !preset.hora_inicio || !preset.hora_fin || !preset.duracion_cita || !target.valid) {
            return { open: false, writes: false, dates: selected };
        }

        const times = {
            start: String(preset.hora_inicio).slice(0, 5),
            end: String(preset.hora_fin).slice(0, 5),
            duration: Number(preset.duracion_cita),
        };

        if (selected.length) {
            const count = selected.length;
            return Object.assign({
                open: true,
                writes: false,
                dates: selected,
                date: selected[0],
                notice: 'Aplicar horario a ' + count + (count === 1 ? ' fecha seleccionada.' : ' fechas seleccionadas.'),
            }, times);
        }

        return Object.assign({
            open: true,
            writes: false,
            dates: [target.date],
            date: target.date,
            notice: '',
        }, times);
    }

    function monthSelectionOnRangeChange(dates, previousRange, nextRange) {
        const same = previousRange
            && nextRange
            && previousRange.start === nextRange.start
            && previousRange.end === nextRange.end;

        if (!previousRange || same) {
            return { dates: (dates || []).slice(), cleared: false, writes: false };
        }

        return { dates: [], cleared: true, writes: false };
    }

    function monthSelectionAfterDismiss(dates) {
        return { dates: (dates || []).slice(), cleared: false, writes: false };
    }

    function configureSelectionContext(dates, filters) {
        const list = (dates || []).slice();
        const safe = filters || {};

        return buildScheduleCreateContext({
            view: 'dayGridMonth',
            date: list[0],
            timed: false,
            dates: list,
            doctorId: safe.doctorId,
            siteId: safe.siteId,
        });
    }

    function presetClickDecision(preset, selectedDates) {
        const selected = (selectedDates || []).slice();

        if (!selected.length) {
            return {
                open: false,
                writes: false,
                dates: [],
                message: PRESET_NEEDS_DATE_MESSAGE,
            };
        }

        return presetDropDecision({
            preset: preset,
            target: { valid: true, date: selected[0] },
            selectedDates: selected,
        });
    }

    function visibilityAfterAction(doctorIds, hiddenDoctorIds, action, doctorId) {
        if (action === 'all') {
            return [];
        }

        if (action === 'none') {
            return (doctorIds || []).map(id => String(id));
        }

        const hidden = new Set((hiddenDoctorIds || []).map(id => String(id)));
        const id = String(doctorId);
        if (hidden.has(id)) {
            hidden.delete(id);
        } else {
            hidden.add(id);
        }
        return Array.from(hidden);
    }

    function mount() {
        const host = document.getElementById('schedule-workspace');

        if (!host || typeof FullCalendar === 'undefined') {
            return;
        }

        const canManage = host.dataset.canManage === 'true';
        const calendarElement = document.getElementById('schedule-calendar');
        const siteFilter = document.getElementById('schedule-filter-site');
        const specialtyFilter = document.getElementById('schedule-filter-specialty');
        const doctorFilter = document.getElementById('schedule-filter-doctor');
        const legend = document.getElementById('schedule-doctor-legend');
        const detail = document.getElementById('schedule-detail');
        const loadState = document.getElementById('schedule-load-state');
        const rangeLabel = document.getElementById('schedule-range-label');
        const modalElement = document.getElementById('schedule-editor');
        const form = document.getElementById('schedule-editor-form');
        const modal = window.bootstrap && window.bootstrap.Modal
            ? window.bootstrap.Modal.getOrCreateInstance(modalElement)
            : null;
        let selectedEvent = null;
        let visibleRange = null;
        let overlapTimer = null;
        let currentView = initialScheduleView();
        let hiddenDoctorIds = [];
        let eventCache = null;
        let eventCacheKey = '';
        let useVisibilityCache = false;
        let paintedDates = [];
        let painting = false;
        let presetDragged = false;
        let activePaintedDates = null;
        let weekGesture = null;

        const calendar = new FullCalendar.Calendar(calendarElement, {
            initialView: initialScheduleView(),
            initialDate: host.dataset.today,
            locale: 'es',
            firstDay: 1,
            headerToolbar: false,
            height: '100%',
            expandRows: true,
            allDaySlot: false,
            nowIndicator: true,
            selectable: canManage,
            // The selection mirror is rendered through eventContent. It is not a
            // DoctorSchedule, so it was painted as "undefined · undefined".
            selectMirror: false,
            selectAllow: function (info) {
                if (currentView === 'dayGridMonth') {
                    return false;
                }

                if (currentView !== 'timeGridWeek') {
                    return true;
                }

                const outcome = clampScheduleSelection(info.start, info.end);

                if (!outcome.spansDays) {
                    weekGesture = null;
                    return true;
                }

                weekGesture = outcome;
                return false;
            },
            editable: canManage,
            eventStartEditable: canManage,
            eventDurationEditable: canManage,
            slotMinTime: '07:00:00',
            slotMaxTime: '21:00:00',
            slotDuration: '00:20:00',
            snapDuration: '00:10:00',
            slotLabelInterval: '01:00:00',
            dayMaxEvents: 3,
            eventOverlap: true,
            events: loadEvents,
            eventsSet: events => {
                const fresh = refreshedSelection(selectedEvent, events);
                if (fresh) { selectedEvent = fresh; paintDetail(fresh); }
                else { selectedEvent = null; detail.innerHTML = '<h2>Detalle</h2><p class="schedule-detail__empty">Seleccione un bloque para revisar su configuración.</p>'; }
            },
            eventDidMount: info => {
                if (selectedEvent && String(info.event.id) === String(selectedEvent.id)) { info.el.classList.add('is-selected-schedule'); }
            },
            datesSet: onDatesSet,
            dateClick: onDateClick,
            select: onSelect,
            eventClick: onEventClick,
            eventDrop: info => confirmMove(info, 'drag'),
            eventResize: info => confirmMove(info, 'resize'),
            eventContent: renderEvent,
            loading: isLoading => {
                loadState.hidden = !isLoading;
                loadState.textContent = isLoading ? 'Actualizando horarios…' : '';
            },
        });

        calendar.render();
        wireToolbar();
        wireFilters();
        wireEditor();

        function loadEvents(info, success, failure) {
            const url = new URL(host.dataset.feed, window.location.origin);
            url.searchParams.set('start', dateOnly(info.start));
            url.searchParams.set('end', dateOnly(addDays(info.end, -1)));
            appendFilter(url, 'site_id', siteFilter.value);
            appendFilter(url, 'specialty_id', specialtyFilter.value);
            appendFilter(url, 'doctor_id', doctorFilter.value);
            const key = url.toString();

            if (useVisibilityCache && eventCache && eventCacheKey === key) {
                useVisibilityCache = false;
                deliverEvents(eventCache, success);
                return;
            }

            useVisibilityCache = false;

            fetch(url, { headers: { Accept: 'application/json' } })
                .then(response => response.ok ? response.json() : Promise.reject(response))
                .then(events => {
                    eventCache = events;
                    eventCacheKey = key;
                    deliverEvents(events, success);
                })
                .catch(error => {
                    loadState.hidden = false;
                    loadState.textContent = 'No fue posible cargar los horarios.';
                    failure(error);
                });
        }

        function deliverEvents(events, success) {
            renderLegend();
            renderFrequentPresets(events);
            const hidden = explicitChoice(doctorFilter.value) ? [] : hiddenDoctorIds;
            success(visibleScheduleEvents(events, hidden));
        }

        function legendDoctors() {
            return Array.from(doctorFilter.options)
                .filter(option => option.value && !option.hidden)
                .map(option => ({ id: option.value, name: option.textContent.trim() }));
        }

        function applyVisibility(action, doctorId) {
            hiddenDoctorIds = visibilityAfterAction(
                legendDoctors().map(doctor => doctor.id),
                hiddenDoctorIds,
                action,
                doctorId
            );
            useVisibilityCache = true;
            calendar.refetchEvents();
        }

        function onDatesSet(info) {
            currentView = info.view.type;
            const nextRange = {
                start: dateOnly(info.start),
                end: dateOnly(addDays(info.end, -1)),
            };
            const shift = monthSelectionOnRangeChange(paintedDates, visibleRange, nextRange);
            visibleRange = nextRange;
            rangeLabel.textContent = info.view.title;
            document.querySelectorAll('[data-calendar-view]').forEach(button => {
                button.classList.toggle('is-active', button.dataset.calendarView === info.view.type);
            });
            if (shift.cleared) {
                clearPaintedSelection();
                return;
            }
            renderPaintHighlights();
        }

        function onDateClick(info) {
            if (!canManage || info.view.type === 'dayGridMonth') {
                return;
            }

            const click = clickSelection(info.date);
            openCreateScheduleModal(buildScheduleCreateContext({
                view: info.view.type,
                date: click.start,
                start: click.start,
                end: click.end,
                timed: true,
                doctorId: doctorFilter.value,
                siteId: siteFilter.value,
            }));
        }

        function onSelect(info) {
            if (!canManage || info.view.type === 'dayGridMonth') {
                calendar.unselect();
                return;
            }

            const outcome = clampScheduleSelection(info.start, info.end);
            weekGesture = null;
            presentSelection(outcome);
        }

        function presentSelection(outcome) {
            calendar.unselect();

            if (!outcome.opensModal) {
                if (outcome.message) {
                    notify(outcome.message);
                }
                return;
            }

            openCreateScheduleModal(buildScheduleCreateContext({
                view: currentView,
                date: outcome.start,
                start: outcome.start,
                end: outcome.end,
                timed: true,
                doctorId: doctorFilter.value,
                siteId: siteFilter.value,
            }));
        }

        function paintSnapshot() {
            return { painting: painting, dates: paintedDates };
        }

        function monthHitFromNode(node) {
            if (!node || !node.closest) {
                return { date: '', inMonth: false, onEvent: false };
            }

            if (node.closest('.fc-event, .fc-daygrid-more-link, .fc-popover')) {
                return { date: '', inMonth: false, onEvent: true };
            }

            const day = node.closest('.fc-daygrid-day');
            if (!day) {
                return { date: '', inMonth: false, onEvent: false };
            }

            return {
                date: day.getAttribute('data-date') || '',
                inMonth: !day.classList.contains('fc-day-other'),
                onEvent: false,
            };
        }

        function monthHitFromPoint(x, y) {
            return monthHitFromNode(document.elementFromPoint(x, y));
        }

        function renderPaintHighlights() {
            calendarElement.querySelectorAll('.fc-daygrid-day').forEach(day => {
                const selected = paintedDates.includes(day.getAttribute('data-date'))
                    && !day.classList.contains('fc-day-other');
                day.classList.toggle('is-painted-date', selected);
            });
        }

        function renderPaintedPanel() {
            const box = document.getElementById('schedule-painted-dates');
            const count = document.getElementById('schedule-painted-count');
            const chips = document.getElementById('schedule-painted-chips');
            if (!box || !count || !chips) return;

            const active = Array.isArray(activePaintedDates);
            box.hidden = !active;
            if (!active) return;

            count.textContent = paintedDateCountLabel(paintedDates);
            chips.replaceChildren();
            paintedDateGroups(paintedDates).forEach(group => {
                const section = document.createElement('section');
                section.className = 'schedule-painted-weekday';

                const header = document.createElement('div');
                header.className = 'schedule-painted-weekday__header';

                const title = document.createElement('strong');
                title.className = 'schedule-painted-weekday__label';
                title.textContent = group.label;

                const clear = document.createElement('button');
                clear.type = 'button';
                clear.className = 'schedule-painted-weekday__clear';
                clear.textContent = 'Quitar todos los ' + group.plural;
                clear.addEventListener('click', () => removePaintedWeekday(group.weekday));

                header.append(title, clear);
                section.append(header);

                const items = document.createElement('div');
                items.className = 'schedule-painted-weekday__dates';
                group.dates.forEach(chip => {
                    const item = document.createElement('span');
                    item.className = 'schedule-painted-chip';
                    item.append(document.createTextNode(chip.label));
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'schedule-painted-chip__remove';
                    remove.setAttribute('aria-label', 'Quitar ' + chip.label);
                    remove.textContent = '×';
                    remove.addEventListener('click', () => removePaintedDate(chip.date));
                    item.append(remove);
                    items.append(item);
                });
                section.append(items);
                chips.append(section);
            });
            const scope = document.getElementById('schedule-scope');
            if (scope && !document.getElementById('schedule-id').value) {
                scope.hidden = paintedDates.length > 1;
            }
        }

        function removePaintedDate(date) {
            const next = reduceMonthPaint(paintSnapshot(), { type: 'remove', date: date });
            paintedDates = next.dates;
            painting = false;
            if (Array.isArray(activePaintedDates)) activePaintedDates = next.dates.slice();
            renderPaintHighlights();
            renderPaintedPanel();
            renderSelectionBar();
            document.getElementById('schedule-date').value = paintedDates[0] || '';
        }

        function removePaintedWeekday(weekday) {
            const next = reduceMonthPaint(paintSnapshot(), { type: 'remove-weekday', weekday: weekday });
            paintedDates = next.dates;
            painting = false;
            if (Array.isArray(activePaintedDates)) activePaintedDates = next.dates.slice();
            renderPaintHighlights();
            renderPaintedPanel();
            renderSelectionBar();
            document.getElementById('schedule-date').value = paintedDates[0] || '';
        }

        function clearPaintedSelection() {
            const next = reduceMonthPaint(paintSnapshot(), { type: 'clear' });
            paintedDates = next.dates;
            painting = false;
            activePaintedDates = null;
            renderPaintHighlights();
            renderPaintedPanel();
            renderSelectionBar();
        }

        function renderSelectionBar() {
            const panel = document.getElementById('schedule-selection');
            const count = document.getElementById('schedule-selection-count');
            if (!panel || !count) return;
            panel.hidden = paintedDates.length === 0;
            count.textContent = paintedDateCountLabel(paintedDates);
        }

        function openPaintedDates(dates) {
            openCreateScheduleModal(buildScheduleCreateContext({
                view: 'dayGridMonth',
                date: dates[0],
                timed: false,
                dates: dates,
                doctorId: doctorFilter.value,
                siteId: siteFilter.value,
            }));
        }

        calendarElement.addEventListener('pointerdown', function (event) {
            weekGesture = null;
            if (!canManage || currentView !== 'dayGridMonth' || event.button !== 0) return;

            const hit = monthHitFromNode(event.target);
            const next = reduceMonthPaint(paintSnapshot(), Object.assign({ type: 'down' }, hit));
            if (!next.painting) return;

            event.preventDefault();
            event.stopPropagation();
            painting = true;
            paintedDates = next.dates;
            renderPaintHighlights();
            renderSelectionBar();
        });

        window.addEventListener('pointermove', function (event) {
            if (!painting) return;

            const next = reduceMonthPaint(paintSnapshot(), Object.assign({ type: 'enter' }, monthHitFromPoint(event.clientX, event.clientY)));
            if (!next.changed) return;
            paintedDates = next.dates;
            renderPaintHighlights();
            renderSelectionBar();
        });

        calendarElement.addEventListener('contextmenu', function (event) {
            if (currentView !== 'dayGridMonth') return;

            const next = reduceMonthPaint(paintSnapshot(), Object.assign({ type: 'contextmenu' }, monthHitFromNode(event.target)));
            if (next.preventMenu) event.preventDefault();
            if (!next.changed) return;
            paintedDates = next.dates;
            painting = false;
            if (Array.isArray(activePaintedDates)) activePaintedDates = next.dates.slice();
            renderPaintHighlights();
            renderPaintedPanel();
            renderSelectionBar();
            document.getElementById('schedule-date').value = paintedDates[0] || '';
        });

        window.addEventListener('pointerup', function () {
            if (painting) {
                const next = reduceMonthPaint(paintSnapshot(), { type: 'up' });
                painting = false;
                paintedDates = next.dates;
                renderPaintHighlights();
                renderSelectionBar();
            }

            const outcome = weekGesture;

            window.setTimeout(function () {
                if (!outcome || weekGesture !== outcome || currentView !== 'timeGridWeek') {
                    return;
                }

                weekGesture = null;
                presentSelection(outcome);
            }, 0);
        });

        function onEventClick(info) {
            selectEvent(info.event);

            if (canManage) {
                openEdit(info.event.extendedProps);
            }
        }

        function renderEvent(info) {
            const props = info.event.extendedProps;
            const lines = scheduleEventLines(props);

            if (!lines) {
                return { domNodes: [] };
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'schedule-event';

            const time = document.createElement('span');
            time.className = 'schedule-event__time';
            time.textContent = lines.time;

            const doctor = document.createElement('span');
            doctor.className = 'schedule-event__doctor';
            doctor.textContent = lines.doctor;

            const cadence = document.createElement('span');
            cadence.className = 'schedule-event__cadence';
            cadence.textContent = lines.cadence;

            wrapper.append(time, doctor);
            if (info.view.type !== 'dayGridMonth') {
                wrapper.append(cadence);
            }
            wrapper.title = scheduleEventText(props, info.view.type)
                + (props.recurrence_label ? ' · ' + props.recurrence_label : '');

            return { domNodes: [wrapper] };
        }

        function wireToolbar() {
            document.querySelectorAll('[data-calendar-action]').forEach(button => {
                button.addEventListener('click', () => calendar[button.dataset.calendarAction]());
            });
            document.querySelectorAll('[data-calendar-view]').forEach(button => {
                button.addEventListener('click', () => calendar.changeView(button.dataset.calendarView));
            });
            document.getElementById('schedule-add').addEventListener('click', () => {
                if (!canManage) return;
                openCreateScheduleModal(toolbarScheduleContext(currentView, calendar.getDate(), {
                    doctorId: doctorFilter.value,
                    siteId: siteFilter.value,
                }));
            });
            const configureSelection = document.getElementById('schedule-selection-configure');
            const clearSelection = document.getElementById('schedule-selection-clear');
            if (configureSelection) {
                configureSelection.addEventListener('click', () => {
                    if (!canManage || !paintedDates.length) return;
                    openPaintedDates(paintedDates.slice());
                });
            }
            if (clearSelection) {
                clearSelection.addEventListener('click', () => clearPaintedSelection());
            }
        }

        function wireFilters() {
            [siteFilter, doctorFilter].forEach(field => field.addEventListener('change', () => calendar.refetchEvents()));
            specialtyFilter.addEventListener('change', () => {
                const specialtyId = specialtyFilter.value;
                Array.from(doctorFilter.options).forEach(option => {
                    if (!option.value) return;
                    option.hidden = Boolean(specialtyId && option.dataset.specialtyId !== specialtyId);
                });
                const current = doctorFilter.selectedOptions[0];
                if (current && current.hidden) doctorFilter.value = '';
                calendar.refetchEvents();
            });
        }

        function wireEditor() {
            modalElement.querySelectorAll('[data-bs-dismiss="modal"]').forEach(button => {
                button.addEventListener('click', () => {
                    modal && modal.hide();
                });
            });
            document.getElementById('schedule-doctor').addEventListener('change', updateSpecialty);
            document.querySelectorAll('input[name="scope"]').forEach(radio => radio.addEventListener('change', updateScope));
            document.querySelectorAll('[data-weekdays]').forEach(button => {
                button.addEventListener('click', () => {
                    const selected = button.dataset.weekdays.split(',');
                    document.querySelectorAll('input[name="weekdays[]"]').forEach(check => {
                        check.checked = selected.includes(check.value);
                    });
                    scheduleOverlapCheck();
                });
            });
            ['schedule-doctor', 'schedule-date', 'schedule-start', 'schedule-end'].forEach(id => {
                document.getElementById(id).addEventListener('change', scheduleOverlapCheck);
            });
            form.addEventListener('submit', submitForm);
            document.getElementById('schedule-delete').addEventListener('click', deleteSchedule);
            if (globalThis.ScheduleTime) {
                globalThis.ScheduleTime.bind(document.getElementById('schedule-editor'));
            }
        }

        function syncScheduleTimes() {
            if (!globalThis.ScheduleTime) {
                return;
            }

            globalThis.ScheduleTime.sync(document.getElementById('schedule-start'));
            globalThis.ScheduleTime.sync(document.getElementById('schedule-end'));
        }

        function openCreateScheduleModal(context) {
            form.reset();
            clearError();
            document.getElementById('schedule-id').value = '';
            document.getElementById('schedule-recurrence').value = 'DATE';
            document.getElementById('schedule-editor-title').textContent = 'Nuevo horario';
            document.getElementById('schedule-date').value = context.date;
            document.getElementById('schedule-start').value = context.start;
            document.getElementById('schedule-end').value = context.end;
            syncScheduleTimes();
            document.getElementById('schedule-duration').value = context.duration ? String(context.duration) : '20';
            document.getElementById('schedule-site').value = context.siteId;
            document.getElementById('schedule-doctor').value = context.doctorId;
            document.getElementById('schedule-scope').hidden = false;
            document.getElementById('schedule-edit-scope').hidden = true;
            document.getElementById('schedule-delete').hidden = true;
            document.getElementById('schedule-save').textContent = 'Revisar y guardar';
            const weekday = document.querySelector('input[name="weekdays[]"][value="' + isoWeekday(new Date(context.date + 'T12:00:00')) + '"]');
            if (weekday) weekday.checked = true;
            if (Array.isArray(context.dates)) {
                activePaintedDates = context.dates.slice();
                paintedDates = activePaintedDates.slice();
            } else {
                activePaintedDates = null;
            }
            painting = false;
            renderPaintHighlights();
            renderPaintedPanel();
            renderSelectionBar();
            const applyNotice = document.getElementById('schedule-preset-apply');
            if (applyNotice) {
                applyNotice.hidden = !context.notice;
                applyNotice.textContent = context.notice || '';
            }
            updateSpecialty();
            updateScope();
            if (document.getElementById('schedule-scope') && activePaintedDates && activePaintedDates.length > 1) {
                document.getElementById('schedule-scope').hidden = true;
            }
            modal && modal.show();
            scheduleOverlapCheck();
        }

        function openEdit(props, proposal) {
            form.reset();
            clearError();
            document.getElementById('schedule-id').value = props.schedule_id;
            document.getElementById('schedule-recurrence').value = props.recurrence;
            document.getElementById('schedule-editor-title').textContent = 'Modificar horario de ' + props.doctor_name;
            document.getElementById('schedule-doctor').value = props.doctor_id;
            document.getElementById('schedule-site').value = props.site_id || '';
            document.getElementById('schedule-date').value = proposal?.date || props.occurrence_date;
            document.getElementById('schedule-start').value = proposal?.start || props.start_time;
            document.getElementById('schedule-end').value = proposal?.end || props.end_time;
            syncScheduleTimes();
            document.getElementById('schedule-duration').value = props.appointment_duration;
            document.getElementById('schedule-scope').hidden = true;
            document.getElementById('schedule-weekdays').hidden = true;
            document.getElementById('schedule-edit-scope').hidden = false;
            document.getElementById('schedule-edit-scope').textContent = props.recurrence === 'WEEKLY'
                ? 'Este bloque es semanal. El cambio afecta todas sus ocurrencias de ese día; una excepción individual requiere MVP-B.'
                : 'Este bloque corresponde únicamente a la fecha indicada.';
            document.getElementById('schedule-delete').hidden = false;
            document.getElementById('schedule-save').textContent = 'Revisar cambio';
            document.getElementById('schedule-painted-dates').hidden = true;
            updateSpecialty();
            modal && modal.show();
            scheduleOverlapCheck();
        }

        async function submitForm(event) {
            event.preventDefault();
            clearError();

            const scheduleId = document.getElementById('schedule-id').value;
            if (!scheduleId && Array.isArray(activePaintedDates) && activePaintedDates.length === 0) {
                showError(PAINTED_DATE_REQUIRED_MESSAGE);
                return;
            }

            const payload = scheduleId ? updatePayload() : createPayload();

            if (!validatePayload(payload, Boolean(scheduleId))) return;

            if (!scheduleId && Array.isArray(activePaintedDates) && activePaintedDates.length > 0) {
                const doctor = document.getElementById('schedule-doctor');
                const doctorName = doctor.selectedOptions[0] ? doctor.selectedOptions[0].textContent.trim() : '';
                const accepted = await confirmAction(
                    'Programar horario',
                    paintedConfirmationText(doctorName, activePaintedDates, payload.hora_inicio, payload.hora_fin, payload.duracion_cita),
                    'Confirmar programación'
                );
                if (!accepted) return;
                await send(host.dataset.store, 'POST', payload);
                return;
            }

            if (scheduleId) {
                const impact = await fetchImpact(scheduleId, 'update', payload);
                const accepted = await confirmAction(
                    'Confirmar modificación',
                    impact.message + ' No se moverá ni cancelará ninguna cita.'
                );
                if (!accepted) return;
                await send(host.dataset.update, 'PUT', payload);
            } else {
                const isMass = payload.scope !== 'single';
                const dates = payload.scope === 'selected'
                    ? selectedWeekDates(payload.fecha_cita, payload.weekdays)
                    : payload.weekdays;
                const description = isMass
                    ? 'Se crearán ' + dates.length + ' bloques. La operación requiere confirmación explícita.'
                    : 'Se creará un bloque puntual para la fecha seleccionada.';
                const accepted = await confirmAction('Confirmar horario', description);
                if (!accepted) return;
                await send(host.dataset.store, 'POST', payload);
            }
        }

        async function deleteSchedule() {
            const scheduleId = document.getElementById('schedule-id').value;
            if (!scheduleId) return;

            const impact = await fetchImpact(scheduleId, 'delete', {});
            const accepted = await confirmAction(
                'Inactivar horario',
                impact.message + ' Las citas no serán eliminadas ni modificadas.'
            );
            if (!accepted) return;
            await send(host.dataset.delete, 'POST', { id: Number(scheduleId) });
        }

        function createPayload() {
            if (Array.isArray(activePaintedDates) && activePaintedDates.length > 1) {
                return concreteDatesPayload({
                    doctorId: document.getElementById('schedule-doctor').value,
                    siteId: document.getElementById('schedule-site').value,
                    dates: activePaintedDates,
                    start: document.getElementById('schedule-start').value,
                    end: document.getElementById('schedule-end').value,
                    duration: document.getElementById('schedule-duration').value,
                });
            }

            const scope = document.querySelector('input[name="scope"]:checked').value;
            return Object.assign({
                doctor_id: Number(document.getElementById('schedule-doctor').value),
                site_id: nullableNumber(document.getElementById('schedule-site').value),
                scope,
                fecha_cita: document.getElementById('schedule-date').value,
                hora_inicio: document.getElementById('schedule-start').value,
                hora_fin: document.getElementById('schedule-end').value,
                duracion_cita: Number(document.getElementById('schedule-duration').value),
            }, weekdayField(scope, checkedWeekdays()));
        }

        function updatePayload() {
            const date = document.getElementById('schedule-date').value;
            return {
                doctor_schedule_id_edit: Number(document.getElementById('schedule-id').value),
                doctor_id_edit: Number(document.getElementById('schedule-doctor').value),
                site_id_edit: nullableNumber(document.getElementById('schedule-site').value),
                fecha_cita_edit: date,
                dia_semana_edit: isoWeekday(new Date(date + 'T12:00:00')),
                hora_inicio_edit: document.getElementById('schedule-start').value,
                hora_fin_edit: document.getElementById('schedule-end').value,
                duracion_edit_cita: Number(document.getElementById('schedule-duration').value),
            };
        }

        async function confirmMove(info, action) {
            info.revert();
            const props = info.event.extendedProps;
            const proposal = {
                date: dateOnly(info.event.start),
                start: timeOnly(info.event.start),
                end: timeOnly(info.event.end),
            };
            const payload = {
                doctor_schedule_id_edit: props.schedule_id,
                doctor_id_edit: props.doctor_id,
                site_id_edit: props.site_id,
                fecha_cita_edit: proposal.date,
                dia_semana_edit: isoWeekday(info.event.start),
                hora_inicio_edit: proposal.start,
                hora_fin_edit: proposal.end,
                duracion_edit_cita: props.appointment_duration,
            };
            const impact = await fetchImpact(props.schedule_id, 'update', payload);
            const accepted = await confirmAction(
                action === 'resize' ? 'Confirmar cambio de extensión' : 'Confirmar movimiento',
                scheduleMoveText(props, proposal, impact.message)
            );
            if (accepted) await send(host.dataset.update, 'PUT', payload);
        }

        async function fetchImpact(scheduleId, action, payload) {
            const endpoint = host.dataset.impactTemplate.replace('__ID__', scheduleId);
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('action', action);
            if (visibleRange) {
                url.searchParams.set('range_start', visibleRange.start);
                url.searchParams.set('range_end', visibleRange.end);
            }
            if (payload.fecha_cita_edit) url.searchParams.set('new_date', payload.fecha_cita_edit);
            if (payload.dia_semana_edit) url.searchParams.set('new_weekday', payload.dia_semana_edit);
            if (payload.hora_inicio_edit) url.searchParams.set('new_start', payload.hora_inicio_edit);
            if (payload.hora_fin_edit) url.searchParams.set('new_end', payload.hora_fin_edit);

            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            return response.ok ? response.json() : { count: 0, message: 'No se pudo calcular el impacto.' };
        }

        async function send(url, method, payload) {
            setBusy(true);
            try {
                const response = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await response.json();
                if (!response.ok || data.code === 0) {
                    showError(validationMessage(data));
                    return;
                }
                clearPaintedSelection();
                modal && modal.hide();
                calendar.refetchEvents();
                if (window.Swal) {
                    await Swal.fire({ icon: 'success', title: 'Horario actualizado', text: data.msg, timer: 1800, showConfirmButton: false });
                }
            } catch (error) {
                showError('No fue posible guardar el horario. Revise la conexión local.');
            } finally {
                setBusy(false);
            }
        }

        function selectEvent(event) {
            if (selectedEvent) selectedEvent.setProp('classNames', []);
            selectedEvent = event;
            event.setProp('classNames', ['is-selected-schedule']);
            paintDetail(event);
        }

        function paintDetail(event) {
            const props = event.extendedProps;
            detail.innerHTML = '<h2>Detalle</h2><dl>'
                + row('Médico', props.doctor_name)
                + row('Especialidad', props.specialty_name || '—')
                + row('Sede', props.site_name)
                + row('Fecha', props.occurrence_date)
                + row('Horario', props.start_time + '–' + props.end_time)
                + row('Cadencia', props.appointment_duration + ' min')
                + row('Tipo', props.recurrence_label)
                + '</dl>';
        }

        function renderLegend() {
            const panel = document.getElementById('schedule-compare');
            const summary = document.getElementById('schedule-compare-summary');
            const state = doctorComparisonState(legendDoctors(), doctorFilter.value, hiddenDoctorIds);
            const heading = doctorComparisonSummary(state);
            if (panel) panel.hidden = !heading.shown;
            if (summary) summary.textContent = heading.text;
            legend.replaceChildren();
            if (!heading.shown) return;
            if (!state.doctors.length) {
                const empty = document.createElement('p');
                empty.className = 'schedule-detail__empty';
                empty.textContent = 'Sin horarios en el periodo.';
                legend.append(empty);
                return;
            }
            const actions = document.createElement('div');
            actions.className = 'schedule-doctor-visibility__actions';
            [['all', 'Todos'], ['none', 'Ninguno']].forEach(([action, label]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'schedule-btn';
                button.textContent = label;
                button.addEventListener('click', () => applyVisibility(action));
                actions.append(button);
            });
            legend.append(actions);
            state.doctors.forEach(doctor => {
                const item = document.createElement('label');
                item.className = 'schedule-doctor-key';
                const check = document.createElement('input');
                check.type = 'checkbox';
                check.checked = doctor.visible;
                check.addEventListener('change', () => applyVisibility('toggle', doctor.id));
                const marker = document.createElement('span');
                marker.className = 'schedule-doctor-key__color';
                marker.style.setProperty('--doctor-color', doctor.color);
                const name = document.createElement('span');
                name.textContent = doctor.initial + ' · ' + doctor.name;
                item.append(check, marker, name);
                legend.append(item);
            });
        }

        function renderFrequentPresets(events) {
            const panel = document.getElementById('schedule-presets');
            const list = document.getElementById('schedule-preset-list');
            if (!panel || !list) return;

            const state = frequentSchedulePresets(events, doctorFilter.value);
            panel.hidden = !state.visible;
            list.replaceChildren();
            showPresetHint('');
            if (!state.visible) return;

            if (!state.presets.length) {
                const empty = document.createElement('p');
                empty.className = 'schedule-detail__empty';
                empty.textContent = state.message;
                list.append(empty);
                return;
            }

            state.presets.forEach(preset => list.append(presetCard(preset)));
        }

        function presetCard(preset) {
            const card = document.createElement('article');
            card.className = 'schedule-preset';
            card.draggable = canManage;
            card.style.setProperty('--doctor-color', doctorColor(preset.doctor_id));
            card.append(
                presetLine(doctorInitials(preset.doctor_name) + ' · ' + preset.doctor_name, 'schedule-preset__doctor'),
                presetLine(preset.hora_inicio + ' – ' + preset.hora_fin, 'schedule-preset__hours'),
                presetLine(preset.duracion_cita + ' min por cita', 'schedule-preset__cadence'),
                presetLine(presetFrequencyLabel(preset.count), 'schedule-preset__count')
            );
            if (!canManage) return card;

            card.addEventListener('dragstart', event => {
                presetDragged = true;
                card.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'copy';
                event.dataTransfer.setData(PRESET_DRAG_MIME, JSON.stringify({
                    doctor_id: preset.doctor_id,
                    doctor_name: preset.doctor_name,
                    hora_inicio: preset.hora_inicio,
                    hora_fin: preset.hora_fin,
                    duracion_cita: preset.duracion_cita,
                }));
            });
            card.addEventListener('dragend', () => {
                card.classList.remove('is-dragging');
                clearPresetHover();
                window.setTimeout(() => { presetDragged = false; }, 0);
            });
            card.addEventListener('click', () => {
                if (presetDragged) return;
                const decision = presetClickDecision(preset, paintedDates);
                if (!decision.open) {
                    showPresetHint(decision.message);
                    return;
                }
                showPresetHint('');
                openPresetPreview(decision);
            });
            return card;
        }

        function presetLine(text, className) {
            const line = document.createElement('span');
            line.className = className;
            line.textContent = text;
            return line;
        }

        function showPresetHint(text) {
            const hint = document.getElementById('schedule-preset-hint');
            if (!hint) return;
            hint.hidden = !text;
            hint.textContent = text || '';
        }

        function carriesPreset(dataTransfer) {
            return Array.from(dataTransfer.types || []).indexOf(PRESET_DRAG_MIME) !== -1;
        }

        function clearPresetHover() {
            calendarElement.querySelectorAll('.is-preset-hover').forEach(day => day.classList.remove('is-preset-hover'));
        }

        function presetHitFromNode(node) {
            if (!node || !node.closest) {
                return { date: '', inMonth: false };
            }

            const day = node.closest('.fc-daygrid-day');
            if (!day) {
                return { date: '', inMonth: false };
            }

            return {
                date: day.getAttribute('data-date') || '',
                inMonth: !day.classList.contains('fc-day-other'),
            };
        }

        function openPresetPreview(decision) {
            openCreateScheduleModal(buildScheduleCreateContext({
                view: 'dayGridMonth',
                date: decision.date,
                start: decision.start,
                end: decision.end,
                keepTimes: true,
                duration: decision.duration,
                notice: decision.notice,
                dates: decision.dates,
                doctorId: doctorFilter.value,
                siteId: siteFilter.value,
            }));
        }

        calendarElement.addEventListener('dragover', function (event) {
            if (!canManage || currentView !== 'dayGridMonth' || !carriesPreset(event.dataTransfer)) return;

            const target = monthPresetTarget(presetHitFromNode(event.target));
            clearPresetHover();
            if (!target.valid) return;

            event.preventDefault();
            const day = event.target.closest && event.target.closest('.fc-daygrid-day');
            if (day) day.classList.add('is-preset-hover');
        });

        calendarElement.addEventListener('drop', function (event) {
            if (!canManage || !carriesPreset(event.dataTransfer)) return;

            clearPresetHover();
            let preset = null;
            try {
                preset = JSON.parse(event.dataTransfer.getData(PRESET_DRAG_MIME) || 'null');
            } catch (error) {
                preset = null;
            }
            const target = currentView === 'dayGridMonth'
                ? monthPresetTarget(presetHitFromNode(event.target))
                : { date: '', valid: false };
            const decision = presetDropDecision({
                preset: preset,
                target: target,
                selectedDates: paintedDates,
            });
            if (!decision.open) return;

            event.preventDefault();
            openPresetPreview(decision);
        });

        function updateSpecialty() {
            const option = document.getElementById('schedule-doctor').selectedOptions[0];
            document.getElementById('schedule-specialty').value = option?.dataset.specialtyName || '';
        }

        function updateScope() {
            const scope = document.querySelector('input[name="scope"]:checked').value;
            const weekdays = document.getElementById('schedule-weekdays');
            weekdays.hidden = scope === 'single';
            document.getElementById('schedule-scope-help').textContent = scope === 'weekly'
                ? 'Se guardará un bloque recurrente sin fecha final. No permite excepciones individuales todavía.'
                : 'Se guardará un bloque independiente por cada día seleccionado dentro de la semana de referencia.';
            scheduleOverlapCheck();
        }

        function scheduleOverlapCheck() {
            window.clearTimeout(overlapTimer);
            overlapTimer = window.setTimeout(checkOverlap, 180);
        }

        async function checkOverlap() {
            const doctor = document.getElementById('schedule-doctor').value;
            const date = document.getElementById('schedule-date').value;
            const start = document.getElementById('schedule-start').value;
            const end = document.getElementById('schedule-end').value;
            const target = document.getElementById('schedule-overlap-status');
            target.textContent = '';
            target.className = 'schedule-overlap-status';
            if (!doctor || !date || !start || !end || end <= start) return;

            const url = new URL(host.dataset.overlap, window.location.origin);
            url.searchParams.set('doctor_id', doctor);
            url.searchParams.set('fecha_cita', date);
            url.searchParams.set('hora_inicio', start);
            url.searchParams.set('hora_fin', end);
            const id = document.getElementById('schedule-id').value;
            if (id) url.searchParams.set('doctor_schedule_id', id);

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                target.textContent = data.mensaje;
                target.classList.add(data.solapa ? 'is-warning' : 'is-clear');
            } catch (error) {
                target.textContent = '';
            }
        }

        function notify(text) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'info',
                    title: 'Horario de un solo día',
                    text: text,
                    confirmButtonColor: '#176b73',
                });
                return;
            }

            window.alert(text);
        }

        async function confirmAction(title, text, confirmLabel) {
            if (!window.Swal) return window.confirm(title + '\n\n' + text);
            const result = await Swal.fire({
                icon: 'warning',
                title,
                html: String(text).split('\n').map(escapeHtml).join('<br>'),
                showCancelButton: true,
                confirmButtonText: confirmLabel || 'Confirmar cambio',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#176b73',
            });
            return result.isConfirmed;
        }

        function validatePayload(payload, editing) {
            if (!payload.doctor_id && !payload.doctor_id_edit) return showError(DOCTOR_REQUIRED_MESSAGE), false;
            const start = editing ? payload.hora_inicio_edit : payload.hora_inicio;
            const end = editing ? payload.hora_fin_edit : payload.hora_fin;
            if (!start || !end || end <= start) return showError('La hora fin debe ser posterior a la hora inicio.'), false;
            if (!editing && payload.scope === 'dates' && !(payload.dates && payload.dates.length)) {
                return showError(PAINTED_DATE_REQUIRED_MESSAGE), false;
            }
            if (!editing && payload.scope !== 'single' && payload.scope !== 'dates' && !(payload.weekdays && payload.weekdays.length)) {
                return showError('Selecciona al menos un día de la semana.'), false;
            }
            return true;
        }

        function checkedWeekdays() {
            return Array.from(document.querySelectorAll('input[name="weekdays[]"]:checked')).map(item => Number(item.value));
        }

        function appendFilter(url, name, value) {
            if (value) url.searchParams.set(name, value);
        }

        function nullableNumber(value) {
            return value === '' ? null : Number(value);
        }

        function validationMessage(data) {
            if (data.error) return Object.values(data.error).flat().join(' ');
            if (data.errors) return Object.values(data.errors).flat().join(' ');
            return data.msg || data.message || 'No fue posible guardar el horario.';
        }

        function showError(message) {
            const target = document.getElementById('schedule-form-error');
            target.hidden = false;
            target.textContent = message;
        }

        function clearError() {
            const target = document.getElementById('schedule-form-error');
            target.hidden = true;
            target.textContent = '';
        }

        function setBusy(busy) {
            document.getElementById('schedule-save').disabled = busy;
            document.getElementById('schedule-delete').disabled = busy;
        }

        function row(label, value) {
            return '<dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(value || '—') + '</dd>';
        }

        function escapeHtml(value) {
            const node = document.createElement('span');
            node.textContent = String(value);
            return node.innerHTML;
        }
    }

    return {
        refreshedSelection,
        scheduleMoveText,
        dateOnly,
        isoWeekday,
        selectedWeekDates,
        scheduleEventText,
        scheduleEventLines,
        isDoctorScheduleProps,
        clickSelection,
        clampScheduleSelection,
        buildScheduleCreateContext,
        toolbarScheduleContext,
        shiftNavigationDate,
        preservedFilters,
        resolveScheduleDoctor,
        scheduleInteractionKind,
        initialScheduleView,
        doctorColor,
        doctorInitials,
        visibleScheduleEvents,
        doctorComparisonState,
        doctorComparisonSummary,
        visibilityAfterAction,
        reduceMonthPaint,
        paintedDateChips,
        weekdayFromIsoDate,
        paintedDateGroups,
        paintedDateCountLabel,
        paintedSaveDecision,
        concreteDatesPayload,
        paintedConfirmationText,
        frequentSchedulePresets,
        presetFrequencyLabel,
        monthPresetTarget,
        presetDropDecision,
        presetClickDecision,
        monthSelectionOnRangeChange,
        monthSelectionAfterDismiss,
        configureSelectionContext,
        PRESET_DRAG_MIME,
        PRESET_EMPTY_MESSAGE,
        PRESET_NEEDS_DATE_MESSAGE,
        PAINTED_DATE_REQUIRED_MESSAGE,
        DOCTOR_REQUIRED_MESSAGE,
        needsExplicitConfirmation,
        weekdayField,
        mount,
    };
}));
