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
    function overlay(ctx, width, height, data, rectangles) {
        ctx.clearRect(0, 0, width, height);
        let precise = 0, approximate = 0, outside = 0;
        const maximum = Math.max(1, ...data.points.map(p => Number(p.clicks)));
        for (const point of data.points) {
            const rect = rectangles[point.zone || 'screen']; if (!rect) continue;
            let x, y, matched = false;
            if (data.layout_version === 3) {
                x = point.x; y = point.y;
                const captured = point.zone_rect;
                matched = captured && ['left', 'top', 'width', 'height'].every(key => Math.abs(captured[key] - rect[key]) <= 4);
            } else {
                x = rect.left + (point.bucket_x + 0.5) / data.grid_size * rect.width;
                y = rect.top + (point.bucket_y + 0.5) / data.grid_size * rect.height;
            }
            if (x < 0 || y < 0 || x > width || y > height) { outside += point.clicks; continue; }
            if (matched) precise += point.clicks; else approximate += point.clicks;
            const radius = 18, color = matched ? '37,99,235' : '234,88,12';
            const gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
            gradient.addColorStop(0, 'rgba(' + color + ',' + (0.3 + point.clicks / maximum * 0.6) + ')');
            gradient.addColorStop(1, 'rgba(' + color + ',0)');
            ctx.fillStyle = gradient; ctx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
        }
        return { precise, approximate, outside };
    }
    return { draw: draw, preview: preview, overlay };
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
    const precisionSelect = document.getElementById('heatmap-precision');
    const profileSelect = document.getElementById('heatmap-profile');
    const frame = document.getElementById('heatmap-preview-frame');
    const stage = document.getElementById('heatmap-preview-stage');
    const overlay = document.getElementById('heatmap-overlay');
    let currentData = null;
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
        frame.parentElement.parentElement.hidden = moduleSelect.value !== 'agenda';
        canvas.hidden = moduleSelect.value === 'agenda';
        profileSelect.replaceChildren(new Option('Más reciente', ''));
        if (moduleSelect.value !== 'agenda') precisionSelect.value = 'approximate';
        window.AgendaHeatmap.draw(canvas.getContext('2d'), canvas.width, canvas.height, { screen: moduleSelect.value, grid_size: 40, points: [] });
    }
    let currentRequest = 0;
    function renderAgenda(data) {
        const profile = (data.profiles || []).find(p => p.key === data.profile);
        const width = profile?.width || 1366, height = profile?.height || 768;
        frame.width = width; frame.height = height;
        stage.style.width = width + 'px'; stage.style.height = height + 'px';
        overlay.width = width; overlay.height = height;
        const doc = frame.contentDocument, replay = frame.contentWindow?.AgendaHeatmapPreview;
        if (!replay) return;
        replay.apply(data.context, profile?.view || viewSelect.value || 'dia');
        // Layout is measured after the recorded dimensions, panels and scrolling are restored.
        window.requestAnimationFrame(() => {
            if (currentData !== data) return;
            const rectangles = {};
            doc.querySelectorAll('[data-ui-zone]').forEach(el => { rectangles[el.dataset.uiZone] = el.getBoundingClientRect(); });
            rectangles.screen = doc.getElementById('agenda-board').getBoundingClientRect();
            const counts = window.AgendaHeatmap.overlay(overlay.getContext('2d'), width, height, data, rectangles);
            status.textContent = data.total + ' clics · ' + counts.precise + ' coincidentes · ' + counts.approximate + ' con referencia aproximada'
                + (counts.outside ? ' · ' + counts.outside + ' fuera de la referencia visible (no se inventó el scroll)' : '')
                + (data.shown != null && data.shown < data.total ? ' · mostrando ' + data.shown : '')
                + (data.layout_version === 3 && !data.total ? '. Sin geometría capturada para estos filtros; consulta v2/v1.' : '');
        });
    }
    async function load(event) {
        if (event) { event.preventDefault(); }
        const request = ++currentRequest;
        try {
            const response = await fetch(root.dataset.endpoint + '?' + new URLSearchParams(new FormData(form)), { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) { throw new Error(data.message || 'No se pudo consultar la telemetría.'); }
            if (request !== currentRequest) { return; }
            currentData = data;
            profileSelect.disabled = precisionSelect.value !== 'captured';
            profileSelect.replaceChildren(new Option('Más reciente', ''));
            for (const profile of data.profiles || []) {
                profileSelect.append(new Option(profile.width + '×' + profile.height + ' · ' + profile.view + ' · ' + profile.clicks + ' clics', profile.key));
            }
            if (data.profile) profileSelect.value = data.profile;
            if (data.screen === 'agenda') renderAgenda(data);
            else {
                const maximum = window.AgendaHeatmap.draw(canvas.getContext('2d'), canvas.width, canvas.height, data, zoneSelect.value);
                status.textContent = data.total + ' clics aproximados · intensidad máxima ' + maximum;
            }
        } catch (error) { status.textContent = error.message || 'Telemetría temporalmente no disponible.'; }
    }
    form.addEventListener('submit', load);
    moduleSelect.addEventListener('change', function () { changeModule(); load(); });
    precisionSelect.addEventListener('change', () => { profileSelect.value = ''; profileSelect.disabled = precisionSelect.value !== 'captured'; load(); });
    profileSelect.addEventListener('change', load);
    [viewSelect, zoneSelect].forEach(select => select.addEventListener('change', () => { profileSelect.value = ''; load(); }));
    frame.addEventListener('load', () => { if (currentData?.screen === 'agenda') renderAgenda(currentData); });
    changeModule();
    if (!form.querySelector('button').disabled) { load(); }
}); }
