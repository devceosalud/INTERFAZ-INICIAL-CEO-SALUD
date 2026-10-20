(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.AgendaRefresh = api;
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';
    // Completion-based scheduling: no overlapping requests, no catch-up burst after hiding.
    function create({run, enabled, onError = () => {}, schedule = setTimeout, clear = clearTimeout, interval = 5000}) {
        let timer = null, stopped = true, running = false;
        function arm(delay) { if (!stopped && !running && timer === null) timer = schedule(tick, delay); }
        async function tick() {
            timer = null;
            if (stopped) return;
            if (!enabled()) { arm(interval); return; }
            running = true;
            try { await run(); } catch(error) { onError(error); } finally { running = false; arm(interval); }
        }
        return {
            start(immediate = false) {
                stopped = false;
                if (immediate && timer !== null) { clear(timer); timer = null; }
                arm(immediate ? 0 : interval);
            },
            stop() { stopped = true; if (timer !== null) clear(timer); timer = null; },
        };
    }
    return {create};
}));
