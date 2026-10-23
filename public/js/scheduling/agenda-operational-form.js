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
    function paymentProjection(position, amount='0') {
        const unknown=position.fuente==='UNALLOCATED',historical=position.fuente==='LEGACY_SNAPSHOT';
        return {paidLabel:historical?'Abonado histórico · no acredita pago':'Total pagado anteriormente',
            paid:unknown?null:decimal(cents(position.pago_real||'0')),
            currentBalance:unknown?null:decimal(cents(position.saldo||'0')),
            advance:decimal(cents(amount||'0')),
            balance:unknown?null:decimal(Math.max(0,cents(position.saldo||'0')-cents(amount||'0')))};
    }
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
    function reservationReadiness(input) {
        let reason = '';
        if (!input.canCreate) { reason = 'No tienes permiso para guardar reservas (appointment.create). Solicita revisión al administrador.'; }
        else if (input.busy) { reason = 'Guardando. Espera el resultado de esta operación.'; }
        else if (!input.slot) { reason = 'Selecciona un intervalo regular para guardar la reserva.'; }
        else if (!input.patient) { reason = 'Busca y selecciona un paciente activo para guardar la reserva.'; }
        else if (!input.service) { reason = 'Selecciona un servicio activo para guardar la reserva.'; }
        else {
            try {
                const price = cents(input.price || '0'), amount = cents(input.amount || '0');
                if (price <= 0 && !input.automaticFree) { reason = 'El servicio necesita un precio válido. Revisa el catálogo.'; }
                else if (amount > price) { reason = 'El adelanto supera el precio de la cita.'; }
                else if (amount > 0 && !input.canPay) { reason = 'Guardar con adelanto requiere appointment.payment.submit. Puedes guardar la reserva con importe cero.'; }
            } catch (e) { reason = e.message; }
        }
        return { allowed: !reason, reason };
    }
    function bookingReadiness(input) {
        let reason = '';
        if (!input.canCreate) { reason = 'No tienes permiso para guardar reservas ni crear citas (appointment.create). Solicita revisión al administrador.'; }
        else if (input.busy) { reason = 'Guardando. Espera el resultado de esta operación.'; }
        else if (!input.slot && !input.exception) { reason = 'Selecciona un intervalo disponible para confirmar una cita.'; }
        else if (!input.patient) { reason = 'Busca y selecciona un paciente activo para esta cita.'; }
        else if (!input.service) { reason = 'Selecciona un servicio activo para esta cita.'; }
        else {
            try {
                const price = cents(input.price || '0'), amount = cents(input.amount || '0');
                if (price <= 0 && !input.automaticFree) { reason = 'El servicio necesita un precio válido. Revisa el catálogo.'; }
                else if (amount > price) { reason = 'El adelanto supera el precio de la cita.'; }
                else if (!input.canPay && ((!input.waived && !input.automaticFree) || amount > 0)) {
                    reason = 'Puedes guardar una reserva sin pago. Registrar el adelanto requiere autorización (appointment.payment.submit) y revisión de Caja.';
                } else if (!input.waived && !input.automaticFree && amount <= 0) {
                    reason = 'Para confirmar registra un pago real mayor a S/0. Puedes guardar una reserva sin confirmar.';
                }
            } catch (e) { reason = e.message; }
        }
        return { allowed: !reason, reason };
    }

    function financialLabel(context,compact=false){const status=context?.financial_status;if(!status)return context?.estado_pagado||'—';return compact?({EXEMPT:'Exonerada',CLINICAL_FREE:'Sin costo clínico',AUTHORIZED_DISCOUNT:'Desc. autorizado',VALIDATION:'Validar precio',DUE:'Pendiente',PAID:'Pagado',CREDIT:'Crédito aplicado'}[status.key]||status.label):status.label;}
    function stateSummary(context) {
        const c=context||{};
        const agenda={CONFIRMADA:'CONFIRMADA',PENDIENTE_CONFIRMACION:'RESERVA PENDIENTE',LEGADO:'AGENDA HISTÓRICA'}[c.estado_agenda]||'AGENDA SIN ACREDITAR';
        const care=String(c.estado_cita||'SIN ESTADO DE ATENCIÓN').replace(/_/g,' ');
        const payment=String(c.financial_status?.label||c.estado_pagado||'SIN ESTADO FINANCIERO');
        return {agenda,care,payment,description:'Horario: '+agenda+' · Atención: '+care+' · Pago: '+payment};
    }
    return { cents, decimal, paymentProjection, payment, links, nonBlank, bookingReadiness, reservationReadiness, financialLabel, stateSummary };
}));
