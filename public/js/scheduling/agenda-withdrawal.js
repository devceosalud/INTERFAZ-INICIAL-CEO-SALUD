(function (root, factory) {
    const api = factory(root);
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    else { root.AgendaWithdrawal = api; }
}(typeof window !== 'undefined' ? window : globalThis, function (root) {
    'use strict';
    function moneyLines(rows) {
        return rows.filter(r => Number(r.amount) > 0).map(r => {
            if (!/^\d+(\.\d{1,2})?$/.test(String(r.amount)) || Number(r.amount) > Number(r.available)) { throw new Error('Revisa el importe disponible del ticket.'); }
            return { voucher_id: Number(r.voucher_id), amount: String(r.amount) };
        });
    }
    function start(config) {
        const id = name => document.getElementById('agenda-' + name);
        const panel = id('workflow-panel'), credit = id('withdrawal-credit');
        let current = null, version = 0, busy = false, key = null;
        function headers() { return { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }; }
        async function request(url, method = 'GET', data) {
            const response = await fetch(url, { method, credentials: 'same-origin', headers: headers(), body: data ? JSON.stringify(data) : undefined });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) { const error = new Error(config.error(body, response.status)); error.status = response.status; throw error; } return body;
        }
        async function select(appointmentId, context = {}) {
            const seq = ++version; current = appointmentId || null; key = null; panel.hidden = !appointmentId || !config.canWorkflow;
            if (!appointmentId || !config.canWorkflow) { return; }
            try {
                const data = await request(config.base + '/' + appointmentId + '/history');
                if (seq !== version) { return; }
                id('workflow-title').textContent = 'Cita #' + appointmentId + ' · ' + data.estado_cita;
                id('withdrawal-current').hidden = data.estado_cita === 'RETIRO'; id('withdrawal-followup').hidden = data.estado_cita !== 'RETIRO';
                id('withdrawal-duration').value = context.duracion || context.duracion_cita || 20;
                id('withdrawal-date').value = context.fecha || context.fecha_cita || '';
                id('withdrawal-reason').value = ''; id('withdrawal-present').checked = false;
                id('workflow-events').replaceChildren(); credit.replaceChildren();
                data.events.forEach(e => { const p = document.createElement('p'); p.textContent = (e.type === 'RETIRO' ? 'Retiro registrado' : e.type === 'REPROGRAMACION_RETIRO' ? 'Nueva cita desde retiro' : e.type.replaceAll('_', ' ').toLowerCase()) + ' · ' + new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Lima' }).format(new Date(e.occurred_at)) + (e.actor ? ' · ' + e.actor : '') + (e.motivo ? ' · ' + e.motivo : '') + (e.related_appointment_id ? ' · Cita #' + e.related_appointment_id : ''); id('workflow-events').append(p); });
                data.available_credit.forEach(line => {
                    if (Number(line.amount) <= 0) { return; }
                    const label = document.createElement('label'); label.className = 'agenda-field';
                    const text = document.createElement('span'); text.textContent = 'Ticket #' + line.voucher_id + ' · Disponible S/ ' + line.amount;
                    const input = document.createElement('input'); input.type = 'number'; input.min = '0'; input.max = line.amount; input.step = '0.01'; input.value = '0'; input.className = 'agenda-field__input';
                    input.dataset.voucher = line.voucher_id; input.dataset.available = line.amount; label.append(text, input); credit.append(label);
                });
                id('withdrawal-refunds').textContent = data.refund_requests.map(r => 'Devolución #' + r.id + ' · S/ ' + r.amount + ' · ' + r.status).join(' | ');
            } catch (e) {
                const placed = root.AgendaGuidance ? root.AgendaGuidance.place(e.message, e.status, 'withdraw') : { text: e.message };
                const errorBox = id('withdrawal-error');
                if (errorBox) { errorBox.hidden = false; errorBox.textContent = placed.text; }
            }
        }
        function lines() { return moneyLines(Array.from(credit.querySelectorAll('input')).map(input => ({ voucher_id: input.dataset.voucher, amount: input.value, available: input.dataset.available }))); }
        async function mutate(action) {
            if (!current || busy) { return; } busy = true;
            const original = current;
            try {
                key = key || root.crypto.randomUUID(); const data = { request_key: key, motivo: id('withdrawal-reason').value };
                if (action === 'withdraw') { Object.assign(data, { requested_action: id('withdrawal-action').value, was_present: id('withdrawal-present').checked }); }
                if (action === 'rebook-withdrawal') { Object.assign(data, { fecha_cita: id('withdrawal-date').value, hora_cita: id('withdrawal-time').value,
                    duracion_cita: Number(id('withdrawal-duration').value), booking_type: id('withdrawal-type').value, credits: lines() }); }
                if (action === 'refund-requests') { data.refunds = lines(); }
                const result = await request(config.base + '/' + original + '/' + action, 'POST', data); key = null;
                await config.refresh(); await select(original); panel.open = true;
                const guidance = root.AgendaGuidance;
                const when = id('withdrawal-date').value.split('-').reverse().join('/') + ' a las ' + id('withdrawal-time').value;
                const done = action === 'rebook-withdrawal'
                    ? (guidance ? guidance.rebooked(id('withdrawal-date').value.split('-').reverse().join('/'), id('withdrawal-time').value) : 'Nueva cita creada para ' + when + '.')
                    : (action === 'withdraw' ? 'Retiro registrado. El horario quedó libre.' : (guidance ? guidance.copy.refundDone : 'Solicitud de devolución registrada.'));
                const status = id('withdrawal-status'); const errorBox = id('withdrawal-error');
                if (errorBox) { errorBox.hidden = true; }
                if (status) { status.hidden = false; status.textContent = done; }
            } catch (e) {
                const placed = root.AgendaGuidance ? root.AgendaGuidance.place(e.message, e.status, 'withdraw') : { text: e.message };
                const errorBox = id('withdrawal-error');
                if (errorBox) { errorBox.hidden = false; errorBox.textContent = placed.text; }
            } finally { busy = false; }
        }
        const actionSelect = id('withdrawal-action');
        const refundHint = id('withdrawal-refund-hint');
        if (actionSelect && actionSelect.addEventListener) {
            actionSelect.addEventListener('change', () => { if (refundHint) { refundHint.hidden = actionSelect.value !== 'DEVOLUCION'; } });
        }
        id('withdrawal-submit').addEventListener('click', () => mutate('withdraw'));
        id('withdrawal-rebook').addEventListener('click', () => mutate('rebook-withdrawal'));
        id('withdrawal-refund').addEventListener('click', () => mutate('refund-requests'));
        async function feed(payload) {
            const list = id('withdrawal-history-list'); list.replaceChildren();
            const rows = payload.retiros || []; id('withdrawal-history').hidden = !rows.length; id('withdrawal-count').textContent = '(' + rows.length + ')';
            rows.forEach(row => { const button = document.createElement('button'); button.type = 'button'; button.className = 'agenda-btn';
                button.textContent = row.fecha + ' ' + row.hora + ' · RETIRO · ' + row.patient; button.disabled = !config.canWorkflow;
                button.addEventListener('click', async () => { await select(row.appointment_id, row); panel.open = true; panel.scrollIntoView({ block: 'nearest' }); }); list.append(button); });
            try {
                const response = await request(config.inbox); const target = id('contingency-list'); target.replaceChildren();
                const rows = response.contingencies || []; id('contingency-badge').textContent = rows.length ? '(' + rows.length + ' pendientes)' : '';
                rows.forEach(row => { const entry = document.createElement('div'); entry.className = 'agenda-document-row';
                    const when = String(row.fecha || '').split('-').reverse().join('/') + ' ' + (row.hora || '');
                    const notice = document.createElement('span'); notice.textContent = root.AgendaGuidance.contingency(row.patient, when.trim());
                    const note = document.createElement('input'); note.placeholder = 'Resultado del seguimiento humano'; note.maxLength = 2000; note.setAttribute('aria-label', 'Resultado del seguimiento');
                    const read = document.createElement('button'); read.type = 'button'; read.textContent = row.read_at ? 'Leído' : 'Marcar como leído'; read.disabled = Boolean(row.read_at);
                    const close = document.createElement('button'); close.type = 'button'; close.textContent = 'Resolver';
                    [read, close].forEach(button => button.addEventListener('click', async () => { try { if (button === close && !note.value.trim()) { let err = entry.querySelector('.agenda-field-error'); if (!err) { err = document.createElement('p'); err.className = 'agenda-field-error'; entry.append(err); } err.textContent = root.AgendaGuidance.copy.resolveNote; return; }
                        await request(config.inbox + '/' + row.id, 'PATCH', button === close ? { resolution: note.value } : {});
                        if (button === close) { const done = id('contingency-status'); if (done) { done.hidden = false; done.textContent = root.AgendaGuidance.copy.resolved; } }
                        await feed(payload); } catch (e) { const err = document.createElement('p'); err.className = 'agenda-field-error'; err.textContent = root.AgendaGuidance.place(e.message, e.status, 'local').text; entry.append(err); } }));
                    entry.append(notice, note, read, close); target.append(entry);
                });
            } catch (e) {
                const target = id('contingency-list');
                const err = document.createElement('p');
                err.className = 'agenda-field-error';
                err.textContent = root.AgendaGuidance ? root.AgendaGuidance.place(e.message, e.status, 'local').text : e.message;
                target.append(err);
            }
        }
        return { select, feed };
    }
    return { start, moneyLines };
}));
