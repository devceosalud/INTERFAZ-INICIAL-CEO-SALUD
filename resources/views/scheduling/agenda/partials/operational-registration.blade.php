<div id="agenda-operational-fields" class="agenda-operational-fields">
    <label class="agenda-field"><span>Modalidad de atención</span><select id="agenda-op-attendance-mode" class="agenda-field__input"><option value="PRESENCIAL">Presencial</option><option value="TELECONSULTA">Teleconsulta</option></select></label>
    <details id="agenda-op-capture-panel">
        <summary>Más datos del paciente</summary>
        <div class="agenda-patient-form-grid">
            <label class="agenda-field"><span>Celular principal</span><input id="agenda-op-phone" class="agenda-field__input" type="tel" maxlength="32" @disabled(!$canWritePatients)></label>
            <label class="agenda-field"><span>Celular secundario · opcional</span><input id="agenda-op-phone-secondary" class="agenda-field__input" type="tel" maxlength="32" @disabled(!$canWritePatients)></label>
            <label class="agenda-field"><span>Canal · origen/captación</span><select id="agenda-op-channel" class="agenda-field__input" @disabled(!$canWritePatients)><option value="">Sin indicar</option>@foreach($channels as $channel)<option value="{{ $channel->id }}">{{ $channel->nombre }}</option>@endforeach</select></label>
            <label class="agenda-field"><span>Medio de contacto</span><select id="agenda-op-medium" class="agenda-field__input" @disabled(!$canWritePatients)><option value="">Sin indicar</option>@foreach($interactionMedia as $medium)<option value="{{ $medium->id }}">{{ $medium->nombre }}</option>@endforeach</select></label>
        </div>
        <div class="agenda-op-save-row">
            <button id="agenda-op-save-patient" class="agenda-btn" type="button" disabled @if(!$canWritePatients) hidden @endif>Guardar datos del paciente</button>
            <small>Los campos vacíos conservan el dato actual. Para corregir o quitar datos, usa Completar ficha.</small>
        </div>
        <p id="agenda-op-patient-status" class="agenda-action-notice" role="status" hidden></p>
        <p id="agenda-op-patient-error" class="agenda-field-error" role="alert" hidden></p>
    </details>
    <label><input id="agenda-op-double" type="checkbox"> Cita extendida · dos intervalos consecutivos, una sola consulta</label>
    <details id="agenda-op-notes-panel">
        <summary>Observación y datos complementarios · opcional</summary>
        <label class="agenda-field"><span>Observación operativa</span><textarea id="agenda-op-note" class="agenda-field__input" rows="2" maxlength="2000"></textarea></label>
        <label><input id="agenda-op-postoperative" type="checkbox"> Cita postoperatoria · no implica gratuidad</label>
        <label><input id="agenda-op-has-wound" type="checkbox"> Pie diabético con herida · selección explícita para esta cita</label>
        <button id="agenda-op-save-notes" class="agenda-btn" type="button" hidden>Guardar motivo y observación</button>
        <small id="agenda-op-notes-tip">Al crear la cita se guardarán estos datos. Los campos vacíos conservan el dato actual.</small>
        <p id="agenda-op-notes-status" class="agenda-action-notice" role="status" hidden></p>
        <p id="agenda-op-notes-error" class="agenda-field-error" role="alert" hidden></p>
    </details>
    <details id="agenda-op-payment-panel">
        <summary>Registrar adelanto</summary>
        <div id="agenda-op-payment-summary" class="agenda-error-summary" role="alert" tabindex="-1" hidden></div>
        <p class="agenda-payment-intro">Registra un pago real. Primero verifica el importe y la operación.</p>
        <div class="agenda-payment-totals">
            <label class="agenda-field"><span>Precio de consulta</span><input id="agenda-op-price" class="agenda-field__input" value="0.00" readonly></label>
            <label class="agenda-field"><span>Ya abonado</span><input id="agenda-op-paid" class="agenda-field__input" value="0.00" readonly></label>
            <label class="agenda-field"><span>Saldo pendiente</span><input id="agenda-op-balance" class="agenda-field__input" value="0.00" readonly></label>
        </div>
        <p id="agenda-op-quote-status" class="agenda-field-error" role="status" hidden></p>
        <button type="button" id="agenda-op-quote-retry" class="agenda-btn" hidden>Reintentar cotización</button>
        <div class="agenda-payment-entry">
            <label class="agenda-field"><span>Importe de este adelanto (S/)</span><input id="agenda-op-amount" class="agenda-field__input" type="number" min="0" step="0.01" value="0" @disabled(!$canSubmitPayment)></label>
            <label class="agenda-field"><span>Medio de pago</span><select id="agenda-op-method" class="agenda-field__input" @disabled(!$canSubmitPayment)>@foreach(\App\Services\Billing\VoucherPaymentRecorder::METHODS as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach</select></label>
            <label class="agenda-field agenda-tip" id="agenda-op-operation-tip"><span>N.º de operación</span><input id="agenda-op-operation" class="agenda-field__input" maxlength="120" disabled></label>
            <label class="agenda-field"><span>Banco / billetera</span><input id="agenda-op-origin" class="agenda-field__input" maxlength="120" placeholder="Yape, Plin, BCP…" @disabled(!$canSubmitPayment)></label>
        </div>
        <section class="agenda-payment-proof" aria-label="Comprobante y lectura OCR">
            <h3 class="agenda-section-title">Comprobante de pago</h3>
            <label class="agenda-field agenda-upload"><span>Adjuntar imagen o comprobante (opcional)</span><input id="agenda-op-proof" type="file" multiple accept="image/jpeg,image/png,application/pdf"><small>Hasta 5 archivos de 8 MB; los PDF se revisan manualmente.</small></label>
            <div id="agenda-op-receipt-review"></div>
        </section>
        <details id="agenda-op-advanced" class="agenda-payment-advanced">
            <summary>Tarifas, descuentos y autorizaciones · opciones avanzadas</summary>
            <div class="agenda-patient-form-grid">
                <label class="agenda-field"><span><input id="agenda-op-referred" type="checkbox"> Referido acreditado del Dr. Julio Quiroz</span><input id="agenda-op-referral-origin" class="agenda-field__input" maxlength="255" placeholder="Origen verificable del referido"><small>Solo Traumatología y Pie Diabético; origen obligatorio.</small></label>
            </div>
            <label class="agenda-field"><span>Recargo opcional (se suma al precio)</span><select id="agenda-op-rate" class="agenda-field__input"><option value="">Sin recargo</option>@foreach(($additionalRates ?? []) as $rate)<option value="{{ $rate->id }}">{{ $rate->nombre }}</option>@endforeach</select></label>
            <div class="agenda-patient-form-grid">
                <label class="agenda-field"><span>Descuento</span><select id="agenda-op-discount-type" class="agenda-field__input" @disabled(!$canCreateAppointments)><option value="">Sin descuento</option><option value="FIXED">Importe S/</option><option value="PERCENT">Porcentaje %</option></select></label>
                <label class="agenda-field"><span>Valor de descuento</span><input id="agenda-op-discount-value" class="agenda-field__input" type="number" min="0" step="0.01" value="0" @disabled(!$canCreateAppointments)></label>
                <label class="agenda-field"><span>Referencia de descuento · opcional</span><input id="agenda-op-discount-reason" class="agenda-field__input" maxlength="500" @disabled(!$canCreateAppointments)></label>
                <label class="agenda-field"><span>Precio antes de descuento</span><input id="agenda-op-catalog-price" class="agenda-field__input" readonly value="0.00"></label>
                <label class="agenda-field"><span>Autorizado por</span><input id="agenda-op-authorized" class="agenda-field__input" maxlength="255" @disabled(!$canAuthorize)></label>
                <label class="agenda-field"><span>Motivo de exoneración</span><textarea id="agenda-op-exemption-reason" class="agenda-field__input" rows="2" maxlength="2000" @disabled(!$canWaive)></textarea></label>
            </div>
            <label><input id="agenda-op-waived" type="checkbox" @disabled(!$canWaive)> Autorización / exoneración</label>
            <p class="agenda-payment-help">La exoneración requiere permisos y autorización expresa; el OCR no acredita dinero.</p>
        </details>
        <p class="agenda-payment-help">Guardar reserva no confirma el horario. Para confirmar se requiere un pago real positivo o exoneración autorizada.</p>
        <p id="agenda-op-payment-error" class="agenda-field-error" role="alert" hidden></p>
        @unless($canSubmitPayment)<p class="agenda-payment-help">No tienes autorización para registrar adelantos. Puedes guardar una reserva sin pago si tu perfil lo permite.</p>@endunless
        <div class="agenda-payment-actions">
            <button id="agenda-op-submit-payment" class="agenda-btn" type="button" hidden @disabled(!$canSubmitPayment)>Registrar adelanto</button>
            <button id="agenda-op-confirm-reservation" class="agenda-btn" type="button" hidden aria-describedby="agenda-op-confirmation-reason">Confirmar autorización</button>
            <button id="agenda-op-convert-additional" class="agenda-btn" type="button" hidden>Resolver como ADICIONAL</button>
        </div>
        <p id="agenda-op-confirmation-reason" class="agenda-guidance">Un nuevo pago real positivo confirma automáticamente la reserva.</p>
        <p id="agenda-op-conversion-reason" class="agenda-guidance" hidden></p>
    </details>
    <details id="agenda-op-indication-record-panel" hidden>
        <summary>Registrar indicación escrita del médico tratante</summary>
        <p>La indicación pertenece al médico de esta atención realizada. Sube su orden escrita en esta operación o selecciona evidencia ya privada; no se admite evidencia verbal ni una solicitud del paciente.</p>
        <label class="agenda-field"><span>Subir evidencia privada · JPG, PNG o PDF, hasta 8 MB</span><input id="agenda-op-indication-file" type="file" accept="image/jpeg,image/png,application/pdf"></label>
        <label class="agenda-field"><span>O usar documento privado de esta cita</span><select id="agenda-op-indication-document" class="agenda-field__input"></select></label>
        <label class="agenda-field"><span>Tipo de evidencia</span><select id="agenda-op-indication-evidence" class="agenda-field__input"><option value="WRITTEN_ORDER">Orden escrita</option><option value="PRESCRIPTION">Receta</option><option value="REPORT">Informe</option><option value="AUTHENTICATED_DOCTOR_RECORD">Registro autenticado del médico</option></select></label>
        <label class="agenda-field"><span>Referencia operativa de la indicación · folio / número, sin diagnóstico</span><input id="agenda-op-indication-reference" class="agenda-field__input" maxlength="120"></label>
        <label class="agenda-field"><span>Fecha de la indicación</span><input id="agenda-op-indication-date" class="agenda-field__input" type="date"></label>
        <label><input id="agenda-op-indication-attested" type="checkbox"> Acredito que el documento contiene la indicación del médico tratante.</label>
        <button id="agenda-op-record-indication" class="agenda-btn" type="button">Registrar indicación</button>
        <p id="agenda-op-indication-record-message" role="status"></p>
    </details>
    <details id="agenda-op-documents-panel">
        <summary>Documentos de la cita</summary>
        <div id="agenda-op-patient-documents"></div>
        <details id="agenda-op-legacy-document-upload" @unless(\App\Support\Scheduling\AppointmentDocumentAccess::write(auth()->user())) hidden @endunless>
        <summary>Otros documentos operativos</summary>
        <label class="agenda-field"><span>Tipo de documento privado</span><select id="agenda-op-document-kind"><option value="ADMINISTRATIVE">Administrativo</option><option value="PRESCRIPTION">Receta</option><option value="REPORT">Informe</option></select></label>
        <label class="agenda-field"><span>Etiqueta del documento</span><input id="agenda-op-document-label" maxlength="120" value="Documento operativo"></label>
        <label class="agenda-field"><span>Documento operativo · JPG, PNG o PDF, hasta 8 MB</span><input id="agenda-op-document-file" type="file" accept="image/jpeg,image/png,application/pdf"></label>
        <label class="agenda-field"><span>+ Agregar link · etiqueta | URL HTTPS</span><textarea id="agenda-op-links" class="agenda-field__input" rows="2" placeholder="Documento | https://drive.google.com/…"></textarea></label>
        <button id="agenda-op-add-documents" class="agenda-btn" type="button" hidden>Adjuntar a esta cita</button>
        </details>
        <p id="agenda-op-documents-status" class="agenda-action-notice" role="status" hidden></p>
        <p id="agenda-op-documents-error" class="agenda-field-error" role="alert" hidden></p>
        <div id="agenda-op-documents" aria-live="polite"></div>
        <div id="agenda-op-replacement-panel" hidden>
            <p id="agenda-op-replacement-title"></p>
            <label class="agenda-field"><span>Nueva versión privada · JPG, PNG o PDF, hasta 8 MB</span><input id="agenda-op-replacement-file" type="file" accept="image/jpeg,image/png,application/pdf"></label>
            <label class="agenda-field"><span>Motivo obligatorio del reemplazo</span><input id="agenda-op-replacement-reason" minlength="3" maxlength="500"></label>
            <button id="agenda-op-replacement-save" type="button" class="agenda-btn">Guardar nueva versión</button>
            <button id="agenda-op-replacement-cancel" type="button" class="agenda-btn">Cancelar reemplazo</button>
            <p>La versión anterior y su archivo se conservan para auditoría. Esto no modifica pagos ni acredita una indicación médica.</p>
        </div>
    </details>
</div>
