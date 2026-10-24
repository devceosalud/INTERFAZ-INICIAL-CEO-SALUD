const test=require('node:test'),assert=require('node:assert/strict');
const {split}=require('../../public/js/scheduling/agenda-appointment-indicators.js');
const {validIds}=require('../../public/js/scheduling/agenda-professional-preferences.js');
const {labelMode}=require('../../public/js/scheduling/agenda-manage-appointment.js');
const form=require('../../public/js/scheduling/agenda-operational-form.js');
test('important clinical characteristics remain directly visible and deduplicate stable keys',()=>{const badges=['POSTOPERATORIO','ADICIONAL','TELECONSULTA','HERIDA','LISTO_REEVALUACION','ADICIONAL'].map(clave=>({clave}));const r=split(badges,2);assert.deepEqual(r.visible.map(b=>b.clave),['LISTO_REEVALUACION','TELECONSULTA','HERIDA','POSTOPERATORIO','ADICIONAL']);assert.deepEqual(r.extra,[]);});
test('critical alerts and postoperative identification never collapse into +N',()=>{const r=split(['POSTOPERATORIO','DESACTIVADA','SALIDA_REGISTRADA','REEMBOLSO_SOLICITADO','CONCILIAR_PAGO','NOTA_TECNICA'].map(clave=>({clave})),1);assert.equal(r.visible.length,5);assert.equal(r.extra[0].clave,'NOTA_TECNICA');});
test('preference restoration only accepts known active professionals and removes duplicates',()=>{assert.deepEqual(validIds([null,2,'2',99,3],new Set(['2','3'])),['2','3']);});
test('attendance and financial presentation remain separate from booking and care states',()=>{assert.equal(labelMode('TELECONSULTA'),'Teleconsulta');assert.equal(labelMode(null),'Presencial');const summary=form.stateSummary({estado_agenda:'CONFIRMADA',estado_cita:'PROGRAMADO',estado_pagado:'PENDIENTE',financial_status:{label:'Exonerada · sin cobro autorizado'}});assert.equal(summary.payment,'Exonerada · sin cobro autorizado');assert.equal(summary.care,'PROGRAMADO');assert.equal(summary.agenda,'CONFIRMADA');});

test('compact economic labels retain distinct zero-cost authorization meanings',()=>{for(const [key,label]of Object.entries({EXEMPT:'Exonerada',CLINICAL_FREE:'Sin costo clínico',AUTHORIZED_DISCOUNT:'Desc. autorizado',VALIDATION:'Validar precio',DUE:'Pendiente',PAID:'Pagado'}))assert.equal(form.financialLabel({financial_status:{key,label:'Detalle completo'}},true),label);assert.equal(form.financialLabel({financial_status:{key:'EXEMPT',label:'Exonerada · sin cobro autorizado'}}),'Exonerada · sin cobro autorizado');});

test('real payment chip is omitted while operational indicators remain',()=>{const r=split([{clave:'PAGO_REAL'},{clave:'TELECONSULTA'}]);assert.deepEqual(r.visible.map(b=>b.clave),['TELECONSULTA']);assert.deepEqual(r.extra,[]);});
