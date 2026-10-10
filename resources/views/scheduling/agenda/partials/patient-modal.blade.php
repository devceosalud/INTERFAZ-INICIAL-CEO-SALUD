<div class="agenda-patient-modal" id="agenda-patient-modal" hidden aria-hidden="true">
    <div class="agenda-patient-modal__backdrop" data-modal-close></div>
    <section class="agenda-patient-dialog" data-ui-screen="agenda" data-ui-zone="dialog" role="dialog" aria-modal="true" aria-labelledby="agenda-patient-modal-title">
        <header class="agenda-patient-dialog__head">
            <div>
                <p class="agenda-patient-dialog__eyebrow">Agenda / Paciente</p>
                <h2 id="agenda-patient-modal-title">Registrar paciente</h2>
            </div>
            <div class="agenda-patient-dialog__hce">
                <span>HCE</span>
                <strong id="agenda-draft-hce">—</strong>
                <small id="agenda-draft-hce-note">La HCE real se confirma después de guardar</small>
            </div>
            <button type="button" class="agenda-patient-dialog__close" id="agenda-draft-close" aria-label="Cerrar">×</button>
        </header>

        <form id="agenda-patient-draft" autocomplete="off"
            data-reniec-endpoint="{{ route('scheduling.mvp.agenda.reniec-lookup') }}"
            data-detail-template="{{ url('/patients/__PATIENT__') }}"
            data-store-endpoint="{{ route('patients.operational.store') }}"
            data-update-template="{{ url('/patients/__PATIENT__') }}"
            data-hce-supported-types='@json(\App\Support\Patients\PatientClinicalHistoryNumber::supportedDocumentTypes())'
            data-can-write="{{ $canWritePatients ? '1' : '0' }}"
            data-can-schedule="{{ $canCreateAppointments ? '1' : '0' }}">
            <div class="agenda-patient-dialog__body">
                <p class="agenda-patient-dialog__notice" id="agenda-draft-note">
                    Registra los datos básicos. Puedes completar la ficha después.
                </p>
                <p class="agenda-patient-dialog__notice is-warning" id="agenda-draft-ruc" hidden>
                    RUC se registra manualmente, no utiliza RENIEC y todavía no tiene una regla HCE aprobada.
                </p>

                <section class="agenda-patient-tabpanel" data-patient-panel="essential">
                    <div class="agenda-patient-form-grid">
                        <label class="agenda-field">
                            <span class="agenda-field__label">Tipo de documento</span>
                            <select class="agenda-field__input" id="agenda-draft-type">
                                @foreach (\App\Http\Controllers\Patients\OperationalPatientController::DOCUMENT_TYPES as $documentType)
                                    <option value="{{ $documentType }}">{{ $documentType }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Número de documento</span>
                            <input class="agenda-field__input" id="agenda-draft-number" type="text" maxlength="255" aria-describedby="agenda-draft-identity-note">
                        </label>
                        <p id="agenda-draft-identity-note" class="agenda-field-error" role="alert" hidden></p>
                        <div class="agenda-patient-reniec">
                            <button type="button" class="agenda-btn" id="agenda-draft-reniec" hidden>Consultar DNI</button>
                        </div>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Nombre</span>
                            <input class="agenda-field__input" id="agenda-draft-nombre" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Apellido paterno</span>
                            <input class="agenda-field__input" id="agenda-draft-apellido-paterno" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Apellido materno</span>
                            <input class="agenda-field__input" id="agenda-draft-apellido-materno" type="text" maxlength="255">
                        </label>
                        <div class="agenda-field">
                            <span class="agenda-field__label">Celular principal <span class="agenda-required-mark">*</span></span>
                            <div class="agenda-phone">
                                <select class="agenda-field__input" id="agenda-draft-phone-prefix" aria-label="Código país">
                                    @foreach ($phonePrefixes as $prefix => $country)
                                        <option value="{{ $prefix }}">{{ $country }} {{ $prefix }}</option>
                                    @endforeach
                                </select>
                                <input class="agenda-field__input" id="agenda-draft-telefono" type="tel" maxlength="32" inputmode="numeric" placeholder="999888777">
                            </div>
                        </div>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Género * · requerido</span>
                            <select class="agenda-field__input" id="agenda-draft-genero">
                                <option value="">Seleccionar</option>
                                <option value="HOMBRE">HOMBRE</option>
                                <option value="MUJER">MUJER</option>
                            </select>
                        </label>
                    </div>
                </section>

                <div class="agenda-patient-form-grid agenda-modal-booking">
                    <label class="agenda-field"><span class="agenda-field__label"><span>Servicio <b class="agenda-required-mark" aria-hidden="true">*</b></span><small class="agenda-required-badge">Obligatorio</small></span>
                        <select class="agenda-field__input" id="agenda-draft-service" aria-required="true" aria-describedby="agenda-draft-service-error"></select>
                        <small id="agenda-draft-service-error" class="agenda-field-error" role="alert" hidden>Selecciona un servicio para agendar la cita.</small>
                    </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Comercial dueño</span>
                            <select class="agenda-field__input" id="agenda-draft-commercial-owner" @disabled(!$canAssignResponsible)>
                                <option value="">{{ $autoOwner ? 'Automático: '.auth()->user()->name : 'Sin asignar' }}</option>
                                @foreach ($commercialUsers as $commercial)
                                    <option value="{{ $commercial->id }}">{{ $commercial->name }}</option>
                                @endforeach
                            </select>
                            <small>Se registra como responsable comercial de la cita, separado del usuario creador.</small>
                        </label>
                </div>

                <details class="agenda-patient-more" id="agenda-draft-more"><summary>Más datos del paciente · opcional</summary>
                    <div class="agenda-patient-form-grid"><label class="agenda-field"><span class="agenda-field__label">Celular secundario · opcional</span><input id="agenda-draft-phone-secondary" class="agenda-field__input" type="tel" maxlength="32" placeholder="+51…"></label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Fecha de nacimiento</span>
                            <input class="agenda-field__input" id="agenda-draft-fecha-nacimiento" type="date">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Canal de captación</span>
                            <select class="agenda-field__input" id="agenda-draft-channel">
                                <option value="">Sin indicar</option>
                                @foreach ($channels as $channel)
                                    <option value="{{ $channel->id }}">{{ $channel->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Medio de contacto</span>
                            <select class="agenda-field__input" id="agenda-draft-interaction-medium">
                                <option value="">Sin indicar</option>
                                @foreach ($interactionMedia as $medium)
                                    <option value="{{ $medium->id }}">{{ $medium->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                <section class="agenda-patient-tabpanel" data-patient-panel="complete">
                    <div class="agenda-patient-form-grid">
                        <label class="agenda-field">
                            <span class="agenda-field__label">Correo electrónico</span>
                            <input class="agenda-field__input" id="agenda-draft-email" type="email" maxlength="255">
                        </label>
                        <label class="agenda-field agenda-patient-form-grid__wide">
                            <span class="agenda-field__label">Dirección</span>
                            <input class="agenda-field__input" id="agenda-draft-direccion" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Estado civil</span>
                            <select class="agenda-field__input" id="agenda-draft-estado-civil">
                                <option value="">Sin indicar</option>
                                @foreach ($civilStatuses as $civilStatus)
                                    <option value="{{ $civilStatus }}">{{ $civilStatus }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Ocupación</span>
                            <input class="agenda-field__input" id="agenda-draft-ocupacion" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Grado de instrucción</span>
                            <input class="agenda-field__input" id="agenda-draft-grado-instruccion" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Familiar de contacto</span>
                            <input class="agenda-field__input" id="agenda-draft-familiar-contacto" type="text" maxlength="255">
                        </label>
<div class="agenda-field agenda-patient-form-grid__wide">
                            <label class="agenda-check">
                                <input type="checkbox" id="agenda-draft-register-responsible">
                                <span>Registrar responsable o acompañante</span>
                            </label>
                            <div id="agenda-draft-responsible" hidden>
                                <div class="agenda-patient-form-grid">
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Parentesco</span>
                                        <select class="agenda-field__input" id="agenda-draft-responsible-relationship">
                                            @foreach ($relationships as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Nombre completo</span>
                                        <input class="agenda-field__input" id="agenda-draft-responsible-name" type="text" maxlength="255">
                                    </label>
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Teléfono</span>
                                        <input class="agenda-field__input" id="agenda-draft-responsible-phone" type="tel" maxlength="255">
                                    </label>
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Tipo de documento</span>
                                        <select class="agenda-field__input" id="agenda-draft-responsible-document-type">
                                            @foreach ($documentTypes as $documentType)
                                                <option value="{{ $documentType }}">{{ $documentType }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Número de documento</span>
                                        <input class="agenda-field__input" id="agenda-draft-responsible-document-number" type="text" maxlength="255">
                                    </label>
                                </div>
                            </div>
                        </div>
</div>
                </section>

                </details>
                <div id="agenda-op-modal-host"></div>
                <div class="agenda-patient-dialog__schedule">
                    <span>Médico: <strong id="agenda-draft-context-doctor">—</strong></span>
                    <span>Fecha: <strong id="agenda-draft-context-date">—</strong></span>
                    <span>Hora: <strong id="agenda-draft-context-time">—</strong></span>
                    <span>Sede: <strong id="agenda-draft-context-site">—</strong></span>
                </div>
                <p class="agenda-draft__message" id="agenda-draft-message" role="status" aria-live="polite"></p>
            </div>

            <p class="agenda-guidance" id="agenda-draft-booking-help">Guardar reserva: guarda el seguimiento sin confirmar el horario. Confirmar cita: confirma el horario con al menos 50% de adelanto real o exoneración autorizada.</p>
            <footer class="agenda-patient-dialog__actions">
                <span id="agenda-patient-write-policy">
                    {{ $canWritePatients ? 'Guardado habilitado para Admisión, Recepción y Comercial.' : 'Solo lectura: guardar requiere Admisión, Recepción o Comercial.' }}
                </span>
                <button type="button" class="agenda-btn" id="agenda-draft-cancel">Cancelar</button>
                <button type="button" class="agenda-btn" id="agenda-draft-save" @disabled(!$canWritePatients)>Guardar reserva</button>
                <button type="submit" class="agenda-btn agenda-btn--primary" id="agenda-draft-save-schedule" @disabled(!$canWritePatients || !$canCreateAppointments)>
                    Guardar y confirmar cita
                </button>
            </footer>
        </form>
    </section>
</div>
