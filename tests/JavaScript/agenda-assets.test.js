const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
test('dependencia ausente deja todas las acciones bloqueadas y explica el fallo sin lanzar excepción',()=>{
    const buttons=[{disabled:false},{disabled:false}],notice={hidden:true,textContent:''};let start;
    const document={addEventListener:(name,fn)=>{start=fn;},getElementById:id=>id==='agenda-board'?{querySelectorAll:()=>buttons}:notice};
    vm.runInNewContext(fs.readFileSync('public/js/scheduling/agenda.js','utf8'),{document,window:{}});
    assert.doesNotThrow(()=>start());assert.equal(notice.hidden,false);assert.match(notice.textContent,/No se pudo cargar Agenda completa/);
    assert.ok(buttons.every(button=>button.disabled));
});


test('Agenda keeps dynamic native selectors after the shared theme loads, without changing other modules',()=>{
    let onLoad;const calls=[];
    const jQuery=selector=>({selectpicker:command=>calls.push({selector,command})});jQuery.fn={selectpicker(){}};
    vm.runInNewContext(fs.readFileSync('public/js/scheduling/agenda-shell.js','utf8'),{window:{jQuery,addEventListener:(event,fn)=>{assert.equal(event,'load');onLoad=fn;}}});
    assert.deepEqual(calls,[]);onLoad();
    assert.deepEqual(calls,[{selector:'#agenda-board select',command:'destroy'}]);
});
