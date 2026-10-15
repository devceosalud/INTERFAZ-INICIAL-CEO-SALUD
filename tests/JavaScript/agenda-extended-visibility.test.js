const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const agenda = fs.readFileSync(path.resolve(__dirname, '../../public/js/scheduling/agenda.js'), 'utf8');
const presenter = fs.readFileSync(path.resolve(__dirname, '../../app/Services/Scheduling/AgendaBoardPresenter.php'), 'utf8');
const feed = fs.readFileSync(path.resolve(__dirname, '../../app/Services/Scheduling/OperationalAgendaAppointmentService.php'), 'utf8');
const from = agenda.indexOf('    function extendedDurationText(context) {');
const until = agenda.indexOf('    function dayEntry(row, item) {', from);

test('etiqueta de cita extendida solo se activa para duration_slots=2', () => {
    assert.ok(from >= 0 && until > from);
    const sandbox = {};
    vm.runInNewContext(agenda.slice(from, until) + '\nglobalThis.formatExtended = extendedDurationText;', sandbox);
    assert.equal(sandbox.formatExtended({duration_slots:2,minutos:40}), 'EXTENDIDA · 40 min · 2 intervalos');
    assert.equal(sandbox.formatExtended({duration_slots:1,minutos:20}), '');
    assert.equal(sandbox.formatExtended({duration_slots:null,minutos:40}), '');
});

test('Vista Día, Semana, detalle y continuación marcan explícitamente la cita extendida', () => {
    assert.match(agenda, /context\.estado_agenda === 'PENDIENTE_CONFIRMACION' \? 'RESERVA'/);
    assert.match(agenda, /extended \? ' · EXTENDIDA' : ''/);
    assert.match(agenda, /extended \? 'cita extendida' : 'cita'/);
    assert.match(agenda, /booking\.textContent = summary\.agenda \+ ' · ' \+ summary\.payment \+ \(extended/);
    assert.match(agenda, /Number\(context\.duration_slots\) === 2 \? ' · 2 intervalos' : ''/);
});

test('Feed autenticado trae duration_slots y minutos persistidos (solo metadatos de duración)', () => {
    assert.match(feed, /'duration_slots'/);
    assert.match(presenter, /'duration_slots' => \(int\) \(\$appointment->duration_slots \?: 1\)/);
    assert.match(presenter, /'hora_fin' => \$end/);
});
