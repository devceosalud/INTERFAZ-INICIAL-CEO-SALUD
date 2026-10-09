(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaOperationalForm = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    function cents(value) {
        const m = /^(\d{1,8})(?:\.(\d{1,2}))?$/.exec(String(value));
        if (!m) { throw new Error('Usa un importe válido con hasta dos decimales.'); }
        return Number(m[1]) * 100 + Number((m[2] || '').padEnd(2, '0'));
    }
    function decimal(value) { return (value / 100).toFixed(2); }
    function nonBlank(data) {
        return Object.fromEntries(Object.entries(data).filter(([, value]) => value != null && String(value).trim() !== ''));
    }
    function payment(amount, method, operation, origin) {
        const value = cents(amount || '0');
        if (value && method !== 'EFECTIVO' && !String(operation).trim()) { throw new Error('Indica el número de operación de este pago.'); }
        return { amount: decimal(value), method: method, operation: method === 'EFECTIVO' ? null : String(operation).trim(), origin: origin || null };
    }
    function links(raw) {
        const rows = String(raw || '').split('\n').map(s => s.trim()).filter(Boolean);
        if (rows.length > 10) { throw new Error('Agrega como máximo diez links por operación.'); }
        return rows.map((line, index) => {
            const separator = line.indexOf('|');
            const label = separator < 0 ? 'Documento externo ' + (index + 1) : line.slice(0, separator).trim();
            const url = separator < 0 ? line : line.slice(separator + 1).trim();
            try { if (new URL(url).protocol !== 'https:') { throw new Error(); } } catch (_) { throw new Error('Los links deben usar HTTPS.'); }
            if (!label || label.length > 120 || url.length > 2048) { throw new Error('Revisa la etiqueta y longitud del link.'); }
            return { label, url };
        });
    }
    return { cents, decimal, payment, links, nonBlank };
}));
