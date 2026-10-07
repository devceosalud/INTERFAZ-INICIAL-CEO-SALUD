(function (root) {
    'use strict';
    root.AgendaOperationalWorkspace = function (config) {
        const model = root.AgendaOperationalForm;
        const byId = id => document.getElementById('agenda-op-' + id);
        const ui = Object.fromEntries(['phone', 'phone-secondary', 'channel', 'medium', 'reason', 'note', 'price', 'paid',
            'amount', 'balance', 'method', 'operation', 'origin', 'authorized', 'waived', 'proof', 'links', 'documents',
            'submit-payment', 'confirm-reservation', 'add-documents', 'payment-panel'].map(id => [id, byId(id)]));
        let quote = 0, balance = 0, registrationKey = null, paymentKey = null, captureId = null, captureDirty = false;
        let captureVersion = 0, selectedKey = '', working = false;
        const headers = () => ({ Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content });
        const base = config.base;
        async function request(url, options = {}) {
            const response = await fetch(url, Object.assign({ credentials: 'same-origin', headers: headers() }, options));
            const body = await response.json().catch(() => ({}));
            if (!response.ok) { throw new Error(config.error(body)); }
            return body;
        }
        function json(method, data) { return { method, headers: Object.assign(headers(), { 'Content-Type': 'application/json' }), body: JSON.stringify(data) }; }
        function payment() { return model.payment(ui.amount.value, ui.method.value, ui.operation.value, ui.origin.value); }
        function calculate() {
            ui.operation.disabled = ui.method.disabled || ui.method.value === 'EFECTIVO';
            if (ui.method.value === 'EFECTIVO') { ui.operation.value = ''; }
            try { ui.balance.value = model.decimal(ui.waived.checked ? 0 : Math.max(0, balance - model.cents(ui.amount.value || '0'))); }
            catch (_) { ui.balance.value = 'Revisa el importe'; }
        }
        function economy(p) {
            quote = model.cents(p.precio); balance = model.cents(p.saldo);
            ui.price.value = p.precio; ui.paid.value = p.pago_real; calculate();
        }
        function quoteChanged(price) {
            if (config.context()?.tipo_contexto === 'cita_existente') { return; }
            quote = Math.round(Number(price || 0) * 100); balance = quote;
            ui.price.value = model.decimal(quote); ui.paid.value = '0.00'; calculate();
        }
        ['amount', 'method', 'waived'].forEach(id => ui[id].addEventListener('input', calculate));
        ['phone', 'phone-secondary', 'channel', 'medium'].forEach(id => ui[id].addEventListener('change', () => { captureDirty = true; }));
        async function patientChanged(id) {
            if (String(id || '') === String(captureId || '')) { return; }
            captureId = id || null; captureDirty = false; const version = ++captureVersion;
            ['phone', 'phone-secondary', 'channel', 'medium'].forEach(id => { ui[id].value = ''; });
            if (!id) { return; }
            try {
                const data = await request(config.patientTemplate.replace('__PATIENT__', encodeURIComponent(id)));
                if (version !== captureVersion || captureDirty) { return; }
                const p = data.patient;
                ui.phone.value = p.telefono || ''; ui['phone-secondary'].value = p.telefono_secundario || '';
                ui.channel.value = p.channel_id || ''; ui.medium.value = p.interaction_medium_id || '';
            } catch (e) { config.notice(e.message); }
        }
        async function register(options) {
            if (working) { return; }
            working = true; config.busy(true);
            try {
                const context = config.context();
                const payload = config.payload(context, options.patientId || 1, options.serviceId, options.ownerId);
                registrationKey = registrationKey || root.crypto.randomUUID();
                Object.assign(payload, { request_key: registrationKey, mode: options.pending ? 'RESERVE' : 'CONFIRM',
                    booking_type: options.bookingType, motivo_consulta: ui.reason.value || null, observaciones: ui.note.value || null,
                    es_exonerado: ui.waived.checked, autorizado_por: ui.authorized.value || null, payment: payment(), links: model.links(ui.links.value) });
                payload.patient_id = options.patientId || null;
                if (options.patient) { payload.patient = options.patient; }
                if (!options.patient && captureDirty && !ui.phone.disabled) {
                    payload.patient_capture = { telefono: ui.phone.value || null, telefono_secundario: ui['phone-secondary'].value || null,
                        channel_id: ui.channel.value || null, interaction_medium_id: ui.medium.value || null };
                }
                const body = new FormData(); body.append('payload', JSON.stringify(payload));
                if (ui.proof.files[0]) { body.append('proof', ui.proof.files[0]); }
                const response = await request(config.endpoint, { method: 'POST', body });
                registrationKey = null; ui.amount.value = '0'; ui.proof.value = ''; ui.links.value = ''; ui.reason.value = ''; ui.note.value = '';
                ui.waived.checked = false; ui.authorized.value = '';
                await config.registered(response);
            } catch (e) { ui['payment-panel'].open = true; config.notice(e.message, true); }
            finally { working = false; config.busy(false); }
        }
        async function documents(id) {
            const result = await request(base + '/' + id + '/documents');
            if (String(config.context()?.appointment_id) !== String(id)) { return; }
            ui.documents.replaceChildren();
            (result.documents || []).forEach(d => {
                const row = document.createElement('div'); row.className = 'agenda-document-row';
                const link = document.createElement('a'); link.textContent = d.label; link.href = d.url || d.download_url;
                link.target = '_blank'; link.rel = 'noopener noreferrer'; row.appendChild(link);
                const label = document.createElement('input'); label.value = d.label; label.maxLength = 120; label.setAttribute('aria-label', 'Etiqueta del documento');
                const url = document.createElement('input'); url.value = d.url || ''; url.setAttribute('aria-label', 'Link HTTPS del documento');
                const save = document.createElement('button'); save.type = 'button'; save.textContent = 'Guardar'; save.className = 'agenda-btn';
                save.addEventListener('click', async () => {
                    try { await request(base + '/' + id + '/documents/' + d.id, json('PUT', d.url ? { label: label.value, url: url.value } : { label: label.value })); await documents(id); }
                    catch (e) { config.notice(e.message, true); }
                });
                const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Quitar'; remove.className = 'agenda-btn';
                remove.addEventListener('click', async () => {
                    try { await request(base + '/' + id + '/documents/' + d.id, { method: 'DELETE' }); await documents(id); }
                    catch (e) { config.notice(e.message, true); }
                });
                if (result.can_write) { row.appendChild(label); if (d.url) { row.appendChild(url); } row.append(save, remove); }
                ui.documents.appendChild(row);
            });
        }
        async function selectionChanged(context) {
            const key = [context?.appointment_id || '', context?.doctor_id || '', context?.fecha || '', context?.hora_inicio || ''].join('|');
            if (key !== selectedKey) { selectedKey = key; registrationKey = null; paymentKey = null; ui.amount.value = '0'; }
            const id = context?.appointment_id;
            ui['submit-payment'].hidden = !id || !config.canCreate; ui['add-documents'].hidden = !id;
            ui['confirm-reservation'].hidden = !id || !config.canCreate || context.estado_agenda !== 'PENDIENTE_CONFIRMACION';
            ui.documents.replaceChildren();
            ui.reason.disabled = Boolean(id); ui.note.disabled = Boolean(id);
            if (!id) { return; }
            try {
                const p = await request(base + '/' + id + '/economy');
                if (String(config.context()?.appointment_id) !== String(id)) { return; }
                economy(p); ui.reason.value = p.motivo_consulta || ''; ui.note.value = p.observaciones || '';
                ui.authorized.value = p.autorizado_por || ''; ui.waived.checked = Boolean(p.es_exonerado); await documents(id);
            } catch (e) { config.notice(e.message, true); }
        }
        async function submitPayment(confirm) {
            const id = config.context()?.appointment_id;
            if (!id || working) { return; }
            working = true; config.busy(true);
            try {
                paymentKey = paymentKey || root.crypto.randomUUID();
                const response = await request(base + '/' + id + '/payments', json('POST', { request_key: paymentKey, confirm,
                    payment: confirm ? model.payment('0', ui.method.value, '', '') : payment() }));
                paymentKey = null; ui.amount.value = '0'; economy(response.economy);
                await config.paymentUpdated(response); config.notice(confirm ? 'Reserva confirmada: ' + response.appointment.tipo_agendamiento : 'Adelanto registrado. Saldo: S/ ' + response.economy.saldo);
            } catch (e) { config.notice(e.message, true); }
            finally { working = false; config.busy(false); }
        }
        ui['submit-payment'].addEventListener('click', () => submitPayment(false));
        ui['confirm-reservation'].addEventListener('click', () => submitPayment(true));
        ui['add-documents'].addEventListener('click', async () => {
            const id = config.context()?.appointment_id;
            if (!id || working) { return; }
            working = true;
            try {
                if (ui.proof.files[0]) {
                    const body = new FormData(); body.append('label', 'Comprobante de pago'); body.append('file', ui.proof.files[0]);
                    await request(base + '/' + id + '/documents', { method: 'POST', body }); ui.proof.value = '';
                }
                const pending = model.links(ui.links.value);
                for (let i = 0; i < pending.length; i++) {
                    await request(base + '/' + id + '/documents', json('POST', pending[i]));
                    ui.links.value = pending.slice(i + 1).map(l => l.label + ' | ' + l.url).join('\n');
                }
                await documents(id); config.notice('Documentos guardados.');
            } catch (e) { config.notice(e.message, true); }
            finally { working = false; }
        });
        return { register, quoteChanged, patientChanged, selectionChanged };
    };
}(typeof window !== 'undefined' ? window : globalThis));
