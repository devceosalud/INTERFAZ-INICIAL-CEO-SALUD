'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const lookup = require('../../public/js/scheduling/agenda-patient-lookup.js');

const schedule = {
    doctor: 'Dr. Bruno Salas',
    specialty: 'Medicina General',
    date: 'mié, 30 set. 2026',
    time: '09:20 – 09:40',
};

test('identidad cargada desde cita coincide con documento y una edición la invalida', () => {
    const current = lookup.present({ status: 'found', patient: { patient_id: 42, tipo_identificacion: 'PASAPORTE', numero_identidad: 'QA-42', nombre: 'QA LOCAL' } });
    assert.equal(lookup.matchesDocument(current, 'PASAPORTE', 'QA-42'), true);
    for (const [type, number] of [['PASAPORTE', ''], ['PASAPORTE', 'QA-43'], ['DNI', 'QA-42']]) {
        const next = lookup.edited(current, type, number);
        assert.equal(next.patientId, '');
        assert.equal(lookup.matchesDocument(next, type, number), false);
    }
    assert.equal(lookup.matchesDocument(lookup.blank(), 'DNI', ''), false);
});

function foundPayload(id) {
    return {
        status: 'found',
        patient: {
            patient_id: id,
            historia_clinica: '9000',
            tipo_identificacion: 'DNI',
            numero_identidad: '70000001',
            nombre: 'Maria',
            apellido_paterno: 'Perez',
            apellido_materno: 'Demo',
            estado: 'ACTIVO',
        },
    };
}

test('cambiar el documento limpia el patient_id anterior', () => {
    const found = lookup.present(foundPayload(15), schedule);
    const changed = lookup.edited(found, 'DNI', '70000002');

    assert.equal(found.patientId, '15');
    assert.equal(changed.patientId, '');
    assert.equal(changed.status, '');
    assert.equal(changed.numero, '70000002');
});

test('un paciente encontrado conserva el patient_id devuelto', () => {
    const found = lookup.present(foundPayload(15), schedule);

    assert.equal(found.status, 'found');
    assert.equal(found.patientId, '15');
    assert.equal(found.name, 'Maria Perez Demo');
    assert.equal(found.clinicalRecord, '9000');
    assert.equal(found.message, 'Paciente registrado');
    assert.equal(found.showRegister, false);
    assert.equal(lookup.edited(found, 'DNI', '70000001').patientId, '15');
});

test('la búsqueda no reemplaza médico, fecha ni hora ya elegidos', () => {
    const found = lookup.present(foundPayload(15), schedule);
    const missing = lookup.present({ status: 'not_found' }, schedule);
    const inactive = lookup.present({
        status: 'inactive',
        patient: foundPayload(9).patient,
    }, schedule);
    const conflict = lookup.present({ status: 'document_conflict' }, schedule);

    [found, missing, inactive, conflict].forEach((result) => {
        assert.equal(result.doctor, 'Dr. Bruno Salas');
        assert.equal(result.specialty, 'Medicina General');
        assert.equal(result.date, 'mié, 30 set. 2026');
        assert.equal(result.time, '09:20 – 09:40');
    });

    assert.equal(missing.message, 'Paciente no registrado');
    assert.equal(missing.patientId, '');
    assert.equal(missing.showRegister, true);
    assert.equal(inactive.patientId, '');
    assert.equal(inactive.message, 'Paciente registrado, actualmente inactivo.');
    assert.equal(conflict.patientId, '');
    assert.equal(conflict.showRegister, false);
    assert.match(conflict.message, /otro tipo de identificación/);
});
