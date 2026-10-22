const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const script=fs.readFileSync(require('node:path').join(__dirname,'../../public/js/admin/master/user/doctor-link.js'),'utf8');

function runWithThemeEnhancement() {
    const events={},onSelect={},removed=[];
    const wrapper={parentNode:{insertBefore(node,near){assert.equal(node,doctor);assert.equal(near,wrapper);}},remove(){removed.push('wrapper');}};
    const orphan={remove(){removed.push('orphan');}};
    let instance=true;
    const doctor={value:'1',isConnected:true,
        addEventListener(name,fn){onSelect[name]=fn;},
        closest(sel){assert.equal(sel,'.bootstrap-select');return instance?wrapper:null;},
        classList:{remove(name){removed.push(name);}}};
    const form={dataset:{current:''},elements:{doctor_id:doctor},
        contains(node){return node===wrapper;},
        querySelectorAll(){return [orphan];},
        addEventListener(){}};
    const jq=node=>{assert.equal(node,doctor);return {
        data(key){assert.equal(key,'selectpicker');return instance?{}:null;},
        selectpicker(action){assert.equal(action,'destroy');instance=false;removed.push('destroy');}
    };};
    jq.fn={selectpicker(){}};
    const window={jQuery:jq,document:{querySelector(sel){
        return sel==='#user-doctor-link'?form:sel==='#user-doctor-link-status'?{}:null;
    }},addEventListener(name,callback){events[name]=callback;}};
    vm.runInNewContext(script,{window,globalThis:window});
    return {doctor,events,onSelect,removed};
}

test('link form keeps unlinked placeholder and removes duplicate Bootstrap select',()=>{
    const x=runWithThemeEnhancement();
    assert.equal(x.doctor.value,'');
    assert.ok(x.removed.includes('destroy'));
    assert.ok(x.removed.includes('orphan'));
    assert.ok(x.events.load);
});

test('late theme event never overwrites physician manually selected by administrator',()=>{
    const x=runWithThemeEnhancement();
    x.doctor.value='1';
    x.onSelect.change();
    x.events.load();
    assert.equal(x.doctor.value,'1');
});
