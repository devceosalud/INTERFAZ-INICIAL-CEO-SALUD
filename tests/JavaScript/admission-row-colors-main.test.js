const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const js = fs.readFileSync(path.join(root, 'public/js/scheduling/admission-board.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'public/css/scheduling/admission-board.css'), 'utf8');

test('admission rows receive server-computed fill distinct from accent border', () => {
    assert.match(js, /tr\.style\.setProperty\('--ad-status-color',/);
    assert.match(js, /setProperty\('--ad-status-background',admissionBackground\(row\.presentation\)\)/); // Intentional Admisión-only turquoise normalization, behavior covered separately.
});

test('appointment cells, selected rows and hover preserve the computed fill', () => {
    assert.match(css, /\.admission-table tr\[data-appointment-id\]\s*,\s*\.admission-table tr\[data-appointment-id\] > td\s*\{\s*background-color: var\(--ad-status-background, #fff\);\s*\}/);
    for (const name of ['is-selected', 'hover']) {
        const selector = name === 'hover' ? ':hover' : '.is-selected';
        assert.ok(css.includes('.admission-table tr[data-appointment-id]' + selector));
    }
    assert.equal((css.match(/background-color: var\(--ad-status-background, #fff\)/g) || []).length, 3);
});
