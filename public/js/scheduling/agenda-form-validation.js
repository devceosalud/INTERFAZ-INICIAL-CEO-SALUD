(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) root.AgendaFormValidation = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    function create({summary,fields,doc,focusNext} = {}) {
        const documentRoot = doc || document;
        const marked = [];
        const delay = focusNext || (fn => setTimeout(fn, 0));
        function targetFor(key) {
            const name = String(key).replace(/^patient\./, '').replace(/\.\d+(?=\.|$)/g, '');
            const value = fields[key] || fields[name] || fields[name.split('.')[0]];
            return typeof value === 'function' ? value() :
                typeof value === 'string' ? documentRoot.getElementById(value) : value;
        }
        function clear() {
            marked.splice(0).forEach(({field,node,describedBy}) => {
                node.remove();
                field.setAttribute('aria-invalid','false');
                if (describedBy === null) field.removeAttribute('aria-describedby');
                else field.setAttribute('aria-describedby', describedBy);
            });
            if (summary) { summary.hidden=true;summary.textContent=''; }
        }
        function show(errors={},fallback='Revisa los datos e inténtalo nuevamente.') {
            clear();
            let first=null;
            const entries=Object.entries(errors || {});
            entries.forEach(([key,messages]) => {
                const field=targetFor(key);
                const text=String(Array.isArray(messages)?messages[0]:messages || '').trim();
                if (!field || !text || !field.id || marked.some(x=>x.field===field)) return;
                const wrapper=field.closest?.('label') || field.parentElement;
                if (!wrapper) return;
                const node=documentRoot.createElement('span');
                node.className='agenda-input-error';
                node.id=field.id+'-validation-error';
                node.textContent=text;
                const describedBy=field.getAttribute('aria-describedby');
                field.setAttribute('aria-invalid','true');
                field.setAttribute('aria-describedby',[describedBy,node.id].filter(Boolean).join(' '));
                wrapper.appendChild(node);
                marked.push({field,node,describedBy});
                if(!first)first=field;
            });
            if (summary) {
                summary.hidden=false;
                summary.textContent='No se puede guardar todavía. '+
                    (first?'Revisa los campos marcados.':fallback);
            }
            const focus=first||summary;
            if (focus) delay(() => {
                let ancestor=focus.parentElement;
                while(ancestor){if(ancestor.tagName==='DETAILS')ancestor.open=true;ancestor=ancestor.parentElement;}
                focus.scrollIntoView?.({block:'center',behavior:'smooth'});
                focus.focus?.({preventScroll:true});
            });
        }
        return {clear,show};
    }
    return {create};
}));
