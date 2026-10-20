const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require.resolve('../../public/js/admin/master/doctor-service/doctor-service.js'),'utf8');
function fixture(plugin=true){
 const target=props=>Object.assign({listeners:{},addEventListener(type,fn){this.listeners[type]=fn;},dispatchEvent(event){this.listeners[event.type]?.({target:this});}},props);
 const select=options=>Object.defineProperty(target({options,value:options[0].value}),'selectedOptions',{get(){return this.options.filter(o=>o.value===this.value);}});
 const option=(value,dataset={})=>({value,dataset,disabled:false});
 const doctor=select([option('trauma',{specialty:'1'}),option('mfr',{specialty:'5'})]);
 const service=select([option(''),option('consulta-trauma',{specialties:'[1]',policy:'PROFESSIONAL'}),option('consulta-mfr',{specialties:'[5]',policy:'PROFESSIONAL'}),option('compartido',{specialties:'[1,5]'})]);
 const approved=target({checked:false}),reason={required:false};
 const fields={doctor_id:doctor,service_id:service,specialty_exception_authorized:approved,specialty_exception_reason:reason};
 const form={elements:{namedItem:name=>fields[name]},querySelector:()=>null};
 const modal=target({}),snapshots=[];
 const document={getElementById:id=>({formCreateDoctorService:form,doctorServiceModalCreate:modal})[id],addEventListener(){}};
 const window={CatalogEditor:{bind(){}},addEventListener(type,fn){if(type==='DOMContentLoaded')this.ready=fn;}};
 if(plugin){window.jQuery=element=>({selectpicker(action){assert.equal(action,'refresh');snapshots.push({selected:element.value,disabled:Object.fromEntries(element.options.map(o=>[o.value,o.disabled]))});}});window.jQuery.fn={selectpicker(){}};}
 vm.runInNewContext(source,{window,document,Event:class{constructor(type){this.type=type;}}});window.ready();
 const change=element=>element.dispatchEvent({type:'change'});
 return {doctor,service,approved,reason,modal,snapshots,change};
}
test('Keylinn/MFR regression: initial load and professional changes refresh cached disabled options after recalculation',()=>{
 const f=fixture();assert.equal(f.snapshots.length,1);assert.equal(f.snapshots.at(-1).disabled['consulta-mfr'],true);
 f.doctor.value='mfr';f.change(f.doctor);
 assert.equal(f.snapshots.at(-1).disabled['consulta-mfr'],false);assert.equal(f.snapshots.at(-1).disabled['consulta-trauma'],true);
 f.service.value='consulta-mfr';f.change(f.service);assert.equal(f.snapshots.at(-1).selected,'consulta-mfr');
 f.modal.dispatchEvent({type:'shown.bs.modal'});assert.equal(f.snapshots.at(-1).selected,'consulta-mfr');
 assert.equal(f.snapshots.at(-1).disabled['consulta-trauma'],true);
});
test('exception changes refresh the picker, require a reason and clear only newly incompatible selections',()=>{
 const f=fixture();f.doctor.value='mfr';f.change(f.doctor);f.approved.checked=true;f.change(f.approved);
 assert.equal(f.reason.required,true);assert.equal(f.snapshots.at(-1).disabled['consulta-trauma'],false);
 f.service.value='consulta-trauma';f.approved.checked=false;f.change(f.approved);
 assert.equal(f.reason.required,false);assert.equal(f.snapshots.at(-1).disabled['consulta-trauma'],true);assert.equal(f.snapshots.at(-1).selected,'');
 f.service.value='compartido';f.doctor.value='trauma';f.change(f.doctor);assert.equal(f.snapshots.at(-1).selected,'compartido');
});
test('native selects keep compatibility validation when Bootstrap Select is absent',()=>{
 const f=fixture(false);f.doctor.value='mfr';f.change(f.doctor);assert.equal(f.service.options[2].disabled,false);assert.equal(f.service.options[1].disabled,true);
});
