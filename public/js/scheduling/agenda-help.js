(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaHelp = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function start(document) {
        const pop = document.createElement('div');
        pop.id = 'agenda-help-pop';
        pop.className = 'agenda-help-pop';
        pop.setAttribute('role', 'dialog');
        pop.hidden = true;
        const head = document.createElement('div');
        head.className = 'agenda-help-pop__head';
        const title = document.createElement('strong');
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'agenda-help-pop__close';
        close.textContent = '×';
        close.setAttribute('aria-label', 'Cerrar');
        const body = document.createElement('p');
        head.append(title, close);
        pop.append(head, body);
        document.body.append(pop);
        let opener = null;

        function shut() {
            pop.hidden = true;
            if (opener) {
                opener.setAttribute('aria-expanded', 'false');
                const button = opener;
                opener = null;
                if (button.focus) { button.focus(); }
            }
        }

        function open(button) {
            if (opener && opener !== button) { opener.setAttribute('aria-expanded', 'false'); }
            if (opener === button && !pop.hidden) { shut(); return; }
            opener = button;
            title.textContent = button.getAttribute('data-help-title') || 'Ayuda';
            body.textContent = button.getAttribute('data-help-text') || '';
            button.setAttribute('aria-expanded', 'true');
            pop.hidden = false;
            const rect = button.getBoundingClientRect ? button.getBoundingClientRect() : { left: 8, bottom: 8, top: 8, width: 26 };
            const width = 260;
            const view = typeof window !== 'undefined' ? window.innerWidth : 320;
            const left = Math.max(8, Math.min(rect.left, (view || 320) - width - 8));
            pop.style.left = left + 'px';
            pop.style.top = (rect.bottom + 6) + 'px';
            close.focus();
        }

        document.querySelectorAll('.agenda-help').forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-controls', 'agenda-help-pop');
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                open(button);
            });
        });
        close.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            shut();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !pop.hidden) { shut(); }
        });
        document.addEventListener('click', function (event) {
            if (pop.hidden) { return; }
            const target = event.target;
            if (target === pop || (pop.contains && pop.contains(target)) || target === opener) { return; }
            shut();
        });
        return { open: open, close: shut, pop: pop };
    }

    return { start: start };
}));
