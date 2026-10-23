const {test}=require('node:test'),assert=require('node:assert/strict');
const {admissionBackground}=require('../../public/js/scheduling/admission-board');
const {fold}=require('../../public/js/scheduling/procedure-picker');
test('Admisión unifies only the approved turquoise without changing other colors',()=>{assert.equal(admissionBackground({fringe:{color:'#99f6e4'}}),'#74dccb');assert.equal(admissionBackground({fondo:'#74dccb'}),'#74dccb');for(const color of ['#ffc28a','#fecaca','#fff2a6','#ffffff'])assert.equal(admissionBackground({fringe:{color}}),color);});
test('procedure search ignores case and accents',()=>{assert.equal(fold('Radiografía de RODILLA'),'radiografia de rodilla');assert.equal(fold('Resonancia magnética'),'resonancia magnetica');});
