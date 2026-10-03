'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const workspace = require('../../public/js/scheduling/schedule-workspace.js');

const root = path.resolve(__dirname, '../..');
const modal = fs.readFileSync(path.join(root, 'resources/views/admissionist/schedule/workspace-modal.blade.php'), 'utf8');
const index = fs.readFileSync(path.join(root, 'resources/views/admissionist/schedule/index.blade.php'), 'utf8');
const script = fs.readFileSync(path.join(root, 'public/js/scheduling/schedule-workspace.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'public/css/scheduling/schedule-workspace.css'), 'utf8');

test('solo este día no envía weekdays', () => {
    assert.deepEqual(workspace.weekdayField('single', []), {});
    assert.deepEqual(workspace.weekdayField('single', [1]), {});
});

test('días seleccionados y patrón semanal sí envían weekdays', () => {
    assert.deepEqual(workspace.weekdayField('selected', [1, 3]), { weekdays: [1, 3] });
    assert.deepEqual(workspace.weekdayField('weekly', [2]), { weekdays: [2] });
});

test('selected weekdays resolve to concrete dates in the reference week', () => {
    assert.deepEqual(
        workspace.selectedWeekDates('2026-10-07', [5, 1, 3]),
        ['2026-10-05', '2026-10-07', '2026-10-09']
    );
});

test('week and day labels expose time doctor and programmed cadence', () => {
    const text = workspace.scheduleEventText({
        start_time: '09:00',
        end_time: '13:00',
        doctor_name: 'Bruno Salas',
        appointment_duration: 20,
    }, 'timeGridWeek');

    assert.equal(text, '09:00–13:00 Bruno Salas · 20 min');
});

test('month label stays macro and omits slot cadence', () => {
    const text = workspace.scheduleEventText({
        start_time: '09:00',
        end_time: '13:00',
        doctor_name: 'Bruno Salas',
        appointment_duration: 20,
    }, 'dayGridMonth');

    assert.equal(text, '09:00–13:00 Bruno Salas');
});

test('every risky pointer or mass action requires explicit confirmation', () => {
    ['mass-create', 'drag', 'resize', 'update', 'delete'].forEach(action => {
        assert.equal(workspace.needsExplicitConfirmation(action), true);
    });
});

test('el modal de horario tiene ids únicos', () => {
    const ids = [...modal.matchAll(/\sid="([^"]+)"/g)].map(match => match[1]);
    const duplicated = ids.filter((id, index) => ids.indexOf(id) !== index);

    assert.deepEqual(duplicated, []);
    assert.ok(ids.includes('schedule-start'));
    assert.ok(ids.includes('schedule-end'));
    assert.ok(ids.includes('schedule-doctor'));
    assert.ok(ids.includes('schedule-site'));
    assert.ok(ids.includes('schedule-duration'));
});

test('la fecha de referencia queda como ancla interna y Aplicar horario ocupa el bloque principal', () => {
    assert.match(modal, /<input type="hidden" id="schedule-date" name="fecha_cita">/);
    assert.equal(modal.includes('Fecha de referencia'), false);
    assert.ok(modal.indexOf('id="schedule-scope"') > modal.indexOf('id="schedule-site"'));
    assert.ok(modal.indexOf('id="schedule-scope"') < modal.indexOf('class="schedule-attention"'));
});

test('cada label del modal envuelve un solo control', () => {
    const labels = modal.match(/<label[\s\S]*?<\/label>/g) || [];

    assert.ok(labels.length >= 10);
    labels.forEach(label => {
        const controls = label.match(/<(input|select|textarea)\b/g) || [];
        assert.equal(controls.length, 1, label);
    });
});

test('los inputs de hora quedan contenidos en su propio campo', () => {
    assert.equal(modal.includes('type="time"'), false);
    assert.ok(modal.includes('data-schedule-time="schedule-start"'));
    assert.ok(modal.includes('data-schedule-time="schedule-end"'));
    assert.ok(modal.includes('name="hora_inicio"'));
    assert.ok(modal.includes('name="hora_fin"'));
    assert.match(css, /\.schedule-attention\s*\{[^}]*grid-column:\s*1 \/ -1;/s);
    assert.match(css, /\.schedule-attention__range\s*\{[^}]*grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\);/s);
    assert.ok(modal.includes('Inicio de atención'));
    assert.ok(modal.includes('Fin de atención'));
    assert.ok(modal.includes('Minutos rápidos'));
    assert.match(index, /schedule-time\.js/);
});

function at(year, month, day, hours, minutes) {
    return new Date(year, month - 1, day, hours, minutes, 0, 0);
}

test('un click simple devuelve la fecha y la hora de inicio', () => {
    const click = workspace.clickSelection(at(2026, 10, 2, 9, 0));

    assert.equal(workspace.dateOnly(click.start), '2026-10-02');
    assert.equal(click.start.getHours(), 9);
    assert.equal(click.start.getMinutes(), 0);
    assert.equal(workspace.dateOnly(click.end), '2026-10-02');
    assert.equal(click.end.getHours(), 10);
    assert.equal(click.writes, false);
});

test('un arrastre vertical permanece en el mismo día', () => {
    const outcome = workspace.clampScheduleSelection(at(2026, 10, 2, 9, 0), at(2026, 10, 2, 11, 0));

    assert.equal(outcome.spansDays, false);
    assert.equal(workspace.dateOnly(outcome.start), '2026-10-02');
    assert.equal(outcome.start.getHours(), 9);
    assert.equal(outcome.end.getHours(), 11);
    assert.equal(outcome.opensModal, true);
    assert.equal(outcome.writes, false);
});

test('un arrastre horizontal no cambia la fecha inicial', () => {
    const outcome = workspace.clampScheduleSelection(at(2026, 10, 2, 9, 0), at(2026, 10, 4, 9, 0));

    assert.equal(workspace.dateOnly(outcome.start), '2026-10-02');
    assert.equal(workspace.dateOnly(outcome.end), '2026-10-02');
    assert.notEqual(workspace.dateOnly(outcome.end), '2026-10-04');
    assert.equal(outcome.writes, false);
});

test('una selección hacia otro día se ancla al día de origen', () => {
    const outcome = workspace.clampScheduleSelection(at(2026, 10, 2, 9, 0), at(2026, 10, 4, 11, 0));

    assert.equal(workspace.dateOnly(outcome.start), '2026-10-02');
    assert.equal(workspace.dateOnly(outcome.end), '2026-10-02');
    assert.equal(outcome.start.getHours(), 9);
    assert.equal(outcome.end.getHours(), 11);
    assert.equal(outcome.opensModal, true);
    assert.equal(outcome.writes, false);
});

test('cruzar la medianoche no produce un horario de varios días', () => {
    const outcome = workspace.clampScheduleSelection(at(2026, 10, 2, 23, 0), at(2026, 10, 3, 1, 0));

    assert.equal(outcome.opensModal, false);
    assert.equal(outcome.writes, false);
    assert.equal(workspace.dateOnly(outcome.start), '2026-10-02');
    assert.notEqual(workspace.dateOnly(outcome.end), '2026-10-03');
    assert.match(outcome.message, /medianoche/);
});

test('cancelar o previsualizar una selección no escribe horarios', () => {
    const preview = workspace.clampScheduleSelection(at(2026, 10, 2, 9, 0), at(2026, 10, 2, 11, 0));
    const rejected = workspace.clampScheduleSelection(at(2026, 10, 2, 23, 0), at(2026, 10, 3, 1, 0));

    assert.equal(preview.writes, false);
    assert.equal(rejected.writes, false);
    assert.equal(workspace.clickSelection(at(2026, 10, 2, 9, 0)).writes, false);
});

test('el renderer no produce undefined y descarta un objeto sin horario real', () => {
    const mirror = { start: at(2026, 10, 2, 9, 0), end: at(2026, 10, 2, 11, 0) };
    const lines = workspace.scheduleEventLines(mirror);
    const text = workspace.scheduleEventText(mirror, 'timeGridWeek');

    assert.equal(workspace.isDoctorScheduleProps(mirror), false);
    assert.equal(lines, null);
    assert.equal(text, '');
    assert.equal(String(lines).includes('undefined'), false);
    assert.equal(text.includes('undefined'), false);
});

test('un bloque real sigue renderizando médico, horas y cadencia', () => {
    const lines = workspace.scheduleEventLines({
        doctor_initial: 'BS',
        doctor_name: 'Bruno Salas',
        start_time: '09:00',
        end_time: '11:00',
        appointment_duration: 20,
    });

    assert.equal(lines.time, 'BS · 09:00–11:00');
    assert.equal(lines.doctor, 'Bruno Salas');
    assert.equal(lines.cadence, '20 min por cita');
    assert.equal(JSON.stringify(lines).includes('undefined'), false);
});

test('mover y redimensionar un bloque siguen siendo gestos de confirmación', () => {
    assert.equal(workspace.needsExplicitConfirmation('drag'), true);
    assert.equal(workspace.needsExplicitConfirmation('resize'), true);
});

test('semana: un click abre el alta con fecha y hora, sin escribir', () => {
    const click = workspace.clickSelection(at(2026, 10, 2, 9, 0));
    const context = workspace.buildScheduleCreateContext({
        view: 'timeGridWeek',
        date: click.start,
        start: click.start,
        end: click.end,
        timed: true,
        doctorId: '',
        siteId: '',
    });

    assert.equal(context.kind, 'create');
    assert.equal(context.date, '2026-10-02');
    assert.equal(context.start, '09:00');
    assert.equal(context.end, '10:00');
    assert.equal(context.writes, false);
    assert.equal(context.doctorId, '');
});

test('día: un click abre el alta con la fecha y el slot', () => {
    const click = workspace.clickSelection(at(2026, 10, 2, 11, 30));
    const context = workspace.buildScheduleCreateContext({
        view: 'timeGridDay',
        date: click.start,
        start: click.start,
        end: click.end,
        timed: true,
        doctorId: '4',
        siteId: '2',
    });

    assert.equal(context.date, '2026-10-02');
    assert.equal(context.start, '11:30');
    assert.equal(context.end, '12:30');
    assert.equal(context.doctorId, '4');
    assert.equal(context.siteId, '2');
    assert.equal(context.writes, false);
});

test('día: un arrastre vertical abre el alta con inicio y fin', () => {
    const outcome = workspace.clampScheduleSelection(at(2026, 10, 2, 9, 0), at(2026, 10, 2, 11, 0));
    const context = workspace.buildScheduleCreateContext({
        view: 'timeGridDay',
        date: outcome.start,
        start: outcome.start,
        end: outcome.end,
        timed: true,
        doctorId: '',
        siteId: '',
    });

    assert.equal(outcome.spansDays, false);
    assert.equal(context.start, '09:00');
    assert.equal(context.end, '11:00');
    assert.equal(context.writes, false);
});

test('mes: un click en la fecha abre el alta sin inventar horas', () => {
    const context = workspace.buildScheduleCreateContext({
        view: 'dayGridMonth',
        date: at(2026, 10, 15, 0, 0),
        timed: false,
        doctorId: '',
        siteId: '',
    });

    assert.equal(context.date, '2026-10-15');
    assert.equal(context.start, '');
    assert.equal(context.end, '');
    assert.equal(context.kind, 'create');
    assert.equal(context.writes, false);
});

test('un click en un bloque existente no es un alta', () => {
    assert.equal(workspace.scheduleInteractionKind('eventClick'), 'edit');
    assert.equal(workspace.scheduleInteractionKind('dateClick'), 'create');
});

test('+ Horario usa la fecha visible en día, semana y mes', () => {
    const filters = { doctorId: '4', siteId: '2' };
    const day = workspace.toolbarScheduleContext('timeGridDay', at(2026, 10, 2, 15, 0), filters);
    const week = workspace.toolbarScheduleContext('timeGridWeek', at(2026, 10, 2, 15, 0), filters);
    const month = workspace.toolbarScheduleContext('dayGridMonth', at(2026, 10, 1, 0, 0), { doctorId: '', siteId: '' });

    assert.equal(day.date, '2026-10-02');
    assert.equal(day.start, '09:00');
    assert.equal(day.end, '13:00');
    assert.equal(week.date, '2026-10-02');
    assert.equal(week.start, '09:00');
    assert.equal(week.end, '13:00');
    assert.equal(month.date, '2026-10-01');
    assert.equal(month.start, '');
    assert.equal(month.end, '');
});

test('un médico específico se precarga y Todos exige elegirlo', () => {
    assert.equal(workspace.resolveScheduleDoctor('4', null), '4');
    assert.equal(workspace.resolveScheduleDoctor('', '9'), '9');
    assert.equal(workspace.resolveScheduleDoctor('', null), '');
    assert.equal(workspace.resolveScheduleDoctor('Todos', '9'), '9');
    assert.equal(workspace.DOCTOR_REQUIRED_MESSAGE, 'Selecciona un médico.');
    assert.equal(script.includes('Selecciona un médico.'), true);
    assert.equal(script.includes('Seleccione un médico.'), false);
});

test('sede Todas no se convierte en un site_id', () => {
    const context = workspace.buildScheduleCreateContext({
        view: 'timeGridWeek',
        date: at(2026, 10, 2, 9, 0),
        start: at(2026, 10, 2, 9, 0),
        end: at(2026, 10, 2, 10, 0),
        timed: true,
        doctorId: '4',
        siteId: '',
    });

    assert.equal(context.siteId, '');
    assert.equal(workspace.buildScheduleCreateContext({
        view: 'timeGridDay',
        date: at(2026, 10, 2, 9, 0),
        start: at(2026, 10, 2, 9, 0),
        end: at(2026, 10, 2, 10, 0),
        siteId: 'Todas',
        doctorId: '4',
    }).siteId, '');
    assert.equal(workspace.buildScheduleCreateContext({
        view: 'timeGridDay',
        date: at(2026, 10, 2, 9, 0),
        start: at(2026, 10, 2, 9, 0),
        end: at(2026, 10, 2, 10, 0),
        siteId: '3',
        doctorId: '4',
    }).siteId, '3');
});

test('cambiar de vista conserva los filtros', () => {
    const filters = { siteId: '2', specialtyId: '7', doctorId: '4' };

    assert.deepEqual(workspace.preservedFilters(filters), filters);
});

test('navegar el mes cambia la fecha que usa + Horario', () => {
    const october = at(2026, 10, 1, 0, 0);
    const november = workspace.shiftNavigationDate(october, 'dayGridMonth', 'next');
    const context = workspace.toolbarScheduleContext('dayGridMonth', november, { doctorId: '', siteId: '' });

    assert.equal(workspace.dateOnly(november), '2026-11-01');
    assert.equal(context.date, '2026-11-01');
    assert.notEqual(context.date, '2026-10-01');
});

test('la vista inicial de horarios es Mes y las otras vistas siguen disponibles', () => {
    assert.equal(workspace.initialScheduleView(), 'dayGridMonth');
    assert.equal(script.includes("initialView: initialScheduleView()"), true);
    assert.equal(script.includes('calendar.changeView'), true);
    assert.match(index, /class="schedule-view-btn is-active" data-calendar-view="dayGridMonth"/);
    assert.equal(index.includes('data-calendar-view="timeGridWeek"'), true);
    assert.equal(index.includes('data-calendar-view="timeGridDay"'), true);
    assert.equal(index.includes('is-active" data-calendar-view="timeGridWeek"'), false);
});

test('el hover de los botones queda dentro del módulo y conserva contraste', () => {
    assert.match(css, /\.schedule-workspace \.schedule-view-btn\.is-active:hover[\s\S]*color:\s*#fff;/);
    assert.match(css, /\.schedule-workspace \.schedule-view-btn\.is-active:hover[\s\S]*background:\s*var\(--schedule-accent-dark\)/);
    assert.match(css, /\.schedule-view-btn:hover:not\(:disabled\):not\(\.is-active\)/);
    assert.match(css, /\.schedule-workspace \.schedule-btn:hover:not\(:disabled\):not\(\.schedule-btn--primary\):not\(\.schedule-btn--danger\)[\s\S]*color:\s*#22313d;/);
    assert.match(css, /\.schedule-workspace \.schedule-btn:disabled[\s\S]*color:\s*#6d7c86;/);
    assert.match(css, /\.schedule-workspace \.schedule-btn--danger:hover:not\(:disabled\)/);
    assert.equal(css.includes('.schedule-view-btn:hover:not(:disabled) {\n    border-color: #7f929f;\n    background: #f8fafb;'), false);
});

test('Médico Todos arma la comparación y un médico específico no', () => {
    const doctors = [
        { id: 4, name: 'Dr. Bruno Salas' },
        { id: 9, name: 'Dra. Ana Quispe' },
    ];
    const compare = workspace.doctorComparisonState(doctors, '', []);
    const single = workspace.doctorComparisonState(doctors, '4', ['4']);

    const compareSummary = workspace.doctorComparisonSummary(compare);
    const hiddenOne = workspace.visibilityAfterAction(['4', '9'], [], 'toggle', '4');
    const afterHide = workspace.doctorComparisonSummary(workspace.doctorComparisonState(doctors, '', hiddenOne));
    const afterAll = workspace.doctorComparisonSummary(workspace.doctorComparisonState(doctors, '', workspace.visibilityAfterAction(['4', '9'], hiddenOne, 'all')));
    const afterNone = workspace.doctorComparisonSummary(workspace.doctorComparisonState(doctors, '', workspace.visibilityAfterAction(['4', '9'], [], 'none')));
    const singleSummary = workspace.doctorComparisonSummary(single);

    assert.equal(compare.mode, 'compare');
    assert.deepEqual(compare.doctors.map(doctor => doctor.initial), ['BS', 'AQ']);
    assert.equal(compare.doctors.every(doctor => doctor.visible), true);
    assert.equal(compareSummary.shown, true);
    assert.equal(compareSummary.title, 'Comparar médicos');
    assert.equal(compareSummary.text, 'Mostrando 2 de 2');
    assert.equal(afterHide.text, 'Mostrando 1 de 2');
    assert.equal(afterAll.text, 'Mostrando 2 de 2');
    assert.equal(afterNone.text, 'Mostrando 0 de 2');
    assert.equal(single.mode, 'single');
    assert.deepEqual(single.doctors.map(doctor => doctor.id), ['4']);
    assert.equal(single.doctors[0].visible, true);
    assert.equal(singleSummary.shown, false);
    assert.equal(index.includes('value="">Todos los médicos</option>'), true);
    assert.equal(index.includes('>Médicos visibles<'), false);
    assert.equal(index.includes('Comparar médicos'), true);
});

test('ocultar y volver a mostrar un médico solo cambia la visualización', () => {
    const events = [
        { extendedProps: { doctor_id: 4, doctor_name: 'Bruno Salas' } },
        { extendedProps: { doctor_id: 9, doctor_name: 'Ana Quispe' } },
    ];
    const hidden = workspace.visibilityAfterAction(['4', '9'], [], 'toggle', '4');
    const hiddenEvents = workspace.visibleScheduleEvents(events, hidden);
    const restored = workspace.visibilityAfterAction(['4', '9'], hidden, 'toggle', '4');

    assert.deepEqual(hiddenEvents.map(event => event.extendedProps.doctor_id), [9]);
    assert.deepEqual(workspace.visibleScheduleEvents(events, restored).map(event => event.extendedProps.doctor_id), [4, 9]);
    assert.equal(workspace.doctorColor(4), workspace.doctorColor(4));
    assert.equal(workspace.doctorColor(4), '#9B5E32');
    assert.equal(workspace.doctorColor(11), workspace.doctorColor(1));
});

test('Todos muestra todos los médicos y Ninguno los oculta', () => {
    const ids = ['4', '9', '12'];
    const none = workspace.visibilityAfterAction(ids, [], 'none');
    const all = workspace.visibilityAfterAction(ids, none, 'all');
    const events = ids.map(id => ({ extendedProps: { doctor_id: id } }));

    assert.deepEqual(none, ids);
    assert.deepEqual(workspace.visibleScheduleEvents(events, none), []);
    assert.deepEqual(all, []);
    assert.equal(workspace.visibleScheduleEvents(events, all).length, 3);
});

function paint(actions) {
    return actions.reduce((state, action) => workspace.reduceMonthPaint(state, action), { painting: false, dates: [] });
}

const octoberHits = ['2026-10-05', '2026-10-06', '2026-10-08', '2026-10-12'];

test('en Mes, mousedown inicia la selección y el arrastre agrega solo las celdas atravesadas', () => {
    const started = paint([{ type: 'down', date: octoberHits[0], inMonth: true, onEvent: false }]);
    const painted = paint([
        { type: 'down', date: octoberHits[0], inMonth: true, onEvent: false },
        ...octoberHits.slice(1).map(date => ({ type: 'enter', date, inMonth: true, onEvent: false })),
    ]);

    assert.equal(started.painting, true);
    assert.deepEqual(started.dates, ['2026-10-05']);
    assert.deepEqual(painted.dates, octoberHits);
    assert.equal(painted.dates.includes('2026-10-07'), false);
    assert.equal(painted.dates.includes('2026-10-09'), false);
    assert.equal(painted.writes, false);
});

test('pasar otra vez por la misma fecha no la duplica', () => {
    const painted = paint([
        { type: 'down', date: '2026-10-05', inMonth: true, onEvent: false },
        { type: 'enter', date: '2026-10-05', inMonth: true, onEvent: false },
        { type: 'enter', date: '2026-10-06', inMonth: true, onEvent: false },
        { type: 'enter', date: '2026-10-05', inMonth: true, onEvent: false },
    ]);

    assert.deepEqual(painted.dates, ['2026-10-05', '2026-10-06']);
});

test('un click simple agrega la fecha y al soltar no abre el modal', () => {
    const released = paint([
        { type: 'down', date: '2026-10-05', inMonth: true, onEvent: false },
        { type: 'up' },
    ]);
    const context = workspace.buildScheduleCreateContext({
        view: 'dayGridMonth',
        date: released.dates[0],
        timed: false,
        dates: released.dates,
        doctorId: '',
        siteId: '',
    });

    assert.equal(released.opensModal, false);
    assert.equal(released.painting, false);
    assert.deepEqual(released.dates, ['2026-10-05']);
    assert.deepEqual(context.dates, ['2026-10-05']);
    assert.equal(context.date, '2026-10-05');
    assert.equal(context.start, '');
    assert.equal(context.end, '');
    assert.equal(context.doctorId, '');
    assert.equal(released.writes, false);
    assert.equal(context.writes, false);
});

test('el modal recibe las fechas pintadas como chips removibles', () => {
    const released = paint([
        { type: 'down', date: octoberHits[0], inMonth: true, onEvent: false },
        ...octoberHits.slice(1).map(date => ({ type: 'enter', date, inMonth: true, onEvent: false })),
        { type: 'up' },
    ]);
    const withoutSixth = workspace.reduceMonthPaint(released, { type: 'remove', date: '2026-10-08' });

    assert.equal(released.opensModal, false);
    assert.equal(released.painting, false);
    assert.deepEqual(released.dates, octoberHits);
    assert.equal(workspace.paintedDateCountLabel(released.dates), '4 fechas seleccionadas');
    assert.deepEqual(workspace.paintedDateChips(released.dates).map(chip => chip.label), ['05/10', '06/10', '08/10', '12/10']);
    assert.deepEqual(withoutSixth.dates, ['2026-10-05', '2026-10-06', '2026-10-12']);
    assert.equal(workspace.paintedDateCountLabel(withoutSixth.dates), '3 fechas seleccionadas');
});

test('las fechas pintadas se agrupan por día de semana en orden operativo', () => {
    const groups = workspace.paintedDateGroups([
        '2026-10-13',
        '2026-10-08',
        '2026-10-06',
        '2026-10-15',
    ]);

    assert.deepEqual(groups.map(group => group.label), ['MAR', 'JUE']);
    assert.deepEqual(groups[0].dates.map(chip => chip.label), ['06/10', '13/10']);
    assert.deepEqual(groups[1].dates.map(chip => chip.label), ['08/10', '15/10']);
    assert.equal(groups[0].plural, 'martes');
});

test('quitar un weekday elimina todo ese grupo y conserva los demás', () => {
    const selected = {
        painting: false,
        dates: ['2026-10-06', '2026-10-08', '2026-10-13', '2026-10-15'],
    };
    const removed = workspace.reduceMonthPaint(selected, { type: 'remove-weekday', weekday: 2 });

    assert.equal(removed.changed, true);
    assert.equal(removed.writes, false);
    assert.deepEqual(removed.dates, ['2026-10-08', '2026-10-15']);
    assert.deepEqual(workspace.paintedDateGroups(removed.dates).map(group => group.label), ['JUE']);
});

test('quitar un weekday inexistente no altera ninguna fecha', () => {
    const dates = ['2026-10-06', '2026-10-08'];
    const untouched = workspace.reduceMonthPaint({ painting: false, dates }, { type: 'remove-weekday', weekday: 1 });

    assert.equal(untouched.changed, false);
    assert.deepEqual(untouched.dates, dates);
});

test('el clic derecho quita solo una fecha ya seleccionada', () => {
    const selected = paint([
        { type: 'down', date: '2026-10-05', inMonth: true, onEvent: false },
        { type: 'enter', date: '2026-10-06', inMonth: true, onEvent: false },
        { type: 'up' },
    ]);
    const removed = workspace.reduceMonthPaint(selected, { type: 'contextmenu', date: '2026-10-06', inMonth: true, onEvent: false });
    const untouched = workspace.reduceMonthPaint(removed, { type: 'contextmenu', date: '2026-10-12', inMonth: true, onEvent: false });

    assert.equal(removed.preventMenu, true);
    assert.deepEqual(removed.dates, ['2026-10-05']);
    assert.equal(untouched.preventMenu, false);
    assert.equal(untouched.changed, false);
    assert.deepEqual(untouched.dates, ['2026-10-05']);
});

test('un bloque existente y un día fuera del mes no entran en la selección', () => {
    const onEvent = paint([{ type: 'down', date: '2026-10-02', inMonth: true, onEvent: true }]);
    const outside = paint([
        { type: 'down', date: '2026-10-05', inMonth: true, onEvent: false },
        { type: 'enter', date: '2026-09-30', inMonth: false, onEvent: false },
        { type: 'enter', date: '2026-11-01', inMonth: false, onEvent: false },
    ]);
    const outsideDown = paint([{ type: 'down', date: '2026-09-30', inMonth: false, onEvent: false }]);

    assert.equal(onEvent.painting, false);
    assert.deepEqual(onEvent.dates, []);
    assert.deepEqual(outside.dates, ['2026-10-05']);
    assert.deepEqual(outsideDown.dates, []);
});

test('Médico Todos sigue obligatorio y varias fechas se envían como dates, no como weekdays', () => {
    const context = workspace.buildScheduleCreateContext({
        view: 'dayGridMonth',
        date: '2026-10-05',
        timed: false,
        dates: octoberHits,
        doctorId: '',
        siteId: '',
    });
    const several = workspace.paintedSaveDecision(octoberHits);
    const one = workspace.paintedSaveDecision(['2026-10-05']);
    const payload = workspace.concreteDatesPayload({
        doctorId: 7,
        siteId: '',
        dates: octoberHits,
        start: '09:00',
        end: '13:00',
        duration: 20,
    });

    assert.equal(context.doctorId, '');
    assert.equal(workspace.DOCTOR_REQUIRED_MESSAGE, 'Selecciona un médico.');
    assert.equal(several.persist, true);
    assert.equal(several.writes, false);
    assert.equal(several.scope, 'dates');
    assert.deepEqual(several.dates, octoberHits);
    assert.equal(payload.scope, 'dates');
    assert.deepEqual(payload.dates, octoberHits);
    assert.equal(Object.prototype.hasOwnProperty.call(payload, 'weekdays'), false);
    assert.equal(payload.site_id, null);
    assert.equal(one.persist, true);
    assert.equal(one.scope, 'single');
    assert.equal(one.fecha_cita, '2026-10-05');
    assert.equal(script.includes('Estas fechas no se pueden guardar todavía.'), false);
    assert.equal(modal.includes('todavía no se guardan'), false);
});

test('confirmar programación resume las fechas y cancelar no escribe', () => {
    const summary = workspace.paintedConfirmationText(
        'Dr. Bruno Salas',
        octoberHits,
        '09:00',
        '13:00',
        20
    );

    assert.match(summary, /Dr\. Bruno Salas/);
    assert.match(summary, /09:00–13:00/);
    assert.match(summary, /20 min por cita/);
    assert.match(summary, /Fechas:/);
    assert.match(summary, /05\/10/);
    assert.match(summary, /06\/10/);
    assert.match(summary, /08\/10/);
    assert.match(summary, /12\/10/);
    assert.match(summary, /Se crearán 4 bloques\./);

    const branch = script.indexOf('activePaintedDates.length > 0');
    const confirmAt = script.indexOf('Confirmar programación', branch);
    const sendAt = script.indexOf("await send(host.dataset.store, 'POST', payload)", confirmAt);
    assert.ok(branch > 0);
    assert.ok(script.slice(confirmAt, sendAt).includes('if (!accepted) return;'));
    assert.equal(workspace.paintedSaveDecision(octoberHits).writes, false);
});

test('un guardado exitoso limpia la selección antes de refrescar el calendario', () => {
    const selected = paint([
        { type: 'down', date: '2026-10-05', inMonth: true, onEvent: false },
        { type: 'enter', date: '2026-10-12', inMonth: true, onEvent: false },
        { type: 'up' },
    ]);
    const cleared = workspace.reduceMonthPaint(selected, { type: 'clear' });
    const success = script.indexOf('clearPaintedSelection();\n                modal && modal.hide();\n                calendar.refetchEvents();');

    assert.deepEqual(selected.dates, ['2026-10-05', '2026-10-12']);
    assert.deepEqual(cleared.dates, []);
    assert.equal(cleared.writes, false);
    assert.ok(success > 0);
    assert.ok(script.indexOf('if (!response.ok || data.code === 0)') < success);
});

test('cancelar el modal conserva la selección y limpiar la borra', () => {
    const selected = paint([
        { type: 'down', date: '2026-10-12', inMonth: true, onEvent: false },
        { type: 'enter', date: '2026-10-13', inMonth: true, onEvent: false },
        { type: 'enter', date: '2026-10-14', inMonth: true, onEvent: false },
        { type: 'enter', date: '2026-10-21', inMonth: true, onEvent: false },
        { type: 'up' },
    ]);
    const again = workspace.reduceMonthPaint(selected, { type: 'down', date: '2026-10-12', inMonth: true, onEvent: false });
    const kept = workspace.monthSelectionAfterDismiss(selected.dates);
    const cleared = workspace.reduceMonthPaint(selected, { type: 'clear' });
    const sameMonth = workspace.monthSelectionOnRangeChange(selected.dates, { start: '2026-09-28', end: '2026-11-01' }, { start: '2026-09-28', end: '2026-11-01' });
    const nextMonth = workspace.monthSelectionOnRangeChange(selected.dates, { start: '2026-09-28', end: '2026-11-01' }, { start: '2026-10-26', end: '2026-12-06' });
    const configured = workspace.configureSelectionContext(selected.dates, { doctorId: '4', siteId: '' });
    const dismiss = script.indexOf('[data-bs-dismiss="modal"]');
    const afterDismiss = script.indexOf("getElementById('schedule-doctor')", dismiss);

    assert.equal(selected.opensModal, false);
    assert.equal(selected.painting, false);
    assert.deepEqual(selected.dates, ['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-21']);
    assert.deepEqual(again.dates, selected.dates);
    assert.equal(again.writes, false);
    assert.deepEqual(kept.dates, selected.dates);
    assert.equal(kept.cleared, false);
    assert.equal(kept.writes, false);
    assert.deepEqual(cleared.dates, []);
    assert.equal(cleared.writes, false);
    assert.equal(sameMonth.cleared, false);
    assert.equal(nextMonth.cleared, true);
    assert.deepEqual(nextMonth.dates, []);
    assert.equal(nextMonth.writes, false);
    assert.equal(configured.opensModal, true);
    assert.equal(configured.writes, false);
    assert.deepEqual(configured.dates, selected.dates);
    assert.equal(configured.start, '');
    assert.equal(configured.end, '');
    assert.equal(configured.doctorId, '4');
    assert.equal(configured.siteId, '');
    assert.equal(index.includes('Configurar selección'), true);
    assert.equal(index.includes('Limpiar selección'), true);
    assert.equal(script.slice(dismiss, afterDismiss).includes('clearPaintedSelection'), false);
    assert.equal(script.includes('if (next.opensModal) openPaintedDates'), false);
    assert.equal(workspace.scheduleInteractionKind('eventClick'), 'edit');
});

function scheduleBlock(doctorId, name, date, start, end, duration, estado) {
    return {
        extendedProps: {
            doctor_id: doctorId,
            doctor_name: name,
            occurrence_date: date,
            start_time: start,
            end_time: end,
            appointment_duration: duration,
            estado: estado || 'ACTIVO',
        },
    };
}

const morningPreset = { hora_inicio: '09:00', hora_fin: '13:00', duracion_cita: 20, doctor_id: '4', doctor_name: 'Dr. Bruno Salas' };

test('un médico concreto agrupa horarios frecuentes y Todos no muestra presets', () => {
    const october = [
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-05', '09:00', '13:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-06', '09:00', '13:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-08', '09:00', '13:00', 30),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-12', '08:00', '12:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-13', '08:00', '12:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-14', '08:00', '12:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-15', '15:00', '18:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-16', '15:00', '18:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-19', '15:00', '18:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-20', '15:00', '18:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-21', '07:00', '09:00', 15),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-07', '09:00', '13:00', 20, 'INACTIVO'),
        scheduleBlock(9, 'Dra. Ana Quispe', '2026-10-05', '10:00', '14:00', 30),
    ];
    const bruno = workspace.frequentSchedulePresets(october, '4');
    const ana = workspace.frequentSchedulePresets(october, '9');
    const everyone = workspace.frequentSchedulePresets(october, '');
    const november = workspace.frequentSchedulePresets([
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-11-02', '11:00', '15:00', 30),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-11-03', '11:00', '15:00', 30),
    ], '4');

    assert.equal(bruno.visible, true);
    assert.equal(bruno.presets.length, 3);
    assert.deepEqual(bruno.presets.map(preset => preset.hora_inicio + '/' + preset.count), ['15:00/4', '08:00/3', '09:00/2']);
    assert.equal(bruno.presets[2].duracion_cita, 20);
    assert.equal(bruno.presets[2].hora_fin, '13:00');
    assert.equal(Object.prototype.hasOwnProperty.call(bruno.presets[0], 'site_id'), false);
    assert.equal(Object.prototype.hasOwnProperty.call(bruno.presets[0], 'schedule_id'), false);
    assert.equal(workspace.presetFrequencyLabel(1), 'usado 1 vez');
    assert.equal(workspace.presetFrequencyLabel(8), 'usado 8 veces');
    assert.equal(ana.presets.length, 1);
    assert.equal(ana.presets[0].hora_inicio, '10:00');
    assert.equal(ana.presets[0].doctor_name, 'Dra. Ana Quispe');
    assert.equal(everyone.visible, false);
    assert.deepEqual(everyone.presets, []);
    assert.equal(november.presets.length, 1);
    assert.equal(november.presets[0].hora_inicio, '11:00');
    assert.equal(november.presets[0].count, 2);
    assert.equal(index.includes('id="schedule-presets"'), true);
    assert.equal(index.includes('Horarios frecuentes'), true);
    assert.equal(script.includes('renderFrequentPresets(events)'), true);
    assert.equal(script.includes('panel.hidden = !state.visible'), true);
});

test('el empate de horarios frecuentes se ordena por la hora de inicio', () => {
    const tied = workspace.frequentSchedulePresets([
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-05', '09:00', '13:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-06', '09:00', '12:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-07', '09:00', '13:00', 20),
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-08', '09:00', '12:00', 20),
    ], '4');

    assert.deepEqual(tied.presets.map(preset => preset.hora_inicio + '-' + preset.hora_fin), ['09:00-12:00', '09:00-13:00']);
});

test('sin horarios activos el panel avisa y no inventa presets', () => {
    const empty = workspace.frequentSchedulePresets([
        scheduleBlock(4, 'Dr. Bruno Salas', '2026-10-05', '09:00', '13:00', 20, 'INACTIVO'),
    ], '4');

    assert.deepEqual(empty.presets, []);
    assert.equal(empty.message, 'Aún no hay horarios frecuentes para este médico en el período visible.');
});

test('arrastrar un horario frecuente prepara el modal y no escribe', () => {
    const selected = ['2026-10-05', '2026-10-06', '2026-10-08', '2026-10-12'];
    const alone = workspace.presetDropDecision({
        preset: morningPreset,
        target: workspace.monthPresetTarget({ date: '2026-10-15', inMonth: true }),
        selectedDates: [],
    });
    const kept = workspace.presetDropDecision({
        preset: morningPreset,
        target: workspace.monthPresetTarget({ date: '2026-10-15', inMonth: true }),
        selectedDates: selected,
    });
    const outside = workspace.presetDropDecision({
        preset: morningPreset,
        target: workspace.monthPresetTarget({ date: '', inMonth: false }),
        selectedDates: selected,
    });
    const otherMonth = workspace.presetDropDecision({
        preset: morningPreset,
        target: workspace.monthPresetTarget({ date: '2026-09-30', inMonth: false }),
        selectedDates: [],
    });
    const preview = workspace.buildScheduleCreateContext({
        view: 'dayGridMonth',
        date: alone.date,
        start: alone.start,
        end: alone.end,
        keepTimes: true,
        duration: alone.duration,
        dates: alone.dates,
        doctorId: '4',
        siteId: '',
    });

    assert.equal(alone.open, true);
    assert.equal(alone.writes, false);
    assert.deepEqual(alone.dates, ['2026-10-15']);
    assert.equal(alone.start, '09:00');
    assert.equal(alone.end, '13:00');
    assert.equal(alone.duration, 20);
    assert.equal(kept.open, true);
    assert.equal(kept.writes, false);
    assert.deepEqual(kept.dates, selected);
    assert.equal(kept.date, '2026-10-05');
    assert.equal(kept.notice, 'Aplicar horario a 4 fechas seleccionadas.');
    assert.equal(outside.open, false);
    assert.deepEqual(outside.dates, selected);
    assert.equal(outside.writes, false);
    assert.equal(otherMonth.open, false);
    assert.equal(otherMonth.writes, false);
    assert.equal(preview.start, '09:00');
    assert.equal(preview.end, '13:00');
    assert.equal(preview.siteId, '');
    assert.equal(preview.writes, false);
    assert.equal(workspace.paintedSaveDecision(kept.dates).scope, 'dates');
    assert.equal(workspace.paintedSaveDecision(alone.dates).scope, 'single');

    const dropAt = script.indexOf("calendarElement.addEventListener('drop'");
    const afterDrop = script.indexOf('function updateSpecialty', dropAt);
    assert.equal(script.slice(dropAt, afterDrop).includes('dataset.store'), false);
    assert.equal(script.includes("eventDrop: info => confirmMove(info, 'drag')"), true);
    assert.equal(script.includes("eventResize: info => confirmMove(info, 'resize')"), true);
    assert.equal(script.includes(workspace.PRESET_DRAG_MIME), true);
});

test('el click de un horario frecuente usa la selección y no inventa una fecha', () => {
    const selected = ['2026-10-05', '2026-10-12'];
    const applied = workspace.presetClickDecision(morningPreset, selected);
    const missing = workspace.presetClickDecision(morningPreset, []);
    const painted = paint([{ type: 'down', date: '2026-10-05', inMonth: true, onEvent: true }]);

    assert.equal(applied.open, true);
    assert.equal(applied.writes, false);
    assert.deepEqual(applied.dates, selected);
    assert.equal(applied.start, '09:00');
    assert.equal(applied.duration, 20);
    assert.equal(missing.open, false);
    assert.equal(missing.writes, false);
    assert.deepEqual(missing.dates, []);
    assert.equal(missing.message, 'Selecciona una fecha del calendario o arrastra este horario a un día.');
    assert.equal(painted.painting, false);
    assert.deepEqual(painted.dates, []);
    assert.equal(workspace.monthPresetTarget({ date: '2026-10-15', inMonth: true }).valid, true);
});

test('ningún listener ajeno enfoca ni abre el time picker', () => {
    assert.equal(script.includes('showPicker'), false);
    assert.equal(script.includes('.focus('), false);
    assert.equal(script.includes("getElementById('schedule-start').click"), false);
    assert.equal(script.includes("getElementById('schedule-end').click"), false);
});
