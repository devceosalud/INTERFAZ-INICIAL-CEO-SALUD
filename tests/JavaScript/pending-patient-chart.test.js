'use strict';

const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const script = fs.readFileSync(
    path.resolve(__dirname, '../../public/js/patients/operational.js'),
    'utf8'
);

test('completar ficha abre el paciente existente y la vista pendiente se recarga al guardar', () => {
    assert.match(script, /data-complete-patient/);
    assert.match(script, /openExisting\(row \|\| \{ dataset: \{ patientId:/);
    assert.match(script, /get\('vista'\) === 'pendientes'/);
    assert.match(script, /location\.reload\(\)/);
    assert.equal(script.includes('window.location'), false);
});
