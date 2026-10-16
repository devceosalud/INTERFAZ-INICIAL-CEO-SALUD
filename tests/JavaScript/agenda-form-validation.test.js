const test=require('node:test');
const assert=require('node:assert/strict');
const {create}=require('../../public/js/scheduling/agenda-form-validation.js');

function fixture(){
    const attrs=new Map([['aria-describedby','existing-help']]);
    const details={tagName:'DETAILS',open:false,parentElement:null};
    const wrapper={tagName:'LABEL',parentElement:details,children:[],appendChild(node){this.children.push(node);}};
    const field={
        id:'agenda-draft-number',parentElement:wrapper,focused:false,scrolled:false,
        closest(){return wrapper;},getAttribute(key){return attrs.has(key)?attrs.get(key):null;},
        setAttribute(k,v){attrs.set(k,v);},removeAttribute(k){attrs.delete(k);},
        scrollIntoView(){this.scrolled=true;},focus(){this.focused=true;}
    };
    const summary={hidden:true,textContent:'',parentElement:details,focus(){}};
    const doc={createElement(){return {id:'',textContent:'',className:'',remove(){this.removed=true;}};}};
    const api=create({summary,fields:{numero_identidad:field},doc,focusNext:fn=>fn()});
    return {api,field,summary,details,attrs,wrapper};
}
test('422 del paciente enfoca campo, expande panel y conserva descripción previa',()=>{
    const h=fixture();
    h.api.show({'patient.numero_identidad':['DNI incorrecto']},'Error');
    assert.equal(h.details.open,true);
    assert.equal(h.field.focused,true);
    assert.equal(h.field.scrolled,true);
    assert.equal(h.attrs.get('aria-invalid'),'true');
    assert.match(h.attrs.get('aria-describedby'),/existing-help/);
    assert.match(h.attrs.get('aria-describedby'),/agenda-draft-number-validation-error/);
    assert.match(h.summary.textContent,/No se puede guardar todavía/);
    assert.equal(h.wrapper.children[0].textContent,'DNI incorrecto');
    h.api.clear();
    assert.equal(h.attrs.get('aria-invalid'),'false');
    assert.equal(h.attrs.get('aria-describedby'),'existing-help');
    assert.equal(h.summary.hidden,true);
    assert.equal(h.wrapper.children[0].removed,true);
});
test('error general sin mapeo no inventa campos ni expone datos',()=>{
    const h=fixture();
    h.api.show({'payment.unknown':['Revisar']},'Validación de disponibilidad');
    assert.equal(h.attrs.get('aria-invalid'),undefined);
    assert.match(h.summary.textContent,/Validación de disponibilidad/);
    assert.equal(h.wrapper.children.length,0);
});
