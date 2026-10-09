<details id="agenda-workflow-panel" class="agenda-workflow" hidden>
    <summary>Estado de cita y seguimiento <button type="button" class="agenda-help" data-help-title="Estados de cierre" data-help-text="Retiro: llegó y se fue. No asistió: nunca llegó. Cancelación: paciente u operador autorizado anula el registro sin borrarlo." aria-expanded="false" aria-controls="agenda-help-pop" aria-label="Ayuda: Retiro">?</button></summary>
    <p id="agenda-withdrawal-status" class="agenda-action-notice" role="status" hidden></p>
    <p id="agenda-withdrawal-error" class="agenda-field-error" role="alert" hidden></p>
    <p id="agenda-workflow-title"></p>
    <div id="agenda-workflow-events"></div>
    <label class="agenda-field"><span>Motivo operativo</span><textarea id="agenda-withdrawal-reason" class="agenda-field__input" maxlength="2000" rows="2"></textarea></label>
    <div id="agenda-withdrawal-current">
        <label class="agenda-field"><span>Operación</span><select id="agenda-ending-kind" class="agenda-field__input">
            <option value="withdraw" @disabled(!$canWithdraw)>RETIRO · llegó y se fue</option>
            <option value="no-show" @disabled(!$canNoShow)>NO ASISTIÓ · nunca llegó</option>
            <option value="cancel" @disabled(!$canCancel)>CANCELACIÓN · paciente u operador autorizado</option>
        </select></label>
        <p id="agenda-ending-help" class="agenda-guidance">RETIRO exige presencia. La cita original se conserva.</p>
        <label id="agenda-withdrawal-presence-field"><input id="agenda-withdrawal-present" type="checkbox"> El paciente estuvo en la clínica y se retira</label>
        <label id="agenda-withdrawal-action-field" class="agenda-field"><span>Acción solicitada</span><select id="agenda-withdrawal-action" class="agenda-field__input"><option value="PENDIENTE">Seguimiento pendiente</option><option value="REPROGRAMAR">Reprogramar en una nueva cita</option><option value="DEVOLUCION">Solicitar devolución</option></select></label>
        <p id="agenda-withdrawal-refund-hint" class="agenda-guidance" hidden>Solo se registra la solicitud. El dinero todavía no se devuelve.</p>
        <button id="agenda-withdrawal-submit" class="agenda-btn" type="button">Registrar operación</button>
    </div>
    <div id="agenda-withdrawal-followup" hidden>
        <p>La cita original conserva fecha e historia. Selecciona solo el dinero disponible que deseas aplicar o reservar.</p>
        <div id="agenda-withdrawal-credit"></div>
        <div class="agenda-patient-form-grid">
            <label class="agenda-field"><span>Nueva fecha</span><input id="agenda-withdrawal-date" type="date" class="agenda-field__input"></label>
            <label class="agenda-field"><span>Hora · HH:MM</span><input id="agenda-withdrawal-time" type="text" inputmode="numeric" placeholder="10:20" class="agenda-field__input"></label>
            <label class="agenda-field"><span>Duración · minutos</span><input id="agenda-withdrawal-duration" type="number" min="1" max="255" class="agenda-field__input"></label>
            <label class="agenda-field"><span>Tipo explícito de nueva cita</span><select id="agenda-withdrawal-type" class="agenda-field__input"><option value="REGULAR">Regular</option>@if($canCreateAdditional)<option value="ADICIONAL">Adicional</option>@endif<option value="FUERA_HORARIO">Fuera de horario</option></select></label>
        </div>
        <small>Mismo paciente, médico y servicio; precio del catálogo vigente. Si el crédito no asegura la cita, se guarda como reserva privada.</small>
        <div class="agenda-workflow-actions">
            <button id="agenda-withdrawal-rebook" class="agenda-btn" type="button" @disabled(!$canCreateAppointments || !$canRescheduleAppointments)>Crear nueva cita y aplicar crédito</button>
            <button id="agenda-withdrawal-refund" class="agenda-btn" type="button" @disabled(!$canWithdraw)>Solicitar devolución</button>
        </div>
        <div id="agenda-withdrawal-refunds"></div>
    </div>
</details>
