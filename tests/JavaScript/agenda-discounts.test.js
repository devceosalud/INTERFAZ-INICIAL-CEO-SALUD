const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const settle = () => new Promise(r=>setImmediate(r));
const source=fs.readFileSync(require('node:path').join(__dirname,'../../public/js/admissionist/appointment/appointment.js'),'utf8');
const quote=source.slice(source.indexOf('let legacyQuoteVersion'),source.indexOf('//PARA CALCULAR EL SALDO'));
function fixture(){
    const fields=Object.fromEntries(['patient_id','service_id','additional_rate_id','fecha_cita','es_exonerado','discount_type','discount_value','discount_reason',
        'precio_programado','precio_programado_hidden','legacy-quote-status'].map(id=>[id,{value:'',checked:false,textContent:''}]));
    Object.assign(fields.patient_id,{value:'1'});fields.service_id.value='3';fields.fecha_cita.value='2026-10-15';
    const calls=[],pending=[];
    const modal={querySelector:s=>fields[s.slice(1)]};
    const context={document:{getElementById:()=>modal,querySelector:()=>({content:'csrf-fixture'})},
        window:{location:{origin:'http://localhost'}},$:()=>({on(){}}),calcularSaldoCita(){},
        fetch:(url,options)=>{calls.push(JSON.parse(options.body));return new Promise(r=>pending.push(r));}};
    vm.runInNewContext(quote+'\nglobalThis.run=calcularPrecioCita;globalThis.ready=()=>legacyQuoteReady;',context);
    return {fields,calls,context,pending,answer(i,value,ok=true){pending[i]({ok,json:async()=>ok?{precio_programado:value}:{message:'Descuento inválido'}});}};
}
test('heredado envía descuento explícito y nunca calcula el precio autorizado en cliente',async()=>{
    const f=fixture();f.fields.discount_type.value='PERCENT';f.fields.discount_value.value='12.50';f.context.run();
    assert.deepEqual(f.calls[0].discount,{type:'PERCENT',value:'12.50',reason:null});assert.equal(f.context.ready(),false);
    f.answer(0,'87.50');await settle();assert.equal(f.fields.precio_programado_hidden.value,'87.50');assert.equal(f.context.ready(),true);
});
test('cambiar descuento descarta cotizaciones tardías y un error bloquea guardar',async()=>{
    const f=fixture();f.fields.discount_type.value='FIXED';f.fields.discount_value.value='20';f.context.run();
    f.fields.discount_value.value='30';f.context.run();f.answer(1,'70.00');await settle();f.answer(0,'80.00');await settle();
    assert.equal(f.fields.precio_programado_hidden.value,'70.00');f.context.run();f.answer(2,'',false);await settle();
    assert.equal(f.context.ready(),false);assert.equal(f.fields['legacy-quote-status'].textContent,'Descuento inválido');
});
