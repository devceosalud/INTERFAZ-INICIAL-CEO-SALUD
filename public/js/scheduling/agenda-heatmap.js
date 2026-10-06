(function (root, factory) {
    const api = factory(typeof module === 'object' && module.exports ? require('./agenda-click-telemetry') : root.AgendaClickTelemetry);
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaHeatmap = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function (telemetry) {
    'use strict';
    function preview(ctx, width, height, screen, selectedZone) {
        const module = telemetry.modules[screen];
        if (!module) { throw new Error('Módulo desconocido'); }
        Object.entries(module.zones).forEach(function ([key, panel]) {
            const [x, y, w, h, label] = panel;
            ctx.fillStyle = key === selectedZone ? '#ccfbf1' : '#e2e8f0';
            ctx.fillRect(x * width, y * height, w * width, h * height);
            ctx.fillStyle = '#f8fafc';
            ctx.fillRect(x * width + 2, y * height + 30, w * width - 4, h * height - 32);
            ctx.fillStyle = '#334155'; ctx.font = '14px sans-serif';
            ctx.fillText(label, x * width + 8, y * height + 20, w * width - 16);
            ctx.fillStyle = '#cbd5e1';
            for (let line = 48; line < h * height - 10; line += 28) {
                ctx.fillRect(x * width + 12, y * height + line, w * width - 24, 1);
            }
        });
        return module;
    }
    function draw(ctx, width, height, data, selectedZone) {
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = '#f8fafc'; ctx.fillRect(0, 0, width, height);
        const module = preview(ctx, width, height, data.screen, selectedZone);
        const maximum = Math.max(1, ...data.points.map((point) => Number(point.clicks)));
        data.points.forEach(function (point) {
            const panel = module.zones[point.zone];
            if (!panel) { return; }
            const x = (panel[0] + (Number(point.bucket_x) + 0.5) / data.grid_size * panel[2]) * width;
            const y = (panel[1] + (Number(point.bucket_y) + 0.5) / data.grid_size * panel[3]) * height;
            const radius = Math.max(width, height) / data.grid_size * 1.4;
            const gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
            gradient.addColorStop(0, 'rgba(220,38,38,' + (0.25 + Number(point.clicks) / maximum * 0.65) + ')');
            gradient.addColorStop(1, 'rgba(249,115,22,0)');
            ctx.fillStyle = gradient; ctx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
        });
        return maximum;
    }
    return { draw: draw, preview: preview };
}));

if (typeof document !== 'undefined') { document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const root = document.getElementById('agenda-heatmap');
    if (!root) { return; }
    const form = document.getElementById('agenda-heatmap-filters');
    const status = document.getElementById('agenda-heatmap-status');
    const canvas = document.getElementById('agenda-heatmap-canvas');
    const moduleSelect = document.getElementById('heatmap-module');
    const viewSelect = document.getElementById('heatmap-view');
    const zoneSelect = document.getElementById('heatmap-zone');
    function options(select, entries) {
        select.replaceChildren();
        Object.entries(Object.assign({ '': 'Todas' }, entries)).forEach(([key, label]) => {
            const option = document.createElement('option'); option.value = key; option.textContent = label; select.appendChild(option);
        });
    }
    function changeModule() {
        const module = window.AgendaClickTelemetry.modules[moduleSelect.value];
        options(viewSelect, Object.fromEntries(module.views.map((v) => [v, v])));
        options(zoneSelect, Object.fromEntries(Object.entries(module.zones).map(([key, rect]) => [key, rect[4]])));
        window.AgendaHeatmap.draw(canvas.getContext('2d'), canvas.width, canvas.height, { screen: moduleSelect.value, grid_size: 40, points: [] });
    }
    let currentRequest = 0;
    async function load(event) {
        if (event) { event.preventDefault(); }
        const request = ++currentRequest;
        try {
            const response = await fetch(root.dataset.endpoint + '?' + new URLSearchParams(new FormData(form)), { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) { throw new Error(data.message || 'No se pudo consultar la telemetría.'); }
            if (request !== currentRequest) { return; }
            const maximum = window.AgendaHeatmap.draw(canvas.getContext('2d'), canvas.width, canvas.height, data, zoneSelect.value);
            status.textContent = data.total + ' clics · intensidad máxima ' + maximum;
        } catch (error) { status.textContent = error.message || 'Telemetría temporalmente no disponible.'; }
    }
    form.addEventListener('submit', load);
    moduleSelect.addEventListener('change', function () { changeModule(); load(); });
    changeModule();
    if (!form.querySelector('button').disabled) { load(); }
}); }
