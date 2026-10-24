(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.ClinicalOrderFields=api;}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';
    const sideNames={D:'Derecho',I:'Izquierdo',B:'Bilateral'},viewNames={AP:'AP',LAT:'Lateral',OBLIQUE:'Oblicua'};
    function describe(value={}){return [...(value.sides||[]).map(k=>sideNames[k]),...(value.with_load?['Con carga']:[]),...(value.projections||[]).map(k=>viewNames[k]),...(value.finger?['Dedo '+value.finger]:[])].filter(Boolean).join(' · ');}
    function create({doc,data,selected,onDirty,onUpdate}){
        const node=(tag,text,cls)=>{const n=doc.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
        const details=new Map((data.items||[]).map(p=>[p.key,{...(p.details||{})}]));
        const header=node('section',undefined,'clinical-order-header'),patient=data.order_patient||{},identity=node('dl');
        for(const [label,value]of [['Paciente',patient.name||'Sin identificación'],['Médico',patient.physician||'Selección autorizada'],['DNI / H.C.',[patient.document,patient.history].filter(Boolean).join(' · ')||'—'],['Edad',patient.age===null||patient.age===undefined?'No registrada':patient.age+' años'],['Fecha de solicitud',patient.date||'—']]){const group=node('div');group.append(node('dt',label),node('dd',value));identity.append(group);}header.append(identity);
        const diagnosisLabel=node('label','Diagnóstico consignado por el médico'),diagnosis=node('input');diagnosis.type='text';diagnosis.maxLength=500;diagnosis.value=data.clinical_order?.diagnosis||'';diagnosisLabel.append(diagnosis);if(data.can_order_clinical)header.append(diagnosisLabel);
        const notes=node('section',undefined,'clinical-order-notes'),notesLabel=node('label','Observaciones de la solicitud'),observations=node('textarea');observations.rows=3;observations.maxLength=600;observations.value=data.clinical_order?.observations||'';notesLabel.append(observations);const counter=node('small'),privacy=node('small','Datos clínicos de acceso restringido. La selección no registra realización, cobro ni firma o sello gráfico.');notes.append(notesLabel,counter,privacy);if(!data.can_order_clinical)notes.hidden=true;
        const changed=()=>{onDirty();counter.textContent=observations.value.length+' / 600';onUpdate?.();};diagnosis.addEventListener('input',changed);observations.addEventListener('input',changed);counter.textContent=observations.value.length+' / 600';
        function options(key,item){
            const spec=item.specification;if(!spec||(!(spec.sides||[]).length&&!(spec.projections||[]).length&&!spec.load&&!spec.finger))return null;
            const box=node('fieldset',undefined,'clinical-procedure-options');box.append(node('legend','Características de '+item.label));box.hidden=!selected.has(key);
            const value=details.get(key)||{};details.set(key,value);
            function checks(field,choices,names,label){if(!choices.length)return;const group=node('div',undefined,'clinical-option-group');group.append(node('strong',label));for(const choice of choices){const l=node('label',names[choice]),input=node('input');input.type='checkbox';input.checked=(value[field]||[]).includes(choice);input.setAttribute('aria-label',names[choice]+' · '+item.label);input.addEventListener('change',()=>{const set=new Set(value[field]||[]);input.checked?set.add(choice):set.delete(choice);value[field]=[...set].sort();changed();});l.prepend(input);group.append(l);}box.append(group);}
            checks('sides',spec.sides||[],sideNames,'Lateralidad');checks('projections',spec.projections||[],viewNames,'Proyecciones');
            if(spec.load){const l=node('label','Con carga'),input=node('input');input.type='checkbox';input.checked=!!value.with_load;input.setAttribute('aria-label','Con carga · '+item.label);input.addEventListener('change',()=>{value.with_load=input.checked;changed();});l.prepend(input);box.append(l);}
            if(spec.finger){const l=node('label','Dedo (1–5, si corresponde)'),input=node('input');input.type='number';input.min=1;input.max=5;input.step=1;input.value=value.finger||'';input.setAttribute('aria-label','Dedo · '+item.label);input.addEventListener('input',()=>{value.finger=input.value;changed();});l.append(input);box.append(l);}
            return box;
        }
        function values(){const procedure_details={};for(const key of selected){const original=details.get(key);if(!original)continue;const v={};if(original.sides?.length)v.sides=original.sides;if(original.projections?.length)v.projections=original.projections;if(original.with_load)v.with_load=true;if(original.finger!==undefined&&original.finger!=='')v.finger=Number(original.finger);procedure_details[key]=v;}return {expected_patient_id:patient.id,clinical_order_revision:data.clinical_order_revision,...(data.can_order_clinical?{clinical_order:{diagnosis:diagnosis.value.trim(),observations:observations.value.trim()}}:{}),procedure_details};}
        return {header,notes,options,values,description:key=>describe(details.get(key)),validate:()=>diagnosis.reportValidity()&&observations.reportValidity()&&Array.from(doc.querySelectorAll('.clinical-procedure-options:not([hidden]) input')).every(n=>n.reportValidity())};
    }
    return {create,describe};
}));
