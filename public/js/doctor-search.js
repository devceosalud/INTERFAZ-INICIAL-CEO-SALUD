(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else api.start(root,document);}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';
    const identity=/(^|[\s_.:\[\]-])(doctor|medico|profesional|physician|professional)([\s_.:\[\]-]|$)/i;
    function isDoctorSelect(select){if(select.tagName!=='SELECT')return false;return select.hasAttribute('data-doctor-search')||identity.test([select.id,select.name,...Array.from(select.attributes||[]).filter(a=>a.name.startsWith('wire:model')).map(a=>a.value)].join(' '));}
    function start(root,doc){
        let scheduled=false,loading=false,started=false;const wired=new WeakSet(),tabIndices=new WeakMap();
        function schedule(){if(scheduled||!started)return;scheduled=true;root.setTimeout(()=>{scheduled=false;scan();},0);}
        function enhance(select){const $=root.jQuery,control=$(select);if(wired.has(select)&&control.data('selectpicker')&&select.parentElement.classList.contains('ceo-doctor-search'))return;
            if(control.data('selectpicker'))control.selectpicker('destroy');
            if(!tabIndices.has(select))tabIndices.set(select,select.getAttribute('tabindex'));const originalTabIndex=tabIndices.get(select);const label=select.labels?.[0]?.cloneNode(true);label?.querySelectorAll('select,.bootstrap-select').forEach(n=>n.remove());const title=select.getAttribute('aria-label')||label?.textContent.trim()||'Médico / profesional';
            const sync=()=>{const b=select.parentElement.querySelector('button.dropdown-toggle');if(b){b.setAttribute('aria-label',title);b.tabIndex=select.disabled?-1:originalTabIndex===null||originalTabIndex==='-98'?0:Number(originalTabIndex);}};
            const style=root.getComputedStyle(select),appearance={};for(const key of ['fontFamily','fontSize','fontWeight','lineHeight','color','backgroundColor','border','borderRadius','padding','height'])appearance[key]=style[key];
            select.setAttribute('data-live-search','true');select.setAttribute('data-live-search-normalize','true');
            control.selectpicker({liveSearch:true,liveSearchNormalize:true,liveSearchPlaceholder:'Buscar médico por nombre',noneResultsText:'Sin médicos coincidentes',selectOnTab:true,size:8,dropupAuto:false,width:'100%',style:'ceo-doctor-search-button'});
            select.setAttribute('aria-hidden','true');select.tabIndex=-1;const wrapper=select.parentElement;wrapper.classList.add('ceo-doctor-search');const button=wrapper.querySelector('button.dropdown-toggle');if(button)Object.assign(button.style,appearance);
            sync();
            const search=wrapper.querySelector('.bs-searchbox input');if(search)search.setAttribute('aria-label','Buscar médico por nombre');
            if(!wired.has(select)){
                const observer=new root.MutationObserver(()=>{root.setTimeout(()=>{if(control.data('selectpicker')){control.selectpicker('refresh');sync();}else schedule();},0);});
                observer.observe(select,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','hidden','selected','label']});
                select.addEventListener('change',()=>root.setTimeout(()=>{if(control.data('selectpicker')){control.selectpicker('render');sync();}},0));
                select.form?.addEventListener('reset',()=>root.setTimeout(()=>{if(control.data('selectpicker')){control.selectpicker('refresh');sync();}},0));wired.add(select);
            }
        }
        function scan(){const targets=Array.from(doc.querySelectorAll('select')).filter(isDoctorSelect);if(!targets.length)return;
            if(!root.jQuery?.fn?.selectpicker){
                const source=doc.querySelector('script[data-doctor-search-vendor]')?.dataset.doctorSearchVendor;if(!source||!root.jQuery||loading)return;loading=true;const script=doc.createElement('script');script.src=source;script.onload=()=>{loading=false;schedule();};script.onerror=()=>{loading=false;};doc.head.append(script);return;
            }
            targets.forEach(enhance);
        }
        root.addEventListener('load',()=>{started=true;schedule();const observer=new root.MutationObserver(records=>{if(records.some(r=>Array.from(r.addedNodes).some(n=>n.nodeType===1&&(n.matches?.('select')||n.querySelector?.('select')))))schedule();});observer.observe(doc.body,{childList:true,subtree:true});});
        return {refresh:schedule};
    }
    return {isDoctorSelect,start};
}));
