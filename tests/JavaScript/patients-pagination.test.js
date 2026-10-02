'use strict';

const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const css = fs.readFileSync(
    path.resolve(__dirname, '../../public/css/patients/operational.css'),
    'utf8'
);

test('la paginación de pacientes limita el SVG al contenedor', () => {
    assert.match(css, /\.patients-pagination svg\s*\{[^}]*width:\s*12px;[^}]*height:\s*12px;[^}]*max-width:\s*12px;[^}]*max-height:\s*12px;/s);
    assert.equal(/(^|\n)svg\s*\{/.test(css), false);
});
