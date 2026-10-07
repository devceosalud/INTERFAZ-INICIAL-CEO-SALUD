<div id="agenda-operational-fields" class="agenda-operational-fields">
    <details id="agenda-op-capture-panel">
        <summary>Más datos del paciente</summary>
        <div class="agenda-patient-form-grid">
            <label class="agenda-field"><span>Celular principal</span><input id="agenda-op-phone" class="agenda-field__input" type="tel" maxlength="32" @disabled(!$canWritePatients)></label>
            <label class="agenda-field"><span>Celular secundario · opcional</span><input id="agenda-op-phone-secondary" class="agenda-field__input" type="tel" maxlength="32" @disabled(!$canWritePatients)></label>
            <label class="agenda-field"><span>Canal · origen/captación</span><select id="agenda-op-channel" class="agenda-field__input" @disabled(!$canWritePatients)><option value="">Sin indicar</option>@foreach($channels as $channel)<option value="{{ $channel->id }}">{{ $channel->nombre }}</option>@endforeach</select></label>
            <label class="agenda-field"><span>Medio de contacto</span><select id="agenda-op-medium" class="agenda-field__input" @disabled(!$canWritePatients)><option value="">Sin indicar</option>@foreach($interactionMedia as $medium)<option value="{{ $medium->id }}">{{ $medium->nombre }}</option>@endforeach</select></label>
        </div>
    </details>
    <details>
        <summary>Motivo y observación · opcional</summary>
        <label class="agenda-field"><span>Motivo de consulta</span><textarea id="agenda-op-reason" class="agenda-field__input" rows="2" maxlength="2000"></textarea></label>
        <label class="agenda-field"><span>Observación operativa</span><textarea id="agenda-op-note" class="agenda-field__input" rows="2" maxlength="2000"></textarea></label>
    </details>
    <details id="agenda-op-payment-panel">
        <summary>Registrar adelanto</summary>
        <div class="agenda-patient-form-grid">
            <label class="agenda-field"><span>Precio de consulta</span><input id="agenda-op-price" class="agenda-field__input" value="0.00" readonly></label>
            <label class="agenda-field"><span>Pagado</span><input id="agenda-op-paid" class="agenda-field__input" value="0.00" readonly></label>
            <label class="agenda-field"><span>Adelanto</span><input id="agenda-op-amount" class="agenda-field__input" type="number" min="0" step="0.01" value="0" @disabled(!$canSubmitPayment)></label>
            <label class="agenda-field"><span>Saldo pendiente</span><input id="agenda-op-balance" class="agenda-field__input" value="0.00" readonly></label>
            <label class="agenda-field"><span>Medio de pago</span><select id="agenda-op-method" class="agenda-field__input" @disabled(!$canSubmitPayment)>@foreach(\App\Services\Billing\VoucherPaymentRecorder::METHODS as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach</select></label>
            <label class="agenda-field"><span>N.º de operación</span><input id="agenda-op-operation" class="agenda-field__input" maxlength="120" disabled></label>
            <label class="agenda-field"><span>Banco / billetera</span><input id="agenda-op-origin" class="agenda-field__input" maxlength="120" placeholder="BCP, BBVA…" @disabled(!$canSubmitPayment)></label>
            <label class="agenda-field"><span>Autorizado por</span><input id="agenda-op-authorized" class="agenda-field__input" maxlength="255" @disabled(!$canAuthorize)></label>
        </div>
        <label><input id="agenda-op-waived" type="checkbox" @disabled(!$canWaive)> Autorización / exoneración</label>
        <small>La autorización no registra dinero. Para asegurar una regular se necesita adelanto real ≥50%.</small>
        @unless($canSubmitPayment)<p>No tienes permiso para registrar adelantos. Puedes guardar una reserva.</p>@endunless
        <label class="agenda-field agenda-upload"><span>+ Subir comprobante · JPG, PNG o PDF, hasta 8 MB</span><input id="agenda-op-proof" type="file" accept="image/jpeg,image/png,application/pdf"></label>
        <button id="agenda-op-submit-payment" class="agenda-btn" type="button" hidden @disabled(!$canSubmitPayment)>Registrar adelanto</button>
        <button id="agenda-op-confirm-reservation" class="agenda-btn" type="button" hidden>Confirmar agenda</button>
    </details>
    <details id="agenda-op-documents-panel">
        <summary>Comprobantes y documentos</summary>
        <label class="agenda-field"><span>+ Agregar link · etiqueta | URL HTTPS</span><textarea id="agenda-op-links" class="agenda-field__input" rows="2" placeholder="Documento | https://drive.google.com/…"></textarea></label>
        <button id="agenda-op-add-documents" class="agenda-btn" type="button" hidden>Adjuntar a esta cita</button>
        <div id="agenda-op-documents" aria-live="polite"></div>
    </details>
</div>
