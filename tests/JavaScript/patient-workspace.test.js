'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');
const workspace = require('../../public/js/patients/patient-workspace.js');

const supportedTypes = ['DNI', 'CARNET EXTRANJERIA', 'PASAPORTE', 'PTP', 'TAM', 'SALVOCONDUCTO'];

test('a new DNI waits for the persisted backend HCE instead of previewing a value', () => {
    assert.deepEqual(workspace.hcePreview('DNI', '73378485', supportedTypes), {
        value: 'Se asignará al guardar',
        pending: true,
        message: 'El backend devolverá la HCE real persistida.',
    });
});

test('all mapped document types defer the final value to the backend', () => {
    supportedTypes.forEach((type) => {
        assert.equal(workspace.hcePreview(type, 'DOC-1', supportedTypes).value, 'Se asignará al guardar');
    });
    assert.equal(workspace.hcePreview('RUC', '20123456789', supportedTypes).value, '—');
});

test('an undocumented patient keeps the final identifier pending', () => {
    assert.deepEqual(workspace.hcePreview('SIN DOCUMENTOS', '', supportedTypes), {
        value: '—',
        pending: true,
        message: 'La regla HCE para pacientes sin documentos está pendiente de negocio.',
    });
});

test('an empty document never invents a suffix', () => {
    assert.equal(workspace.hcePreview('DNI', '', supportedTypes).value, '—');
    assert.equal(workspace.hcePreview('DNI', '', supportedTypes).pending, true);
});

test('Add opens a new record and Back restores list selection and scroll', () => {
    const list = workspace.listState('27', 418);
    const record = workspace.openNew(list);

    assert.equal(record.surface, 'record');
    assert.equal(record.mode, 'new');
    assert.deepEqual(workspace.back(record), list);
});

test('opening patient A then patient B replaces the record context', () => {
    const list = workspace.listState('10', 200);
    const patientA = workspace.openExisting(list, { id: 10, nombre: 'A' });
    const patientB = workspace.openExisting(list, { id: 11, nombre: 'B' });

    assert.equal(patientA.patientId, '10');
    assert.equal(patientB.patientId, '11');
    assert.equal(patientB.patient.nombre, 'B');
});

test('una alta exitosa vuelve al listado y deja la siguiente ficha vacía', () => {
    const blank = workspace.blankForm();
    const created = workspace.afterCreate();

    assert.equal(created.surface, 'list');
    assert.equal(created.patientId, '');
    assert.equal(blank.patientId, '');
    assert.equal(blank.email, '');
    assert.equal(blank.telefono_prefijo, '+51');
    assert.equal(blank.telefono_numero, '');
    assert.equal(blank.registrar_responsable, false);
    assert.equal(blank.responsable_nombres, '');
    assert.equal(blank.notice, '');
});

test('el paciente recién creado queda primero y no duplica su fila', () => {
    const rows = workspace.placeCreated([
        { id: '4', nombre: 'OTRO' },
        { id: '9', nombre: 'REPETIDO' },
    ], {
        id: 9,
        tipo_identificacion: 'DNI',
        numero_identidad: '73378485',
        apellido_paterno: 'PEREZ',
        apellido_materno: 'DEMO',
        nombre: 'MARIA',
        estado: 'ACTIVO',
    });

    assert.equal(rows[0].id, '9');
    assert.equal(rows[0].highlight, true);
    assert.equal(rows[0].registro, '—');
    assert.equal(rows[0].nombre, 'PEREZ DEMO MARIA');
    assert.equal(rows.filter((row) => row.id === '9').length, 1);
    assert.equal(rows[1].id, '4');
});

test('RENIEC is offered only for a new DNI record', () => {
    assert.equal(workspace.canConsultReniec('new', 'DNI'), true);
    assert.equal(workspace.canConsultReniec('existing', 'DNI'), false);
    assert.equal(workspace.canConsultReniec('new', 'PASAPORTE'), false);
});
