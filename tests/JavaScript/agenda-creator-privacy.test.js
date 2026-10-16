const test=require('node:test'),assert=require('node:assert/strict');
const selection=require('../../public/js/scheduling/agenda-selection.js');
test('creator details are absent when server withholds creator; responsible stays distinct',()=>{
 const c={tipo_contexto:'cita_existente',responsable:'COMERCIAL QA'};
 assert.equal(selection.fromContext(c,'Sede QA').creator,null);assert.equal(selection.registrationDescription(c),'');
 assert.equal(selection.fromContext(c,'Sede QA').commercial,'COMERCIAL QA');
});
test('authorized registration description distinguishes creator and assigned commercial',()=>{
 const c={tipo_contexto:'cita_existente',creador:'ADMIN QA',responsable:'COMERCIAL QA'};
 assert.equal(selection.registrationDescription(c),'Registró: ADMIN QA · Comercial responsable: COMERCIAL QA');
 assert.equal(selection.registrationDescription({...c,tipo_contexto:'slot_libre'}),'');
});