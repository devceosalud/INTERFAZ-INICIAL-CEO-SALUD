<section class="agenda-operations__section agenda-quick" id="agenda-quick-registration"
    aria-labelledby="agenda-quick-title">
    <div class="agenda-pane-head">
        <div>
            <h2 class="agenda-section-title" id="agenda-quick-title">Registro rápido</h2>
            <p>Contexto preparado; sin guardado en MVP-2C</p>
        </div>
        <span class="agenda-quick__mode" id="agenda-quick-mode">Sin selección</span>
    </div>

    <div class="agenda-quick__body">
        <form class="agenda-lookup" id="agenda-patient-lookup" autocomplete="off"
            data-endpoint="{{ route('scheduling.mvp.agenda.patient-lookup') }}">
            <label class="agenda-field">
                <span class="agenda-field__label">Tipo documento</span>
                <select class="agenda-field__input" id="agenda-document-type" name="tipo_identificacion">
                    <option value="DNI">DNI</option>
                    <option value="CARNET EXTRANJERIA">Carné de extranjería</option>
                    <option value="PTP">PTP</option>
                    <option value="TAM">TAM</option>
                    <option value="RUC">RUC</option>
                    <option value="PASAPORTE">Pasaporte</option>
                    <option value="SALVOCONDUCTO">Salvoconducto</option>
                    <option value="SIN DOCUMENTOS">Sin documentos</option>
                </select>
            </label>
            <label class="agenda-field">
                <span class="agenda-field__label">Número documento</span>
                <input class="agenda-field__input" id="agenda-document-number" name="numero_identidad"
                    type="text" inputmode="text" maxlength="255" autocomplete="off" spellcheck="false">
            </label>
            <button type="submit" class="agenda-btn agenda-btn--primary" id="agenda-document-search">Buscar</button>
        </form>

        <p class="agenda-lookup__result" id="agenda-patient-lookup-result" role="status" aria-live="polite"></p>

        <div class="agenda-quick__patient">
            <p>
                <span>Paciente</span>
                <strong id="agenda-quick-patient-state">Sin paciente seleccionado</strong>
            </p>
            <p>
                <span>H.C.E.</span>
                <strong id="agenda-lookup-clinical-record">—</strong>
            </p>
            <input type="hidden" id="agenda-quick-patient-id" value="">
        </div>

        <button type="button" class="agenda-btn" id="agenda-patient-register" disabled hidden
            title="Abre el alta dentro de Agenda. Todavía no guarda al paciente.">
            Registrar paciente
        </button>

        <form class="agenda-draft" id="agenda-patient-draft" hidden autocomplete="off"
            data-endpoint="{{ route('scheduling.mvp.agenda.reniec-lookup') }}">
            <p class="agenda-draft__title">Nuevo paciente</p>
            <p class="agenda-draft__note" id="agenda-draft-note">Este registro todavía no se guarda.</p>
            <p class="agenda-draft__note" id="agenda-draft-ruc" hidden>
                RUC se registra de forma manual. La consulta de empresa queda pendiente de validación.
            </p>

            <div class="agenda-draft__grid">
                <label class="agenda-field">
                    <span class="agenda-field__label">Tipo</span>
                    <input class="agenda-field__input" id="agenda-draft-type" type="text" readonly>
                </label>
                <label class="agenda-field">
                    <span class="agenda-field__label">Número</span>
                    <input class="agenda-field__input" id="agenda-draft-number" type="text" readonly>
                </label>
                <label class="agenda-field">
                    <span class="agenda-field__label">Nombre</span>
                    <input class="agenda-field__input" id="agenda-draft-nombre" type="text" autocomplete="off">
                </label>
                <label class="agenda-field">
                    <span class="agenda-field__label">Apellido paterno</span>
                    <input class="agenda-field__input" id="agenda-draft-apellido-paterno" type="text" autocomplete="off">
                </label>
                <label class="agenda-field">
                    <span class="agenda-field__label">Apellido materno</span>
                    <input class="agenda-field__input" id="agenda-draft-apellido-materno" type="text" autocomplete="off">
                </label>
                <label class="agenda-field">
                    <span class="agenda-field__label">Teléfono</span>
                    <input class="agenda-field__input" id="agenda-draft-telefono" type="text" autocomplete="off">
                </label>
                <label class="agenda-field">
                    <span class="agenda-field__label">Email</span>
                    <input class="agenda-field__input" id="agenda-draft-email" type="text" autocomplete="off">
                </label>
                <label class="agenda-field">
                    <span class="agenda-field__label">Fecha de nacimiento</span>
                    <input class="agenda-field__input" id="agenda-draft-fecha-nacimiento" type="date">
                </label>
                <label class="agenda-field">
                    <span class="agenda-field__label">Género</span>
                    <select class="agenda-field__input" id="agenda-draft-genero">
                        <option value="">Sin indicar</option>
                        <option value="HOMBRE">Hombre</option>
                        <option value="MUJER">Mujer</option>
                    </select>
                </label>
                <label class="agenda-field">
                    <span class="agenda-field__label">Estado civil</span>
                    <input class="agenda-field__input" id="agenda-draft-estado-civil" type="text" autocomplete="off">
                </label>
                <label class="agenda-field agenda-draft__wide">
                    <span class="agenda-field__label">Dirección</span>
                    <input class="agenda-field__input" id="agenda-draft-direccion" type="text" autocomplete="off">
                </label>
                <label class="agenda-field agenda-draft__wide">
                    <span class="agenda-field__label">Motivo de la nueva cita</span>
                    <input class="agenda-field__input" id="agenda-draft-motivo" type="text" autocomplete="off">
                </label>
            </div>

            <p class="agenda-draft__message" id="agenda-draft-message" role="status" aria-live="polite"></p>
            <div class="agenda-draft__actions">
                <button type="button" class="agenda-btn" id="agenda-draft-reniec" hidden>
                    Consultar RENIEC
                </button>
                <button type="button" class="agenda-btn" id="agenda-draft-cancel">Cancelar registro</button>
            </div>
        </form>

        <dl class="agenda-quick__context">
            <div><dt>ID paciente</dt><dd id="agenda-quick-patient-id-display">—</dd></div>
            <div><dt>Médico</dt><dd id="agenda-quick-doctor">—</dd></div>
            <div><dt>Especialidad</dt><dd id="agenda-quick-specialty">—</dd></div>
            <div><dt>Fecha</dt><dd id="agenda-quick-date">—</dd></div>
            <div><dt>Hora</dt><dd id="agenda-quick-time">Seleccione un intervalo</dd></div>
            <div><dt>Duración</dt><dd id="agenda-quick-duration">—</dd></div>
            <div><dt>Sede</dt><dd id="agenda-quick-site">—</dd></div>
            <div><dt>Servicio</dt><dd id="agenda-quick-service">Pendiente de selección</dd></div>
            <div><dt>Estado</dt><dd id="agenda-quick-status">Sin cita</dd></div>
            <div><dt>Pago</dt><dd id="agenda-quick-payment">—</dd></div>
            <div><dt>H.C.</dt><dd id="agenda-quick-clinical-record">—</dd></div>
            <div><dt>Operador</dt><dd>{{ auth()->user()->name ?? auth()->user()->nombre ?? 'Usuario autenticado' }}</dd></div>
        </dl>

        <p class="agenda-quick__message" id="agenda-quick-message">
            Seleccione médico, fecha e intervalo disponible. Esta pantalla no crea ni modifica citas.
        </p>

        <button type="button" class="agenda-btn agenda-complete-registration"
            id="agenda-complete-registration" disabled hidden
            title="La verificación por DNI y el completado funcional pertenecen a MVP-3">
            Completar registro
        </button>
        <p class="agenda-complete-registration__help" id="agenda-complete-registration-help" hidden>
            Disponible funcionalmente en MVP-3 — DNI + paciente rápido/completar registro.
        </p>

        <details class="agenda-tools">
            <summary>Validar cruce de horario</summary>
            <form class="agenda-overlap" id="agenda-overlap-form"
                data-endpoint="{{ route('scheduling.mvp.schedule.overlap') }}">
                <div class="agenda-overlap__row">
                    <label class="agenda-field">
                        <span class="agenda-field__label">Inicio</span>
                        <input type="time" class="agenda-field__input" id="agenda-overlap-start" required>
                    </label>
                    <label class="agenda-field">
                        <span class="agenda-field__label">Fin</span>
                        <input type="time" class="agenda-field__input" id="agenda-overlap-end" required>
                    </label>
                </div>
                <button type="submit" class="agenda-btn agenda-btn--primary" id="agenda-overlap-submit">Comprobar cruce</button>
            </form>
            <p class="agenda-overlap__result" id="agenda-overlap-result" role="status" aria-live="polite"></p>
            <a class="agenda-link" href="{{ route('admissionit.doctor.schedule.index') }}" id="agenda-schedule-link">
                Configurar horarios médicos
            </a>
        </details>
    </div>
</section>
