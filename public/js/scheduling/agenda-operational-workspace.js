(function (root) {
    'use strict';
    root.AgendaOperationalWorkspace = function (config) {
        const model = root.AgendaOperationalForm;
        const byId = id => document.getElementById('agenda-op-' + id);
        const ui = Object.fromEntries(['phone', 'phone-secondary', 'channel', 'medium', 'reason', 'note', 'price', 'paid',
            'amount', 'balance', 'method', 'operation', 'origin', 'authorized', 'waived', 'proof', 'links', 'documents',
            'submit-payment', 'confirm-reservation', 'add-documents', 'payment-panel', 'save-patient', 'save-notes'].map(id => [id, byId(id)]));
        const receipt = root.AppointmentReceiptReview ? root.AppointmentReceiptReview({host:byId('receipt-review'),input:ui.proof,prefix:'agenda-op',
            paymentFields:{amount:ui.amount,method:ui.method,operation:ui.operation,origin:ui.origin},onProofRemoved:()=>clearStalePaymentMessages()}) : {
                append() {if(Array.from(ui.proof.files || []).some(f=>root.AgendaGuidance.invalidProof(f))) throw new Error(root.AgendaGuidance.copy.fileInvalid);
                    if(ui.proof.files?.length) throw new Error('No se cargó la revisión de comprobantes. Actualiza Agenda antes de adjuntar; puedes registrar un pago manual sin imagen.');},
                reset:async()=>{ui.proof.value='';},hasChanges:()=>Boolean(ui.proof.files?.length)};
        const validation = root.AgendaFormValidation?.create({summary:byId('payment-summary'),fields:{
            'payment.amount':ui.amount,'payment.method':ui.method,'payment.operation':ui.operation,'payment.origin':ui.origin,
            'proofs':ui.proof,'proof_review.reviewed':()=>byId('proof-reviewed'),
            'discount.value':()=>byId('discount-value'),'discount.type':()=>byId('discount-type'),
            'referral.origin':()=>byId('referral-origin'),'motivo_exoneracion':()=>byId('exemption-reason'),
            'autorizado_por':ui.authorized,'motivo_consulta':ui.reason,'service_id':()=>document.getElementById('agenda-service-select')
        }});
        const documentFile=byId('document-file');
        let replacementDocument=null,replacementKey=null,patientDocuments=null,patientDocumentId=null,patientDocumentDirty=false;
        function clearReplacement(){replacementDocument=null;replacementKey=null;byId('replacement-panel').hidden=true;byId('replacement-file').value='';byId('replacement-reason').value='';captureClean(['replacement-file','replacement-reason']);}
        const discountType = byId('discount-type'), discountValue = byId('discount-value'), discountReason = byId('discount-reason');
        const catalogPrice = byId('catalog-price'), hasWound = byId('has-wound'), postoperative = byId('postoperative'), exemptionReason = byId('exemption-reason');
        function discountInput() { return discountType?.value ? { type: discountType.value, value: discountValue.value || '0', reason: discountReason.value || null } : null; }
        const initialFinancialLocks = { waived: ui.waived.disabled, authorized: ui.authorized.disabled, exemption: exemptionReason?.disabled };
        const doubleInput=byId('double');
        const indicationInput=byId('indication'), referredInput=byId('referred'), referralOrigin=byId('referral-origin');
        const modalityInput=byId('price-modality'),commercialInput=byId('commercial-service');
        function medicalPriceInput() {
            const data={};
            if(modalityInput?.value){data.price_modality=modalityInput.value;if(modalityInput.value==='REEVALUATION'&&commercialInput?.value)data.commercial_service_id=Number(commercialInput.value);}
            if(indicationInput?.value) data.reevaluation_indication_id=Number(indicationInput.value);
            if(referredInput?.checked) data.referral={origin:referralOrigin.value,attested:true};
            return data;
        }
        let automaticFree=false, indicationRecordKey=null;
        let quoteReady = true, quoteVersion = 0, activeQuoteRequest = null;
        byId('proof')?.addEventListener('change',()=>{const panel=byId('proof-panel');if(panel)panel.open=true;});
        const rateSelect = byId('rate'), quoteStatus = byId('quote-status'), quoteRetry = byId('quote-retry');
        let quote = 0, balance = 0, registrationKey = null, paymentKey = null, captureId = null, captureDirty = false;
        let captureVersion = 0, selectedKey = '', working = false;
        let capturePatient = null;
        const pendingDocumentEdits=new Map();
        const tracked=['phone','phone-secondary','channel','medium','reason','note','amount','method','operation','origin','authorized','waived','proof','links',
            'attendance-mode','rate','discount-type','discount-value','discount-reason','has-wound','postoperative','exemption-reason','double','indication','price-modality','commercial-service','referred','referral-origin',
            'indication-file','indication-document','indication-date','indication-evidence','indication-reference','indication-attested',
            'document-file','document-kind','document-label','replacement-file','replacement-reason','proof-reviewed','receipt-date','receipt-time'];
        const cleanValues=new Map();
        let cleanExtra='',detailsVersion=0,documentsVersion=0;
        function fieldValue(name) {const f=byId(name);return f?JSON.stringify([f.value||'',Boolean(f.checked),Array.from(f.files||[]).map(v=>[v.name||'',v.size||0])]):'';}
        function captureClean(names=tracked) {names.forEach(name=>cleanValues.set(name,fieldValue(name)));if(names===tracked)cleanExtra=config.extraState?.()||'';}
        function hasUnsavedChanges() {return patientDocumentDirty || receipt.hasChanges() || pendingDocumentEdits.size>0 || tracked.some(name=>{const f=byId(name);return f && !f.disabled && cleanValues.get(name)!==fieldValue(name);}) || cleanExtra!==(config.extraState?.()||'');}
        function contextKey(c) {return [c?.appointment_id||'',c?.doctor_id||'',c?.fecha||'',c?.hora_inicio||'',c?.site_id||'',c?.tipo_contexto||''].join('|');}
        function pendingRevision() {return JSON.stringify([tracked.map(fieldValue),Array.from(pendingDocumentEdits),config.extraState?.()||'']);}
        async function confirmDiscard(message) {
            if (!root.Swal) {config.notice('No se pudo abrir la confirmación. Actualiza Agenda; tus cambios se conservaron.',true);return false;}
            const decision=await root.Swal.fire({title:'Cambios sin guardar',text:message,showCancelButton:true,
                confirmButtonText:'Descartar y continuar',cancelButtonText:'Conservar cambios',focusCancel:true,
                allowEscapeKey:true,allowOutsideClick:false,width:430});
            return decision.isConfirmed===true || decision.value===true;
        }
        async function prepareSelection(next) {
            if(working || (config.isBusy && config.isBusy())){config.notice('Espera a que termine la operación antes de cambiar de contexto.',true);return false;}
            if(contextKey(next)===selectedKey || !hasUnsavedChanges())return true;
            return confirmDiscard('Hay datos sin guardar en esta cita o reserva. ¿Descartar esos cambios y continuar?');
        }

        const headers = () => ({ Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content });
        const base = config.base;
        let buttonsBeforeWork = [], lastPosition = null;
        function setWorking(value) {
            working = value;ui.reason.readOnly=value;
            const fieldsHost=document.getElementById('agenda-operational-fields');
            if(fieldsHost)fieldsHost.inert=value;
            if (value) {
                buttonsBeforeWork = ['submit-payment', 'confirm-reservation', 'add-documents', 'save-patient', 'save-notes']
                    .map(id => [ui[id], ui[id].disabled]);
                buttonsBeforeWork.forEach(([button]) => { button.disabled = true; });
            } else {
                buttonsBeforeWork.forEach(([button, disabled]) => { button.disabled = disabled; });
                buttonsBeforeWork = [];
            }
            if (config.busy) { config.busy(value); }
            if (!value && lastPosition) { confirmation(lastPosition); }
        }
        function rejected(error, kind) {
            // Preserve UUID on uncertain outcomes (network/5xx/409); changing data cannot double-charge.
            if (![400, 403, 404, 413, 422, 429].includes(error.status)) { return; }
            if (kind === 'registration') { registrationKey = null; }
            if (kind === 'payment') { paymentKey = null; }
        }
        function readiness() {
            return { automaticFree: quoteReady && automaticFree, price: quoteReady ? ui.price.value : '0.00', amount: ui.amount.value, canPay: !ui.amount.disabled && lastPosition?.reservation_slot_available !== false,
                waived: !ui.waived.disabled && ui.waived.checked && Boolean(ui.authorized.value.trim()) };
        }
        function hasConfirmablePayment(position) {
            return position.asegurada || (position.fuente === 'PAYMENTS' && Number(position.pago_real || 0) > 0
                && Number(position.saldo) < Number(position.precio));
        }
        function reservationConfirmation() {
            const pending = config.context()?.estado_agenda === 'PENDIENTE_CONFIRMACION';
            return { allowed: Boolean(pending && !working && config.canCreate && lastPosition
                && lastPosition.can_confirm_additional !== false && lastPosition.reservation_slot_available !== false && (hasConfirmablePayment(lastPosition) || (lastPosition.es_exonerado && String(lastPosition.autorizado_por || '').trim()))),
                reason: working ? 'Guardando. Espera el resultado de esta operaci\u00f3n.' :
                    !config.canCreate ? 'Confirmar requiere appointment.create.' :
                    !pending ? 'La cita seleccionada no tiene una confirmaci\u00f3n pendiente.' :
                    !lastPosition ? 'Consultando el requisito financiero de la reserva\u2026' :
                    lastPosition.reservation_slot_available===false ? lastPosition.reservation_block_reason :
                    'Para confirmar se requiere un pago real positivo o exoneraci\u00f3n autorizada.' };
        }
        function confirmReservation() {
            if (reservationConfirmation().allowed) { return submitPayment(true); }
        }
        function confirmation(position) {
            const reason = byId('confirmation-reason');
            const allowed = config.canCreate && position.can_confirm_additional!==false && position.reservation_slot_available!==false && (hasConfirmablePayment(position) || (position.es_exonerado && String(position.autorizado_por || '').trim()));
            ui['confirm-reservation'].disabled = working || !allowed;
            ui['submit-payment'].disabled = working || position.reservation_slot_available===false || ui.amount.disabled;
            const conversion = byId('convert-additional'), explanation = byId('conversion-reason'), offer = position.additional_conversion;
            if (conversion) { conversion.hidden = !offer?.available; conversion.disabled = working || !offer?.can_convert; }
            if (explanation) { explanation.hidden = !offer?.available; explanation.textContent = offer ? offer.reason + ' Precio conservado: S/ ' + offer.price + '. Adelanto mínimo: S/ ' + offer.minimum_advance + '. La conversión no registra dinero ni confirma la cita.' : ''; }
            if (reason) {
                reason.textContent = !config.canCreate ? 'Confirmar requiere appointment.create.' :
                    position.can_confirm_additional===false ? 'Confirmar una cita ADICIONAL requiere appointment.additional.create.' :
                    position.reservation_slot_available===false ? position.reservation_block_reason :
                    !allowed ? 'Para confirmar se requiere un pago real positivo o exoneración autorizada. Un nuevo pago real positivo confirma automáticamente.' :
                    'El requisito financiero está cubierto. Confirmar volverá a validar el horario y el profesional.';
            }
        }
        async function request(url, options = {}) {
            const response = await fetch(url, Object.assign({ credentials: 'same-origin', headers: headers() }, options));
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(config.error(body, response.status));
                error.status = response.status;
                error.errors = response.status === 422 ? (body.errors || {}) : {};
                throw error;
            }
            return body;
        }
        function json(method, data) { return { method, headers: Object.assign(headers(), { 'Content-Type': 'application/json' }), body: JSON.stringify(data) }; }
        function inlineStatus(domain, message, error = false) {
            const status = byId(domain + '-status'), alert = byId(domain + '-error');
            status.hidden = error || !message; alert.hidden = !error;
            (error ? alert : status).textContent = message || '';
        }
        function captureValues() {
            return model.nonBlank({ telefono: ui.phone.value, telefono_secundario: ui['phone-secondary'].value,
                channel_id: ui.channel.value, interaction_medium_id: ui.medium.value });
        }
        ui['save-patient'].addEventListener('click', async () => {
            const id = captureId, version = captureVersion;
            if (!id || working || ui.phone.disabled) { return; }
            const payload = captureValues();
            if (config.context()?.appointment_id) { payload.appointment_id = config.context().appointment_id; }
            setWorking(true);
            inlineStatus('patient', '');
            try {
                const result = await request(config.patientTemplate.replace('__PATIENT__', encodeURIComponent(id)) + '/agenda-contact', json('PATCH', payload));
                if (version !== captureVersion) { return; }
                capturePatient = result.patient; captureDirty = false;
                ui.phone.value = result.patient.telefono || ''; ui['phone-secondary'].value = result.patient.telefono_secundario || '';
                ui.channel.value = result.patient.channel_id || ''; ui.medium.value = result.patient.interaction_medium_id || '';
                document.getElementById('agenda-patient-phone-summary').textContent = result.patient.telefono || 'Sin celular';
                captureClean(['phone','phone-secondary','channel','medium']);inlineStatus('patient', result.message);
            } catch (e) { if (version === captureVersion) { inlineStatus('patient', e.message, true); } }
            finally { setWorking(false); ui['save-patient'].disabled = !captureId || ui.phone.disabled; }
        });
        ui['save-notes'].addEventListener('click', async () => {
            const id = config.context()?.appointment_id;
            if (!id || working || ui.reason.disabled) { return; }
            const fields=[ui.reason,ui.note,postoperative,hasWound].filter(Boolean);
            const locks=fields.map(field=>field.disabled);
            const notes=model.nonBlank({motivo_consulta:ui.reason.value,observaciones:ui.note.value,has_diabetic_wound:Boolean(hasWound?.checked),is_postoperative:Boolean(postoperative?.checked)});
            if(lastPosition?.management?.revision){notes.request_key=root.crypto.randomUUID();notes.expected_revision=lastPosition.management.revision;}
            fields.forEach(field=>field.disabled=true);
            setWorking(true); inlineStatus('notes', '');
            try {
                const result = await request(base + '/' + id + '/notes', json('PATCH', notes));
                if (String(config.context()?.appointment_id) !== String(id)) { return; }
                ui.reason.value = result.motivo_consulta || ''; ui.note.value = result.observaciones || ''; if(hasWound)hasWound.checked=Boolean(result.has_diabetic_wound); if (postoperative) postoperative.checked = Boolean(result.is_postoperative);
                if(result.management)lastPosition.management=result.management;
                captureClean(['reason','note','has-wound','postoperative']);inlineStatus('notes', result.message);
            } catch (e) { if (String(config.context()?.appointment_id) === String(id)) { inlineStatus('notes', e.message, true); } }
            finally { fields.forEach((field,i)=>field.disabled=locks[i]);setWorking(false); ui['save-notes'].disabled = ui.reason.disabled; }
        });
        function payment() { return model.payment(ui.amount.value, ui.method.value, ui.operation.value, ui.origin.value); }
        function calculate() {
            ui.operation.disabled = ui.method.disabled || ui.method.value === 'EFECTIVO';
            const operationTip = document.getElementById('agenda-op-operation-tip');
            if (operationTip) {
                if (ui.operation.disabled && ui.method.value === 'EFECTIVO') { operationTip.dataset.tip = root.AgendaGuidance.copy.cashNoOperation; }
                else { delete operationTip.dataset.tip; }
            }
            if (ui.method.value === 'EFECTIVO') { ui.operation.value = ''; }
            try { ui.balance.value = model.decimal(ui.waived.checked ? 0 : Math.max(0, balance - model.cents(ui.amount.value || '0'))); }
            catch (_) { ui.balance.value = 'Revisa el importe'; }
            try {
                const projection=model.paymentProjection(lastPosition||{pago_real:'0',saldo:model.decimal(ui.waived.checked?0:balance)},ui.amount.value);
                if(byId('paid-label'))byId('paid-label').textContent=projection.paidLabel;
                if(byId('current-balance'))byId('current-balance').value=projection.currentBalance??'Por conciliar';
                ui.paid.value=projection.paid??'Por conciliar';ui.balance.value=projection.balance??'Por conciliar';
                config.quoted?.({status:quoteReady?'ready':'pending',precio_programado:ui.price.value,...projection});
            } catch (_) {config.quoted?.({status:'error',message:'Revisa el importe del nuevo adelanto.'});}
            if (config.changed) { config.changed(); }
        }
        function economy(p) {
            lastPosition = { ...lastPosition, ...p };
            confirmation(lastPosition);
            quote = model.cents(p.precio); balance = model.cents(p.saldo);
            ui.price.value = p.precio; ui.paid.value = p.pago_real; calculate();
        }
        function quoteChanged(price, serviceConfig) {
            if (config.context()?.tipo_contexto === 'cita_existente') { return; }
            if(serviceConfig&&modalityInput){
                modalityInput.value='CONSULTATION';
                const canReevaluate=!serviceConfig.serviceType||serviceConfig.serviceType==='CONSULTATION';
                const aliases=serviceConfig.reevaluationServices||[];
                modalityInput.disabled=!canReevaluate||!config.canCreate;
                const option=modalityInput.querySelector?.('[value="REEVALUATION"]');if(option)option.disabled=!canReevaluate||aliases.length===0;
                if(commercialInput){commercialInput.replaceChildren();aliases.forEach(alias=>{const o=document.createElement('option');o.value=String(alias.id);o.textContent=alias.nombre;commercialInput.appendChild(o);});commercialInput.disabled=true;}
                if(indicationInput){indicationInput.value='';indicationInput.disabled=true;}
                const fields=document.getElementById('agenda-reevaluation-fields');if(fields)fields.hidden=true;
            }
            quoteReady=false;
            quote = Math.round(Number(price || 0) * 100); balance = quote;
            ui.price.value = model.decimal(quote); ui.paid.value = '0.00'; calculate();
            refreshQuote();
        }
        async function refreshQuote(patientId) {
            const version = ++quoteVersion;
            if (activeQuoteRequest) activeQuoteRequest.abort();
            activeQuoteRequest = null;
            if (!config.quoteData || !config.quoteEndpoint || config.context()?.appointment_id) { return; }
            const data = { ...config.quoteData() };
            if (patientId !== undefined) data.patient_id = patientId || 0;
            if (!data.doctor_id || !data.service_id || !data.fecha_cita) {
                quoteReady = false;
                quoteStatus.hidden = true;
                if (quoteRetry) quoteRetry.hidden = true;
                if (config.changed) config.changed();
                config.quoted?.({status:'idle'});
                return;
            }
            if (rateSelect?.value) data.additional_rate_id = rateSelect.value;
            Object.assign(data,medicalPriceInput());
            if (discountInput()) data.discount = discountInput();
            quoteReady = false; automaticFree = false;
            quote = balance = 0;
            ui.price.value = '';
            ui.price.placeholder = 'Esperando cotización';
            quoteStatus.hidden = false; quoteStatus.textContent = 'Consultando precio vigente…';
            config.quoted?.({status:'pending'});
            if (quoteRetry) quoteRetry.hidden = true;
            if (config.changed) config.changed();

            const controller = new AbortController();
            activeQuoteRequest = controller;
            // Network failures must never leave a misleading "Consultando..." indefinitely.
            const timeout = setTimeout(() => controller.abort(), 12000);
            try {
                const result = await request(config.quoteEndpoint, { ...json('POST', data), signal: controller.signal });
                if (version !== quoteVersion || config.context()?.appointment_id) return;
                if (result.precio_programado === undefined || !Number.isFinite(Number(result.precio_programado))
                    || Number(result.precio_programado) < 0) {
                    throw new Error('La respuesta de cotización no contiene un precio válido.');
                }
                automaticFree = result.pricing_rule === 'SAME_DAY_REEVALUATION';
                quote = model.cents(result.precio_programado); balance = quote;
                if (catalogPrice) catalogPrice.value = result.catalog_price ?? result.precio_programado;
                ui.price.value = result.precio_programado; ui.paid.value = '0.00';
                quoteReady = true; quoteStatus.hidden = true; calculate();
                config.quoted?.({...result,status:'ready',paid:'0.00',currentBalance:ui.waived.checked?'0.00':result.precio_programado,advance:ui.amount?.value||'0.00',balance:ui.balance?.value??result.precio_programado});
            } catch (error) {
                if (version !== quoteVersion || config.context()?.appointment_id) return;
                quoteReady = false;
                ui.price.value = '';
                quoteStatus.hidden = false;
                quoteStatus.textContent = error.name === 'AbortError'
                    ? 'La cotización tardó demasiado. Pulsa Reintentar cotización.'
                    : 'No se pudo cotizar: ' + error.message;
                if (quoteRetry) quoteRetry.hidden = false;
                config.quoted?.({status:'error',message:quoteStatus.textContent});
                if (config.changed) config.changed();
            } finally {
                clearTimeout(timeout);
                if (activeQuoteRequest === controller) activeQuoteRequest = null;
            }
        }
        quoteRetry?.addEventListener('click', () => refreshQuote());
        [indicationInput,referredInput,referralOrigin].forEach(field=>field?.addEventListener('input',()=>refreshQuote()));
        modalityInput?.addEventListener('change',()=>{
            const reevaluation=modalityInput.value==='REEVALUATION';
            if(commercialInput)commercialInput.disabled=!reevaluation;
            if(indicationInput){indicationInput.disabled=!reevaluation;if(!reevaluation)indicationInput.value='';}
            const fields=document.getElementById('agenda-reevaluation-fields');if(fields)fields.hidden=!reevaluation;
            refreshQuote();
        });
        commercialInput?.addEventListener('change',()=>refreshQuote());
        [discountType,discountValue].forEach(field => field?.addEventListener('input', () => refreshQuote()));
        if (rateSelect) rateSelect.addEventListener('change', () => refreshQuote());
        ['amount', 'method', 'waived', 'authorized'].forEach(id => ui[id].addEventListener('input', calculate));
        const clearStalePaymentMessages=()=>{
            validation?.clear();
            for(const id of ['agenda-op-payment-error','agenda-op-documents-error']){
                const el=document.getElementById(id);
                if(el){el.hidden=true;el.textContent='';}
            }
        };
        ui.proof.addEventListener('change',clearStalePaymentMessages);
        // Changing an uploaded Yape/transfer proof to cash must not resubmit stale evidence.
        let previousPaymentMethod=ui.method.value;
        ui.method.addEventListener('change',()=>{
            const current=ui.method.value;
            if(current!==previousPaymentMethod)clearStalePaymentMessages();
            if(current==='EFECTIVO' && previousPaymentMethod!=='EFECTIVO' && ui.proof.files?.length){
                void receipt.reset(true);
                config.notice('Comprobante bancario retirado al cambiar a efectivo. El importe puedes registrarlo manualmente.');
            }
            previousPaymentMethod=current;
            calculate();
        });
        ['phone', 'phone-secondary', 'channel', 'medium'].forEach(id => ['input','change'].forEach(event=>ui[id].addEventListener(event, () => { captureDirty = true; })));
        async function patientChanged(id, hydrateIdentity = false) {
            if (String(id || '') === String(captureId || '')) {
                if (hydrateIdentity && capturePatient && config.patientLoaded) { config.patientLoaded(capturePatient); }
                return;
            }
            capturePatient = null;
            captureId = id || null; captureDirty = false; const version = ++captureVersion;
            refreshQuote(id || 0);
            ui['save-patient'].disabled = !id || ui.phone.disabled; inlineStatus('patient', '');
            ['phone', 'phone-secondary', 'channel', 'medium'].forEach(id => { ui[id].value = ''; });
            document.getElementById('agenda-patient-phone-summary').textContent = '—';
            if (!id) { return; }
            try {
                const data = await request(config.patientTemplate.replace('__PATIENT__', encodeURIComponent(id)));
                if (version !== captureVersion || captureDirty) { return; }
                const p = data.patient;
                capturePatient = p;
                if (config.patientLoaded) { config.patientLoaded(p); }
                ui.phone.value = p.telefono || ''; document.getElementById('agenda-patient-phone-summary').textContent = p.telefono || 'Sin celular'; ui['phone-secondary'].value = p.telefono_secundario || '';
                ui.channel.value = p.channel_id || ''; ui.medium.value = p.interaction_medium_id || '';captureClean(['phone','phone-secondary','channel','medium']);
            } catch (e) { config.notice(e.message); }
        }
        async function register(options) {
            if (working) { return; }
            let withoutDepositReason=null;
            if(options.withoutDeposit){
                if(Number(ui.amount.value||0)>0||ui.proof.files?.length||ui.operation.value.trim()||ui.waived.checked){config.notice('Para confirmar sin adelanto, retira el nuevo pago, sus comprobantes y la exoneración.',true);return;}
                if(!root.Swal)return;setWorking(true);
                try{const answer=await root.Swal.fire({target:document.querySelector('.modal.show')||document.body,title:'Guardar y confirmar sin adelanto',allowOutsideClick:false,text:'El horario quedará confirmado, pagado S/ 0 y saldo completo pendiente. No es una exoneración. Indica el motivo de autorización.',input:'textarea',inputAttributes:{maxlength:'500'},inputValidator:v=>String(v||'').trim().length<5?'Indica un motivo de al menos 5 caracteres.':undefined,showCancelButton:true,confirmButtonText:'Guardar y confirmar sin adelanto',cancelButtonText:'Cancelar',focusCancel:true});if(!answer.isConfirmed)return;withoutDepositReason=String(answer.value).trim();}finally{setWorking(false);}
            }
            const reasonText=ui.reason.value.replace(/^[\s\u200B\uFEFF]+|[\s\u200B\uFEFF]+$/g,'');
            const reasonError=byId('reason-error');
            if(!reasonText||reasonText.length>2000){
                const message=!reasonText?'Escribe el motivo de consulta para registrar la cita o reserva.':'El motivo de consulta admite hasta 2000 caracteres.';
                if(reasonError){reasonError.hidden=false;reasonError.textContent=message;}
                ui.reason.setAttribute('aria-invalid','true');ui.reason.scrollIntoView?.({block:'nearest'});ui.reason.focus();config.notice(message,true);return;
            }
            if(reasonError){reasonError.hidden=true;reasonError.textContent='';}ui.reason.setAttribute('aria-invalid','false');
            // "Guardar reserva" never creates a payment/operation from a draft OCR suggestion.
            // Actual advances remain available through the explicit "Registrar adelanto" flow
            // on an existing reservation; that flow preserves all bank duplicate protections.
            if (options.pending) {
                const hasPaymentDraft=Number(ui.amount.value || 0)>0
                    || Boolean(ui.operation.value.trim()) || Boolean(ui.proof.files?.length);
                if (hasPaymentDraft) {
                    if (!root.Swal) { config.notice('Para guardar sin cobrar, limpia primero los datos de pago o confirma la cita.',true);return; }
                    const answer=await root.Swal.fire({
                        title:'Guardar reserva SIN registrar pago',
                        text:'El importe, el N.º de operación y el comprobante seleccionados NO se guardarán como pago. Puedes registrar un adelanto después desde la cita pendiente.',
                        icon:'warning',showCancelButton:true,
                        confirmButtonText:'Guardar solo reserva',
                        cancelButtonText:'Volver al pago',
                    });
                    if (!answer.isConfirmed || working) return;
                }
            }
            validation?.clear();config.clearPatientErrors?.();
            setWorking(true);
            try {
                const context = config.context();
                if (!quoteReady) { throw new Error('Espera el precio vigente antes de registrar.'); }
                const payload = config.payload(context, options.patientId || 1, options.serviceId, options.ownerId);
                registrationKey = registrationKey || root.crypto.randomUUID();
                Object.assign(payload, { request_key: registrationKey, mode: options.pending ? 'RESERVE' : 'CONFIRM',
                    attendance_mode: byId('attendance-mode')?.value || 'PRESENCIAL', booking_type: options.bookingType, motivo_consulta: reasonText, observaciones: ui.note.value || null,
                    es_exonerado: ui.waived.checked, autorizado_por: ui.authorized.value || null,
                    payment: options.pending ? model.payment('0','EFECTIVO','','') : payment(), links: model.links(ui.links.value) });
                if(options.withoutDeposit)Object.assign(payload,{without_deposit:true,confirmed:true,reason:withoutDepositReason,mode:'CONFIRM'});
                payload.patient_id = options.patientId || null;
                Object.assign(payload,medicalPriceInput());
                payload.cita_doble=Boolean(doubleInput?.checked);
                if(payload.cita_doble) payload.duracion_cita=Number(payload.duracion_cita)*2;
                if (discountInput()) payload.discount = discountInput();
                payload.is_postoperative = Boolean(postoperative?.checked); payload.has_diabetic_wound = Boolean(hasWound?.checked);
                payload.motivo_exoneracion = exemptionReason?.value || null;
                if (rateSelect?.value) payload.additional_rate_id = rateSelect.value;
                if (options.patient) { payload.patient = options.patient; }
                if (!options.patient && captureDirty && !ui.phone.disabled) {
                    payload.patient_capture = captureValues();
                }
                // Cash is manual: never attach a stale wallet receipt to a cash booking.
                if(!options.pending && payload.payment.method==='EFECTIVO' && ui.proof.files?.length)await receipt.reset(true);
                const body = new FormData(); if(!options.pending)receipt.append(body,payload); body.append('payload',JSON.stringify(payload));
                const response = await request(config.endpoint, { method: 'POST', body });
                registrationKey = null; ui.amount.value = '0'; await receipt.reset(); ui.links.value = ''; ui.reason.value = ''; ui.note.value = '';
                ui.waived.checked = false; ui.authorized.value = '';
                captureClean();pendingDocumentEdits.clear();await config.registered(response, options);
            } catch (e) {
                rejected(e, 'registration');
                if (options.patient && Object.keys(e.errors || {}).some(key=>key.startsWith('patient.'))) {
                    config.patientErrors?.(e.errors,e.message);
                } else {
                    showPlaced(e, options.bookingType === 'ADICIONAL' ? 'additional' : 'payment');
                }
            }
            finally { setWorking(false); }
        }
        async function documents(id) {
            const version=++documentsVersion;
            const result = await request(base + '/' + id + '/documents');
            if (version!==documentsVersion || String(config.context()?.appointment_id) !== String(id)) { return; }
            ['document-file','document-kind','document-label','links','add-documents'].forEach(name=>byId(name).disabled=!result.can_upload_private);
            ui.documents.replaceChildren();
            const legacyDocumentUpload=byId('legacy-document-upload');if(legacyDocumentUpload)legacyDocumentUpload.hidden=!result.can_upload_private;
            if(root.AppointmentPatientDocuments){if(patientDocumentId!==id){patientDocumentId=id;patientDocumentDirty=false;patientDocuments=root.AppointmentPatientDocuments.create({doc:document,url:base+'/'+id+'/documents',appointmentId:id,initialData:result,readResponse:async response=>{const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||'No se pudo guardar el documento.');return data;},onDirty:value=>{patientDocumentDirty=value;},onBusy:value=>setWorking(value)});}byId('patient-documents').replaceChildren(patientDocuments.element);await patientDocuments.load();}

            const orderDocument=byId('indication-document'); if(orderDocument) orderDocument.replaceChildren();
            (result.documents || []).filter(d=>!['PATIENT_PROVIDED','REEVALUATION_ORDER'].includes(d.purpose)).forEach(d => {
                if(orderDocument && d.download_url) {const option=document.createElement('option');option.value=String(d.id);option.textContent=d.label;orderDocument.appendChild(option);}
                const row = document.createElement('div'); row.className = 'agenda-document-row'; row.dataset.documentId=String(d.id);
                const link = document.createElement('a'); link.textContent = d.label; link.href = d.url || d.download_url;
                link.target = '_blank'; link.rel = 'noopener noreferrer'; row.appendChild(link);
                const kind=document.createElement('small');kind.textContent=' · '+({PRESCRIPTION:'Receta',REPORT:'Informe',ADMINISTRATIVE:'Administrativo'}[d.document_kind]||'Sin clasificar');row.appendChild(kind);
                const label = document.createElement('input'); label.value = d.label; label.maxLength = 120; label.setAttribute('aria-label', 'Etiqueta del documento');
                const url = document.createElement('input'); url.value = d.url || ''; url.setAttribute('aria-label', 'Link HTTPS del documento');
                const pending=pendingDocumentEdits.get(d.id);if(pending){label.value=pending.label;url.value=pending.url;}
                [label,url].forEach(field=>field.addEventListener('input',()=>{if(label.value===d.label && url.value===(d.url||''))pendingDocumentEdits.delete(d.id);else pendingDocumentEdits.set(d.id,{label:label.value,url:url.value});}));
                const save = document.createElement('button'); save.type = 'button'; save.textContent = 'Guardar'; save.className = 'agenda-btn';
                save.addEventListener('click', async () => {
                    try { await request(base + '/' + id + '/documents/' + d.id, json('PUT', d.url ? { label: label.value, url: url.value } : { label: label.value })); pendingDocumentEdits.delete(d.id);await documents(id); }
                    catch (e) { showPlaced(e, 'documents'); }
                });
                const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Quitar'; remove.className = 'agenda-btn';
                remove.addEventListener('click', async () => {
                    try { await request(base + '/' + id + '/documents/' + d.id, { method: 'DELETE' }); await documents(id); }
                    catch (e) { showPlaced(e, 'documents'); }
                });
                if (result.can_write && (d.payment_id || d.can_replace)) { const edit = document.createElement('details'); const summary = document.createElement('summary'); summary.textContent = 'Editar'; edit.append(summary, label); if (d.url) { edit.append(url); } edit.append(save, remove); row.append(edit); }
                if(d.can_replace){const replace=document.createElement('button');replace.type='button';replace.className='agenda-btn';replace.textContent='Reemplazar archivo';replace.addEventListener('click',()=>{
                    if(working || replacementDocument)return;
                    replacementDocument=d;replacementKey=null;byId('replacement-title').textContent='Reemplazar: '+d.label;byId('replacement-panel').hidden=false;
                });row.appendChild(replace);}
                ui.documents.appendChild(row);
            });captureClean(['indication-document']);
        }
        async function selectionChanged(context) {
            validation?.clear();
            const key=contextKey(context);const version=++detailsVersion;
            if(key===selectedKey && hasUnsavedChanges())return;
            if(key!==selectedKey){byId('patient-documents')?.replaceChildren();patientDocuments=null;patientDocumentId=null;patientDocumentDirty=false;clearReplacement();byId('document-kind').value='ADMINISTRATIVE';byId('document-label').value='Documento operativo';}
            if (key !== selectedKey) { await receipt.reset(); if(documentFile)documentFile.value=''; ui.reason.value='';ui.note.value='';ui.method.value='EFECTIVO';ui.operation.value='';ui.origin.value='';ui.price.value='0.00';ui.paid.value='0.00';ui.balance.value='0.00';quote=balance=0;quoteReady=false;pendingDocumentEdits.clear();++documentsVersion;++captureVersion;captureId=null;capturePatient=null;captureDirty=false; ['phone','phone-secondary','channel','medium'].forEach(name=>{ui[name].value='';}); if(doubleInput)doubleInput.checked=false; ['indication-file','indication-date','indication-reference'].forEach(name=>{if(byId(name))byId(name).value='';}); if(byId('indication-attested'))byId('indication-attested').checked=false; if(byId('indication-record-message'))byId('indication-record-message').textContent=''; automaticFree=false; indicationRecordKey=null; if(indicationInput) indicationInput.value=''; if(referredInput) referredInput.checked=false; if(referralOrigin) referralOrigin.value=''; if (discountType) discountType.value=''; if (discountValue) discountValue.value='0'; if (discountReason) discountReason.value=''; if (postoperative) postoperative.checked=false; if(hasWound)hasWound.checked=false; if (exemptionReason) exemptionReason.value=''; selectedKey = key; registrationKey = null; paymentKey = null; ui.amount.value = '0'; ui['payment-panel'].open = false; ui.proof.value = ''; ui.links.value = ''; ui.waived.checked = false; ui.authorized.value = ''; }
            const id = context?.appointment_id;
            if(byId('edit-economy'))byId('edit-economy').hidden=true;
            if (byId('convert-additional')) byId('convert-additional').hidden=true;
            if (byId('conversion-reason')) byId('conversion-reason').hidden=true;
            conversionKey=null;
            [doubleInput,indicationInput,modalityInput,commercialInput,referredInput,referralOrigin].forEach(field=>{if(field) field.disabled=Boolean(id)||!config.canCreate;});
            if(byId('indication-record-panel')) byId('indication-record-panel').hidden=true;
            [discountType,discountValue,discountReason].forEach(field => { if (field) field.disabled = Boolean(id) || !config.canCreate; });
            if (postoperative) postoperative.disabled = Boolean(id); if(hasWound)hasWound.disabled=Boolean(id);
            ++quoteVersion;
            if (activeQuoteRequest) activeQuoteRequest.abort();
            activeQuoteRequest = null;
            if (quoteRetry) quoteRetry.hidden = true;
            if (quoteStatus) quoteStatus.hidden = true;
            if (rateSelect) rateSelect.disabled = Boolean(id);
            ui.waived.disabled = Boolean(id) || initialFinancialLocks.waived;
            ui.authorized.disabled = Boolean(id) || initialFinancialLocks.authorized;
            if (exemptionReason) exemptionReason.disabled = Boolean(id) || initialFinancialLocks.exemption;
            if (id) { quoteReady = true; if (quoteStatus) quoteStatus.hidden = true; }
            ui['submit-payment'].hidden = !id || !config.canCreate; ui['add-documents'].hidden = !id;
            ['document-file','document-kind','document-label','links','add-documents'].forEach(name=>byId(name).disabled=true);
            ui['confirm-reservation'].hidden = !id || context.estado_agenda !== 'PENDIENTE_CONFIRMACION';
            ui['confirm-reservation'].disabled = true; lastPosition = null;
            const confirmationReason = byId('confirmation-reason');
            if (confirmationReason) { confirmationReason.hidden = !id || context.estado_agenda !== 'PENDIENTE_CONFIRMACION'; confirmationReason.textContent = 'Consultando el requisito financiero de la reserva…'; }
            ui.documents.replaceChildren();

            inlineStatus('notes', ''); ui['save-notes'].hidden = true;
            ui.reason.disabled = Boolean(id); ui.note.disabled = Boolean(id);
            byId('attendance-mode').value=context?.attendance_mode||'PRESENCIAL';byId('attendance-mode').disabled=Boolean(id)||!config.canCreate;
            if (!id) { ui.reason.value = ''; ui.note.value = '';captureClean(); byId('notes-tip').textContent = 'Al crear la cita se guardarán estos datos. Los campos vacíos conservan el dato actual.'; return; }
            patientChanged(context.patient_id, true);
            try {
                const p = await request(base + '/' + id + '/economy');
                if (version!==detailsVersion || String(config.context()?.appointment_id) !== String(id)) { return; }
                economy(p);
                const editEconomy=byId('edit-economy');if(editEconomy){editEconomy.hidden=!p.management?.can_adjust_pricing;editEconomy.disabled=false;}
                if(byId('economic-guidance'))byId('economic-guidance').textContent=p.management?.can_adjust_pricing?'Cita existente: utiliza Modificar economía de la cita para revisar tarifa, descuento o recargo, indicar el motivo y confirmar el cambio auditado.':'Cita existente: corregir tarifa, descuento o recargo requiere ADMINISTRADOR con autorización económica. Los pagos se registran por separado.';
                if(doubleInput)doubleInput.checked=Number(p.duration_slots)===2; if(referredInput)referredInput.checked=Boolean(p.referral_origin);if(referralOrigin)referralOrigin.value=p.referral_origin||''; if(byId('indication-record-panel')) byId('indication-record-panel').hidden=!p.can_record_indication;
                if (catalogPrice) catalogPrice.value=p.catalog_price || p.precio;
                if (discountType) discountType.value=p.discount_type || ''; if (discountValue) discountValue.value=p.discount_value || '0';
                if (discountReason) discountReason.value=p.discount_reason || '';
                if (postoperative) { postoperative.checked=Boolean(p.is_postoperative); postoperative.disabled=!p.can_edit_notes; } if(hasWound){hasWound.checked=Boolean(p.has_diabetic_wound);hasWound.disabled=!p.can_edit_notes;}
                if (exemptionReason) exemptionReason.value=p.motivo_exoneracion || '';
                ui.reason.value = p.motivo_consulta || ''; ui.note.value = p.observaciones || '';
                ui.reason.disabled = !p.can_edit_notes; ui.note.disabled = !p.can_edit_notes;
                ui['save-notes'].hidden = !p.can_edit_notes; ui['save-notes'].disabled = !p.can_edit_notes;
                byId('notes-tip').textContent = p.can_edit_notes ? 'Guarda los cambios en esta cita. Los campos vacíos conservan el dato actual.' : 'Esta cita está en consulta: no tienes permiso para modificar sus notas.';
                ui.authorized.value = p.autorizado_por || ''; ui.waived.checked = Boolean(p.es_exonerado);captureClean(['reason','note','has-wound','postoperative','authorized','waived','indication','referred','referral-origin','discount-type','discount-value','discount-reason','exemption-reason','double','rate']);await documents(id);
            } catch (e) { config.notice(e.message, true); }
        }
        async function submitPayment(confirm) {
            validation?.clear();
            const id = config.context()?.appointment_id;
            if (!id || working) { return; }
            if (!confirm) {
                if(lastPosition?.reservation_slot_available===false){config.notice(lastPosition.reservation_block_reason,true);return;}
                let cents = 0;
                try { cents = model.cents(ui.amount.value || '0'); }
                catch (e) { showPlaced(e, 'payment'); return; }
                if (cents < 1) { showPlaced({ message: root.AgendaGuidance.copy.needAmount }, 'payment'); return; }
            }
            setWorking(true);
            try {
                paymentKey = paymentKey || root.crypto.randomUUID();
                const payload = { request_key: paymentKey, confirm, payment: confirm ? model.payment('0', ui.method.value, '', '') : payment() };
                if(lastPosition?.management?.revision)payload.expected_revision=lastPosition.management.revision;
                if(!confirm && payload.payment.method==='EFECTIVO' && ui.proof.files?.length)await receipt.reset(true);
                const body=new FormData(); if(!confirm)receipt.append(body,payload); body.append('payload',JSON.stringify(payload));
                const response = await request(base + '/' + id + '/payments', { method: 'POST', body });
                const currentContext=config.context();
                if(currentContext && String(currentContext.appointment_id)===String(id))currentContext.estado_agenda=response.appointment.estado_agenda;
                ui['confirm-reservation'].hidden=response.appointment.estado_agenda!=='PENDIENTE_CONFIRMACION';
                if(byId('confirmation-reason'))byId('confirmation-reason').hidden=ui['confirm-reservation'].hidden;
                paymentKey = null; ui.amount.value = '0'; if(!confirm)await receipt.reset(); ++detailsVersion;economy(response.economy);captureClean(['amount','method','operation','origin','proof']);
                await config.paymentUpdated(response); config.notice(confirm ? 'Reserva confirmada: ' + response.appointment.tipo_agendamiento : 'Pago registrado y horario confirmado. Saldo: S/ ' + response.economy.saldo);
            } catch (e) { rejected(e, 'payment'); showPlaced(e, 'payment'); }
            finally { setWorking(false); }
        }
        function showPlaced(error, context) {
            if(error.errors?.motivo_consulta){
                const message=error.errors.motivo_consulta[0],fieldError=byId('reason-error');
                if(fieldError){fieldError.hidden=false;fieldError.textContent=message;}ui.reason.setAttribute('aria-invalid','true');ui.reason.scrollIntoView?.({block:'nearest'});ui.reason.focus();config.notice(message,true);return;
            }
            const placed = root.AgendaGuidance.place(error.message, error.status, context);
            const errors = error.errors || {};
            const paymentError = document.getElementById('agenda-op-payment-error');
            const documentError = document.getElementById('agenda-op-documents-error');
            if (paymentError) { paymentError.hidden = true; }
            if (documentError) { documentError.hidden = true; }
            if (placed.domain === 'payment') {
                ui['payment-panel'].open = true;
                if (paymentError) { paymentError.hidden = false; paymentError.textContent = placed.text; }
                validation?.show(errors,placed.text);
                ui['payment-panel'].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                if (!validation) ui.amount.focus();
                if (placed.focus) {
                    ui.amount.classList.add('is-guidance-focus');
                    root.setTimeout(() => ui.amount.classList.remove('is-guidance-focus'), 7000);
                }
                return;
            }
            if (placed.domain === 'documents') {
                const saved = document.getElementById('agenda-op-documents-status');
                if (saved) { saved.hidden = true; }
                const panel = document.getElementById('agenda-op-documents-panel');
                if (panel) { panel.open = true; }
                if (documentError) { documentError.hidden = false; documentError.textContent = placed.text; documentError.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
                return;
            }
            config.notice(placed.text, true);
        }
        ui['submit-payment'].addEventListener('click', () => submitPayment(false));
        let conversionKey = null;
        byId('convert-additional')?.addEventListener('click', async () => {
            const id = config.context()?.appointment_id, version = detailsVersion;
            if (!id || working) return;
            setWorking(true);
            try {
                const url = base + '/' + id + '/additional-conversion', offer = await request(url);
                if (version !== detailsVersion || String(config.context()?.appointment_id) !== String(id)) return;
                if (!offer.available || !offer.can_convert) throw new Error(offer.reason);
                const answer = await root.Swal.fire({title:'¿Conservar esta reserva como ADICIONAL?',
                    text:'Misma cita. Precio: S/ '+offer.price+'. Adelanto mínimo: S/ '+offer.minimum_advance+'. No registra dinero ni confirma. La duración se conserva. Requiere confirmación posterior expresa.',
                    icon:'warning',showCancelButton:true,confirmButtonText:'Convertir a ADICIONAL',cancelButtonText:'Mantener pendiente'});
                if (!answer.isConfirmed || version !== detailsVersion || String(config.context()?.appointment_id) !== String(id)) return;
                conversionKey = conversionKey || root.crypto.randomUUID();
                const response = await request(url, json('POST', {request_key:conversionKey,expected_version:offer.expected_version,confirmed:true}));
                conversionKey = null; ++detailsVersion;
                await config.paymentUpdated(response); config.notice('Reserva conservada como ADICIONAL. No se registró dinero ni se confirmó.');
            } catch (e) { if ([400,403,404,409,422].includes(e.status)) conversionKey=null; config.notice(e.message,true); }
            finally { setWorking(false); }
        });
        ui['confirm-reservation'].addEventListener('click', () => submitPayment(true));
        ui['add-documents'].addEventListener('click', async () => {
            const id = config.context()?.appointment_id;
            if (!id || working) { return; }
            if (root.AgendaGuidance.invalidProof(documentFile.files[0])) { showPlaced({ message: root.AgendaGuidance.copy.fileInvalid }, 'documents'); return; }
            let pending = [];
            try { pending = model.links(ui.links.value); }
            catch (e) { showPlaced(e, 'documents'); return; }
            const hasFile = Boolean(documentFile.files[0]);
            if (!hasFile && pending.length === 0) { showPlaced({ message: root.AgendaGuidance.copy.needDocument }, 'documents'); return; }
            working = true;
            try {
                if (hasFile) {
                    const body = new FormData(); body.append('label', byId('document-label').value); body.append('document_kind',byId('document-kind').value); body.append('file', documentFile.files[0]);
                    await request(base + '/' + id + '/documents', { method: 'POST', body }); documentFile.value = '';
                }
                for (let i = 0; i < pending.length; i++) {
                    await request(base + '/' + id + '/documents', json('POST', {...pending[i],document_kind:byId('document-kind').value}));
                    ui.links.value = pending.slice(i + 1).map(l => l.label + ' | ' + l.url).join('\n');
                }
                await documents(id);captureClean(['document-file','document-kind','document-label','links']);
                const saved = hasFile && pending.length ? root.AgendaGuidance.copy.fileSaved + ' ' + root.AgendaGuidance.copy.linkSaved : (hasFile ? root.AgendaGuidance.copy.fileSaved : root.AgendaGuidance.copy.linkSaved);
                const status = document.getElementById('agenda-op-documents-status');
                const errorBox = document.getElementById('agenda-op-documents-error');
                if (errorBox) { errorBox.hidden = true; errorBox.textContent = ''; }
                if (status) { status.hidden = false; status.textContent = saved; }
                else { config.notice(saved); }
            } catch (e) { showPlaced(e, 'documents'); }
            finally { working = false; }
        });
        byId('replacement-cancel').addEventListener('click',()=>{if(!working)clearReplacement();});
        byId('replacement-save').addEventListener('click',async()=>{
            const id=config.context()?.appointment_id,d=replacementDocument,file=byId('replacement-file').files[0];
            if(!id||!d||working)return;
            if(!file||root.AgendaGuidance.invalidProof(file)){showPlaced({message:root.AgendaGuidance.copy.fileInvalid},'documents');return;}
            if(byId('replacement-reason').value.trim().length<3){showPlaced({message:'Indica el motivo del reemplazo (mínimo 3 caracteres).'},'documents');return;}
            setWorking(true);
            try{
                replacementKey=replacementKey||root.crypto.randomUUID();const body=new FormData();
                Object.entries({request_key:replacementKey,expected_version:d.version,label:d.label,document_kind:d.document_kind==='UNCLASSIFIED'?'ADMINISTRATIVE':d.document_kind,reason:byId('replacement-reason').value}).forEach(([k,v])=>body.append(k,v));body.append('file',file);
                await request(base+'/'+id+'/documents/'+d.id+'/replace',{method:'POST',body});clearReplacement();await documents(id);config.notice('Nueva versión guardada. La anterior se conserva para auditoría.');
            }catch(e){if([400,403,404,413,422].includes(e.status))replacementKey=null;showPlaced(e,'documents');}
            finally{setWorking(false);}
        });
        const recordIndication=byId('record-indication');
        recordIndication?.addEventListener('click',async()=>{
            const id=config.context()?.appointment_id,message=byId('indication-record-message');
            if(!id || working) return;
            indicationRecordKey=indicationRecordKey || root.crypto.randomUUID();
            const payload={request_key:indicationRecordKey,evidence_document_id:Number(byId('indication-document').value),
                indicated_on:byId('indication-date').value,evidence_type:byId('indication-evidence').value,
                instruction_reference:byId('indication-reference').value,doctor_order_attested:Boolean(byId('indication-attested').checked)};
            const file=byId('indication-file').files[0];
            if(file && root.AgendaGuidance.invalidProof(file)){message.textContent=root.AgendaGuidance.copy.fileInvalid;return;}
            let options=json('POST',payload);
            if(file){delete payload.evidence_document_id;const body=new FormData();body.append('payload',JSON.stringify(payload));body.append('file',file);options={method:'POST',body};}
            recordIndication.disabled=true;setWorking(true);
            try {const result=await request(base+'/'+id+'/reevaluation-indications',options);
                indicationRecordKey=null;if(String(config.context()?.appointment_id)!==String(id))return;message.textContent='Indicación acreditada n.º '+result.indication_id+'. Autoriza una sola cita.';
                byId('indication-attested').checked=false;byId('indication-file').value='';await documents(id);captureClean(['indication-file','indication-document','indication-date','indication-evidence','indication-reference','indication-attested']);
            }catch(e){if([400,403,404,422].includes(e.status)) indicationRecordKey=null;message.textContent=e.message;}
            finally{setWorking(false);recordIndication.disabled=false;}
        });
        function openPayment() { ui['payment-panel'].open = true; ui.amount.focus(); ui['payment-panel'].scrollIntoView({ block: 'nearest' }); }
        captureClean();
        return { prepareSelection,confirmDiscard,hasUnsavedChanges,pendingRevision,contextKey,captureClean,register, quoteChanged, patientChanged, selectionChanged, openPayment, readiness, reservationConfirmation, confirmReservation };
    };
}(typeof window !== 'undefined' ? window : globalThis));
