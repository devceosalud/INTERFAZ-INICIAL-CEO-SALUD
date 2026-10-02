'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
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

test('un DNI nuevo exige 8 dígitos y un heredado inválido se conserva', () => {
    assert.equal(draft.dniMessage({ tipo: 'DNI', numero: '73378485', patientId: '' }), '');
    assert.equal(draft.dniMessage({ tipo: 'DNI', numero: '7337848', patientId: '' }), 'El DNI debe tener exactamente 8 dígitos numéricos.');
    assert.equal(draft.dniMessage({ tipo: 'DNI', numero: '733784850', patientId: '' }), 'El DNI debe tener exactamente 8 dígitos numéricos.');
    assert.equal(draft.dniMessage({ tipo: 'DNI', numero: '1234567890', patientId: '' }), 'El DNI debe tener exactamente 8 dígitos numéricos.');
    assert.equal(draft.dniMessage({ tipo: 'DNI', numero: 'AB123456', patientId: '' }), 'El DNI debe tener exactamente 8 dígitos numéricos.');
    assert.equal(draft.dniMessage({
        tipo: 'DNI',
        numero: '1234567890',
        patientId: '9',
        stored_tipo: 'DNI',
        stored_numero: '1234567890',
    }), '');
    assert.equal(draft.dniMessage({ tipo: 'PASAPORTE', numero: 'P-123', patientId: '' }), '');
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

test('RENIEC parcial no borra fecha, sexo, estado civil ni dirección escritos', () => {
    const opened = draft.open(Object.assign(identity('not_found'), {
        tipo: 'DNI',
        numero: '70000009',
    }), schedule);
    const edited = draft.edit(opened, 'fecha_nacimiento', '1985-05-05');
    const withAddress = draft.edit(edited, 'direccion', 'Dirección escrita');
    const partial = draft.applyReniec(withAddress, {
        status: 'prefilled',
        identity: {
            nombre: 'ANA',
            apellido_paterno: 'LOPEZ',
            apellido_materno: 'DIAZ',
            fecha_nacimiento: null,
            genero: null,
            estado_civil: null,
            direccion: null,
        },
    });

    assert.equal(partial.nombre, 'ANA');
    assert.equal(partial.apellido_paterno, 'LOPEZ');
    assert.equal(partial.apellido_materno, 'DIAZ');
    assert.equal(partial.fecha_nacimiento, '1985-05-05');
    assert.equal(partial.direccion, 'Dirección escrita');
    assert.equal(partial.genero, '');
    assert.equal(partial.estado_civil, '');
    assert.equal(partial.numero, '70000009');
    assert.equal(partial.doctor, schedule.doctor);
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

test('una ficha existente conserva patient_id y la HCE almacenada al editar', () => {
    const opened = draft.openExisting({
        id: 17,
        tipo_identificacion: 'DNI',
        numero_identidad: '73378485',
        historia_clinica: '9000',
        nombre: 'Maria',
        apellido_paterno: 'Perez',
        apellido_materno: 'Demo',
        genero: 'MUJER',
    }, schedule);
    const edited = draft.edit(opened, 'nombre', 'Maria Elena');

    assert.equal(edited.patientId, '17');
    assert.equal(edited.nombre, 'Maria Elena');
    assert.deepEqual(draft.hcePreview(edited), {
        value: '9000',
        message: 'HCE almacenada; no se reformatea.',
        pending: false,
    });
});

test('un paciente nuevo espera la HCE persistida y no envía una calculada por frontend', () => {
    const opened = draft.open(Object.assign(identity('not_found'), {
        tipo: 'DNI',
        numero: '73378485',
    }), schedule);
    const completed = draft.edit(draft.edit(draft.edit(draft.edit(opened,
        'nombre', 'Maria'), 'apellido_paterno', 'Perez'), 'apellido_materno', 'Demo'), 'genero', 'MUJER');
    const payload = draft.toPayload(completed);

    assert.equal(draft.hcePreview(completed, ['DNI']).value, 'Se asignará al guardar');
    assert.equal(payload.numero_identidad, '73378485');
    assert.equal(Object.hasOwn(payload, 'historia_clinica'), false);
    assert.equal(Object.hasOwn(payload, 'commercial_owner_id'), false);
});

test('atribución comercial permanece en contexto sin simular persistencia', () => {
    const opened = draft.open(Object.assign(identity('not_found'), {
        tipo: 'DNI',
        numero: '73378485',
    }), schedule);
    const attributed = draft.edit(opened, 'commercial_owner_id', '31');

    assert.equal(attributed.commercial_owner_id, '31');
    assert.equal(draft.toPayload(attributed).commercial_owner_id, undefined);
    assert.equal(attributed.doctor, schedule.doctor);
    assert.equal(attributed.time, schedule.time);
});

test('guardar sin agendar no asocia ni a un paciente nuevo ni a uno existente', () => {
    const created = draft.saveOutcome(false, false);
    const updated = draft.saveOutcome(false, true);

    assert.equal(created.attach, false);
    assert.equal(updated.attach, false);
    assert.equal(created.message, 'Paciente registrado sin asociarlo al agendamiento actual.');
    assert.equal(updated.message, 'Paciente actualizado sin asociarlo al agendamiento actual.');
});

test('guardar y agendar asocia el patient_id y no anuncia una cita', () => {
    const created = draft.saveOutcome(true, false);
    const updated = draft.saveOutcome(true, true);

    assert.equal(created.attach, true);
    assert.equal(updated.attach, true);
    assert.equal(created.message, updated.message);
    assert.match(created.message, /Registro rápido/);
    assert.match(created.message, /Preparando la cita/);
});

test('Consultar RENIEC de Agenda usa el endpoint propio de la ficha', () => {
    const form = fs.readFileSync(
        path.join(__dirname, '../../resources/views/scheduling/agenda/partials/patient-modal.blade.php'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(__dirname, '../../public/js/scheduling/agenda.js'),
        'utf8'
    );
    const start = script.indexOf("el.draftReniec.addEventListener('click'");
    const listener = script.slice(start, start + 900);

    assert.match(form, /data-reniec-endpoint="\{\{ route\('scheduling\.mvp\.agenda\.reniec-lookup'\) \}\}"/);
    assert.match(listener, /dataset\.reniecEndpoint/);
    assert.equal(listener.includes('dataset.endpoint'), false);
});
