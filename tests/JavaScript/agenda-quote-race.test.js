'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const agenda = fs.readFileSync(path.resolve(__dirname, '../../public/js/scheduling/agenda.js'), 'utf8');
const workspace = fs.readFileSync(path.resolve(__dirname, '../../public/js/scheduling/agenda-operational-workspace.js'), 'utf8');
const view = fs.readFileSync(path.resolve(__dirname, '../../resources/views/scheduling/agenda/partials/operational-registration.blade.php'), 'utf8');

function quoteSource() {
    const start = workspace.indexOf('        async function refreshQuote(patientId) {');
    const end = workspace.indexOf("        [indicationInput,referredInput,referralOrigin].forEach(", start);
    assert.ok(start > 0 && end > start, 'Expected quote handler was not found');
    return workspace.slice(start, end);
}
function createQuoteHarness(configOverrides = {}) {
    const state = {
        ui: { price:{ value:'',placeholder:'' }, paid:{ value:'' } },
        quoteStatus: {hidden:true,textContent:''}, quoteRetry:{hidden:true,handler:null,
            addEventListener(event,fn){if(event==='click') this.handler=fn;}},
        catalogPrice:{value:''}, automaticFree:false, quoteVersion:0,activeQuoteRequest:null,
        quote:0,balance:0,quoteReady:true, rateSelect:{value:''},
        config:{
            quoteData:()=>({doctor_id:9,service_id:3,fecha_cita:'2026-10-17'}),
            quoteEndpoint:'/scheduling-mvp/agenda/quote',
            context:()=>({tipo_contexto:'slot_libre'}),
            changed:()=>{},...configOverrides
        },
        model:{cents: n=>Math.round(Number(n)*100)},
        medicalPriceInput:()=>({}), discountInput:()=>null,
        json:(method,data)=>({method,body:JSON.stringify(data)}),
        calculate:()=>{}, request:()=>Promise.reject(new Error('not configured')),
        AbortController, setTimeout,clearTimeout,
    };
    vm.runInNewContext(quoteSource()+'\nglobalThis.runQuote = refreshQuote;',state);
    return state;
}

test('Seleccionar un intervalo espera el reset antes de solicitar el precio', () => {
    const start=agenda.indexOf('    async function selectInterval(');
    const end=agenda.indexOf('    function ',start+40);
    const code=agenda.slice(start,end);
    const reset=code.indexOf('await operationalWorkspace.selectionChanged(context)');
    const services=code.indexOf('configureServices(context)');
    assert.ok(reset>=0 && services>reset,'Reset must finish before service quote starts');
    assert.match(view,/id="agenda-op-quote-retry"/);
});

test('Cotización exitosa se muestra y habilita solo tras respuesta valida del servidor', async () => {
    const h=createQuoteHarness();
    h.request=async (_url,_options)=>({precio_programado:'150.00',catalog_price:'150.00',pricing_rule:'NORMAL'});
    await h.runQuote();
    assert.equal(h.quoteReady,true);
    assert.equal(h.ui.price.value,'150.00');
    assert.equal(h.quoteStatus.hidden,true);
    assert.equal(h.quoteRetry.hidden,true);
});

test('Error del servidor muestra motivo y permite reintentar sin precio falso', async () => {
    const h=createQuoteHarness();
    h.request=async ()=>{throw new Error('Tarifa no vigente');};
    await h.runQuote();
    assert.equal(h.quoteReady,false);
    assert.equal(h.ui.price.value,'');
    assert.match(h.quoteStatus.textContent,/Tarifa no vigente/);
    assert.equal(h.quoteRetry.hidden,false);
    h.request=async ()=>({precio_programado:'80.00',pricing_rule:'NORMAL'});
    await h.quoteRetry.handler();
    assert.equal(h.quoteReady,true);
    assert.equal(h.ui.price.value,'80.00');
});

test('Respuesta atrasada no puede sobrescribir la cotizacion mas reciente', async () => {
    const h=createQuoteHarness();
    let firstResolve;
    h.request=()=>new Promise(resolve=>{firstResolve=resolve;});
    const first=h.runQuote();
    h.request=async()=>({precio_programado:'200.00',pricing_rule:'NORMAL'});
    await h.runQuote();
    firstResolve({precio_programado:'999.00',pricing_rule:'NORMAL'});
    await first;
    assert.equal(h.quoteReady,true);
    assert.equal(h.ui.price.value,'200.00');
});

test('Timeout termina el estado Consultando y no habilita guardado', async () => {
    const h=createQuoteHarness();
    h.setTimeout=callback=>{queueMicrotask(callback);return 1;};
    h.clearTimeout=()=>{};
    h.request=(_url,{signal})=>new Promise((_resolve,reject)=>{
        signal.addEventListener('abort',()=>{
            const err=new Error('aborted');err.name='AbortError';reject(err);
        },{once:true});
    });
    await h.runQuote();
    assert.equal(h.quoteReady,false);
    assert.match(h.quoteStatus.textContent,/tardó demasiado/);
    assert.equal(h.quoteRetry.hidden,false);
});
