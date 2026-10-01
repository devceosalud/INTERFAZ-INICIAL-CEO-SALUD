'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const lookup = require('../../public/js/scheduling/agenda-patient-lookup.js');
const draft = require('../../public/js/scheduling/agenda-patient-draft.js');

const schedule = {
    doctor: 'Dr. Bruno Salas',
    specialty: 'Medicina General',
    date: 'mié, 30 set. 2026',
    time: '09:20 – 09:40',
    site: 'Sede Central',
};

function identity(status) {
    if (status === 'found' || status === 'inactive') {
        return lookup.present({
            status: status,
            patient: {
                patient_id: 4,
                historia_clinica: '9000',
                tipo_identificacion: 'DNI',
                numero_identidad: '70000001',
                nombre: 'Maria',
                apellido_paterno: 'Perez',
                apellido_materno: 'Demo',
                estado: status === 'found' ? 'ACTIVO' : 'INACTIVO',
            },
        }, schedule);
    }

    return lookup.present({ status: status }, schedule);
}

test('registrar paciente solo aparece cuando el documento no existe', () => {
    assert.equal(identity('not_found').showRegister, true);
    assert.equal(identity('found').showRegister, false);
    assert.equal(identity('inactive').showRegister, false);
    assert.equal(identity('document_conflict').showRegister, false);
    assert.equal(draft.canOpen(identity('found')), false);
    assert.equal(draft.canOpen(identity('inactive')), false);
    assert.equal(draft.canOpen(identity('document_conflict')), false);
});

test('un DNI no registrado abre el formulario y ofrece RENIEC', () => {
    const opened = draft.open(Object.assign(identity('not_found'), {
        tipo: 'DNI',
        numero: '70000009',
    }), schedule);

    assert.equal(opened.open, true);
    assert.equal(opened.reniecOffered, true);
    assert.equal(opened.tipo, 'DNI');
    assert.equal(opened.numero, '70000009');
    assert.equal(opened.patientId, '');
    assert.equal(opened.doctor, schedule.doctor);
    assert.equal(opened.time, schedule.time);
});

test('carné y pasaporte abren el alta manual sin RENIEC', () => {
    ['CARNET EXTRANJERIA', 'PASAPORTE', 'RUC', 'SIN DOCUMENTOS'].forEach((tipo) => {
        const opened = draft.open(Object.assign(identity('not_found'), {
            tipo: tipo,
            numero: 'AB-12',
        }), schedule);

        assert.equal(opened.open, true);
        assert.equal(opened.reniecOffered, false);
        assert.equal(opened.doctor, schedule.doctor);
        assert.equal(opened.date, schedule.date);
    });
});

test('RENIEC precarga la identidad y no pisa lo escrito a mano', () => {
    const opened = draft.open(Object.assign(identity('not_found'), {
        tipo: 'DNI',
        numero: '70000009',
    }), schedule);
    const edited = draft.edit(opened, 'nombre', 'Nombre manual');
    const filled = draft.applyReniec(edited, {
        status: 'prefilled',
        identity: {
            nombre: 'Persona',
            apellido_paterno: 'Prueba',
            apellido_materno: 'Segura',
            fecha_nacimiento: '1990-01-01',
            genero: 'MUJER',
            estado_civil: 'SOLTERO',
            direccion: 'Dirección privada de prueba',
        },
    });

    assert.equal(filled.nombre, 'Nombre manual');
    assert.equal(filled.apellido_paterno, 'Prueba');
    assert.equal(filled.fecha_nacimiento, '1990-01-01');
    assert.equal(filled.genero, 'MUJER');
    assert.equal(filled.patientId, '');
    assert.equal(filled.doctor, schedule.doctor);
    assert.match(filled.message, /Revise y complete/);
});

test('un fallo de RENIEC conserva el formulario y el horario', () => {
    const opened = draft.open(Object.assign(identity('not_found'), {
        tipo: 'DNI',
        numero: '70000009',
    }), schedule);
    const edited = draft.edit(opened, 'telefono', '999111222');
    const failed = draft.applyReniec(edited, { status: 'unavailable' });

    assert.equal(failed.open, true);
    assert.equal(failed.telefono, '999111222');
    assert.equal(failed.numero, '70000009');
    assert.equal(failed.time, schedule.time);
    assert.equal(failed.date, schedule.date);
    assert.match(failed.message, /registro manual/);
});

test('cambiar el documento descarta el borrador y el patient_id', () => {
    const found = lookup.present({
        status: 'found',
        patient: {
            patient_id: 4,
            historia_clinica: '9000',
            tipo_identificacion: 'DNI',
            numero_identidad: '70000001',
            nombre: 'Maria',
            apellido_paterno: 'Perez',
            apellido_materno: 'Demo',
            estado: 'ACTIVO',
        },
    }, schedule);
    const changed = lookup.edited(found, 'DNI', '70000002');
    const dropped = draft.discard(schedule);

    assert.equal(changed.patientId, '');
    assert.equal(changed.status, '');
    assert.equal(dropped.open, false);
    assert.equal(dropped.patientId, '');
    assert.equal(dropped.doctor, schedule.doctor);
    assert.equal(dropped.date, schedule.date);
    assert.equal(dropped.time, schedule.time);
});

test('cancelar el registro conserva médico, fecha y hora', () => {
    const opened = draft.open(Object.assign(identity('not_found'), {
        tipo: 'DNI',
        numero: '70000009',
    }), schedule);
    const cancelled = draft.cancel(opened, schedule);

    assert.equal(cancelled.open, false);
    assert.equal(cancelled.patientId, '');
    assert.equal(cancelled.doctor, 'Dr. Bruno Salas');
    assert.equal(cancelled.date, 'mié, 30 set. 2026');
    assert.equal(cancelled.time, '09:20 – 09:40');
    assert.equal(cancelled.site, 'Sede Central');
});
