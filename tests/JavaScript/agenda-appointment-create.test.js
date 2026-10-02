const test = require('node:test');
const assert = require('node:assert/strict');
const create = require('../../public/js/scheduling/agenda-appointment-create.js');

function slot(overrides = {}) {
    return Object.assign({
        tipo_contexto: 'slot_libre',
        seleccionable: true,
        doctor_id: 7,
        site_id: 2,
        fecha: '2026-10-05',
        hora_inicio: '10:20',
        minutos: 10,
    }, overrides);
}

test('el payload usa ids y la duración exacta del slot real', () => {
    assert.deepEqual(create.payload(slot(), '11', '4', '9'), {
        patient_id: 11,
        doctor_id: 7,
        service_id: 4,
        site_id: 2,
        fecha_cita: '2026-10-05',
        hora_cita: '10:20',
        duracion_cita: 10,
        responsible_user_id: 9,
    });
});

test('un horario heredado conserva site_id null y responsable opcional', () => {
    const payload = create.payload(slot({ site_id: null, minutos: 30 }), 11, 4, '');

    assert.equal(payload.site_id, null);
    assert.equal(payload.responsible_user_id, null);
    assert.equal(payload.duracion_cita, 30);
});

test('no permite guardar una cita existente ni un intervalo fuera de horario', () => {
    assert.throws(
        () => create.payload(slot({ tipo_contexto: 'cita_existente', seleccionable: false }), 11, 4, ''),
        /intervalo disponible/
    );
    assert.throws(
        () => create.payload(slot({ tipo_contexto: 'fuera_horario', seleccionable: false }), 11, 4, ''),
        /intervalo disponible/
    );
});

test('paciente y servicio son requisitos explícitos', () => {
    assert.throws(() => create.payload(slot(), '', 4, ''), /paciente/);
    assert.throws(() => create.payload(slot(), 11, '', ''), /servicio/);
});

test('los errores 409 y 422 conservan el mensaje del backend', () => {
    assert.equal(
        create.validationMessage({ message: 'El horario seleccionado ya no se encuentra disponible.' }),
        'El horario seleccionado ya no se encuentra disponible.'
    );
    assert.equal(
        create.validationMessage({ errors: { service_id: ['Seleccione un servicio válido.'] } }),
        'Seleccione un servicio válido.'
    );
});
