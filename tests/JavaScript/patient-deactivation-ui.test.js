const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const source = file => fs.readFileSync(path.join(root, file), 'utf8');

test('la desactivación requiere selección activa y permiso de administrador', () => {
    const view = source('resources/views/patients/operational.blade.php');
    const script = source('public/js/patients/operational.js');
    assert.match(view, /data-can-deactivate=/);
    assert.match(view, /data-patient-state=/);
    assert.match(view, /id="patient-deactivate" disabled/);
    assert.match(view, /id="patient-context-deactivate" disabled/);
    assert.match(script, /selectedRow\.dataset\.patientState !== 'ACTIVO'/);
    assert.match(script, /canDeactivate = root\.dataset\.canDeactivate === '1'/);
    assert.match(script, /window\.confirm\(/);
    assert.match(script, /method: 'PATCH'/);
    assert.match(script, /deactivateButton\.addEventListener\('click'/);
    assert.match(script, /contextDeactivate\.addEventListener\('click'/);
});

test('listado ofrece consultar inactivos sin eliminar su historia', () => {
    const view = source('resources/views/patients/operational.blade.php');
    const script = source('public/js/patients/operational.js');
    assert.match(view, /name="estado"/);
    assert.match(view, /value="INACTIVO"/);
    assert.match(view, /value="TODOS"/);
    assert.match(script, /window\.location\.reload\(\)/);
    assert.doesNotMatch(script, /method: 'DELETE'/);
});
