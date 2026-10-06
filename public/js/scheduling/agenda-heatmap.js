(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaHeatmap = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    function draw(ctx, width, height, data) {
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = '#f8fafc'; ctx.fillRect(0, 0, width, height);
        const maximum = Math.max(1, ...data.points.map((point) => Number(point.clicks)));
        data.points.forEach(function (point) {
            const x = (Number(point.bucket_x) + 0.5) / data.grid_size * width;
            const y = (Number(point.bucket_y) + 0.5) / data.grid_size * height;
            const radius = Math.max(width, height) / data.grid_size * 1.4;
            const gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
            gradient.addColorStop(0, 'rgba(220,38,38,' + (0.25 + Number(point.clicks) / maximum * 0.65) + ')');
            gradient.addColorStop(1, 'rgba(249,115,22,0)');
            ctx.fillStyle = gradient; ctx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
        });
        return maximum;
    }
    return { draw: draw };
}));

if (typeof document !== 'undefined') { document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const root = document.getElementById('agenda-heatmap');
    if (!root) { return; }
    const form = document.getElementById('agenda-heatmap-filters');
    const status = document.getElementById('agenda-heatmap-status');
    const canvas = document.getElementById('agenda-heatmap-canvas');
    async function load(event) {
        if (event) { event.preventDefault(); }
        try {
            const response = await fetch(root.dataset.endpoint + '?' + new URLSearchParams(new FormData(form)), { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) { throw new Error(data.message || 'No se pudo consultar la telemetría.'); }
            const maximum = window.AgendaHeatmap.draw(canvas.getContext('2d'), canvas.width, canvas.height, data);
            status.textContent = data.total + ' clics · intensidad máxima ' + maximum;
        } catch (error) { status.textContent = error.message || 'Telemetría temporalmente no disponible.'; }
    }
    form.addEventListener('submit', load);
    if (!form.querySelector('button').disabled) { load(); }
}); }
