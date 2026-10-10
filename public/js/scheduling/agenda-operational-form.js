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
    function bookingReadiness(input) {
        let reason = '';
        if (!input.canCreate) { reason = 'No tienes permiso para guardar reservas ni crear citas (appointment.create). Solicita revisión al administrador.'; }
        else if (input.busy) { reason = 'Guardando. Espera el resultado de esta operación.'; }
        else if (!input.slot) { reason = 'Selecciona un intervalo disponible para confirmar una cita.'; }
        else if (!input.patient) { reason = 'Busca y selecciona un paciente activo para esta cita.'; }
        else if (!input.service) { reason = 'Selecciona un servicio activo para esta cita.'; }
        else if (!input.exception) {
            try {
                const price = cents(input.price || '0'), amount = cents(input.amount || '0');
                if (price <= 0) { reason = 'El servicio necesita un precio válido. Revisa el catálogo.'; }
                else if (amount > price) { reason = 'El adelanto supera el precio de la cita.'; }
                else if (!input.canPay && (!input.waived || amount > 0)) {
                    reason = 'Puedes guardar una reserva sin pago. Registrar el adelanto requiere autorización (appointment.payment.submit) y revisión de Caja.';
                } else if (!input.waived && amount * 2 < price) {
                    reason = 'Para confirmar registra al menos S/ ' + decimal(Math.ceil(price / 2)) + ' (50%). Puedes guardar una reserva sin confirmar.';
                }
            } catch (e) { reason = e.message; }
        }
        return { allowed: !reason, reason };
    }
    return { cents, decimal, payment, links, nonBlank, bookingReadiness };
}));
