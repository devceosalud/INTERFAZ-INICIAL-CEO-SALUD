<details id="agenda-workflow-panel" class="agenda-workflow" hidden>
    <summary>Retiro y seguimiento</summary>
    <p id="agenda-workflow-title"></p>
    <div id="agenda-workflow-events"></div>
    <label class="agenda-field"><span>Motivo operativo</span><textarea id="agenda-withdrawal-reason" class="agenda-field__input" maxlength="2000" rows="2"></textarea></label>
    <div id="agenda-withdrawal-current">
        <label><input id="agenda-withdrawal-present" type="checkbox"> El paciente estuvo en la clínica y se retira</label>
        <label class="agenda-field"><span>Acción solicitada</span><select id="agenda-withdrawal-action" class="agenda-field__input"><option value="PENDIENTE">Seguimiento pendiente</option><option value="REPROGRAMAR">Reprogramar en una nueva cita</option><option value="DEVOLUCION">Solicitar devolución</option></select></label>
        <button id="agenda-withdrawal-submit" class="agenda-btn" type="button" @disabled(!$canWithdraw)>Registrar retiro</button>
        @unless($canWithdraw)<small>No tienes permiso para registrar el retiro.</small>@endunless
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
            <button id="agenda-withdrawal-refund" class="agenda-btn" type="button" @disabled(!$canWithdraw)>Solicitar devolución · sin procesar pago</button>
        </div>
        <div id="agenda-withdrawal-refunds"></div>
    </div>
</details>
