const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const css=fs.readFileSync(path.join(__dirname,'../../public/css/scheduling/admission-board.css'),'utf8');
const js=fs.readFileSync(path.join(__dirname,'../../public/js/scheduling/admission-board.js'),'utf8');
function rule(selector){const block=css.replace(/\/\*[\s\S]*?\*\//g,'').split('}').find(part=>part.slice(part.lastIndexOf('{')+1)&&part.slice(0,part.lastIndexOf('{')).trim().split(',').some(s=>s.trim()===selector));assert.ok(block,selector);return block.slice(block.lastIndexOf('{')+1);}
test('el fondo completo de fila y celdas proviene de la variable del servidor',()=>{
 for(const selector of ['.admission-table tr[data-appointment-id]','.admission-table tr[data-appointment-id] > td'])assert.match(rule(selector),/background-color:\s*var\(--ad-status-background,\s*#fff\)/);
});
test('selección y hover conservan el fondo operativo y distinguen la fila con contorno',()=>{
 const selected=rule('.admission-table tr[data-appointment-id].is-selected'),hover=rule('.admission-table tr[data-appointment-id]:hover');
 for(const block of [selected,hover])assert.match(block,/background-color:\s*var\(--ad-status-background,\s*#fff\)/);
 assert.match(selected,/outline:\s*2px solid var\(--ad-status-color,\s*var\(--ad-brand\)\)/);assert.match(selected,/box-shadow:\s*none/);assert.match(hover,/outline:\s*1px solid/);
});
test('el render conserva el fondo del servidor y el borde de PR67 sin confundirlos',()=>{
    assert.match(js, /setProperty\('--ad-status-background',admissionBackground\(row\.presentation\)\)/); // Intentional Admisión-only turquoise normalization, behavior covered separately.
 assert.match(js,/setProperty\('--ad-status-color',tones\[row\.presentation\?\.fringe\?\.key\]\|\|row\.presentation\?\.fringe\?\.color\|\|'#dce8e9'\)/);
});
