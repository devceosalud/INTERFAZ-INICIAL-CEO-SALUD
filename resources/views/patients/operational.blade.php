@extends('layouts.app')

@section('body_class', 'patients-viewport')

@section('css_data')
    <link href="{{ asset('css/patients/operational.css') }}" rel="stylesheet">
@endsection

@section('body')
    @include('templates.preloader')

    <div id="main-wrapper" class="menu-toggle patients-shell-compact">
        @include('templates.nav-header')
        @include('templates.chat-box')
        @include('templates.header')
        @include('templates.sidebar')

        <div class="content-body patients-page">
            <div class="container-fluid patients-page__container">
                <main class="patients-workspace" id="patients-workspace" data-ui-screen="pacientes"
                    data-initial-patient-id="{{ $initialPatientId ?? '' }}"
                    data-detail-template="{{ url('/patients/__PATIENT__') }}"
                    data-store-url="{{ route('patients.operational.store') }}"
                    data-update-template="{{ url('/patients/__PATIENT__') }}"
                    data-reniec-url="{{ route('patients.operational.reniec-lookup') }}"
                    data-hce-supported-types='@json(\App\Support\Patients\PatientClinicalHistoryNumber::supportedDocumentTypes())'
                    data-can-write="{{ $canWritePatients ? '1' : '0' }}"
                    data-today="{{ $filters['fecha'] }}">

                    @if($agendaReturnUrl ?? null)
                        <a href="{{ $agendaReturnUrl }}" class="patients-views__tab" id="patient-return-agenda">← Volver a Agenda</a>
                    @endif

                    <section class="patients-list-surface" id="patients-list-surface" data-ui-zone="list" aria-labelledby="patients-list-title">
                        <header class="patients-titlebar">
                            <div>
                                <p class="patients-eyebrow">Gestión asistencial</p>
                                <h1 id="patients-list-title">{{ $pendingView ? 'Fichas pendientes' : 'Pacientes' }}</h1>
                            </div>
                            <p class="patients-titlebar__summary">
                                @if ($pendingView)
                                    {{ $patients->total() }} con los filtros actuales
                                @else
                                    {{ $patients->total() }} registros maestros
                                @endif
                            </p>
                        </header>

                        <nav class="patients-views" aria-label="Vistas de pacientes">
                            <a class="patients-views__tab @unless($pendingView) is-active @endunless" href="{{ $patientListUrl }}">
                                Pacientes
                            </a>
                            <a class="patients-views__tab @if($pendingView) is-active @endif" href="{{ $pendingListUrl }}" id="patients-pending-tab">
                                Fichas pendientes
                                <span class="patients-views__count">{{ $pendingCount }}</span>
                            </a>
                        </nav>

                        <form class="patients-filterbar" method="GET" action="{{ url()->current() }}" id="patients-filter-form" data-ui-zone="toolbar">
                            @if ($pendingView)
                                <input type="hidden" name="vista" value="pendientes">
                            @endif
                            <label class="patients-field">
                                <span>Tipo documento</span>
                                <select name="tipo_documento" id="patient-filter-document-type">
                                    <option value="">Todos</option>
                                    @foreach ($documentTypes as $documentType)
                                        <option value="{{ $documentType }}" @selected($filters['tipo_documento'] === $documentType)>
                                            {{ $documentType }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="patients-field">
                                <span>Número documento</span>
                                <input type="search" name="numero_documento" value="{{ $filters['numero_documento'] }}"
                                    maxlength="255" autocomplete="off">
                            </label>

                            <label class="patients-field patients-field--hce">
                                <span>HCE</span>
                                <input type="search" name="hce" value="{{ $filters['hce'] }}" maxlength="255"
                                    autocomplete="off">
                            </label>

                            <label class="patients-field patients-field--name">
                                <span>Nombre y apellido</span>
                                <input type="search" name="nombre" value="{{ $filters['nombre'] }}" maxlength="255"
                                    autocomplete="off">
                            </label>

                            <label class="patients-field patients-field--date">
                                <span>Fecha</span>
                                <input type="date" name="fecha" value="{{ $filters['fecha'] }}"
                                    aria-describedby="patient-date-source-note">
                            </label>

                            <div class="patients-filterbar__actions">
                                <button class="patients-btn patients-btn--primary" type="button" id="patient-add">Agregar</button>
                                <button class="patients-btn patients-btn--danger" type="button" id="patient-delete" disabled
                                    title="La semántica de eliminación todavía no está definida">Eliminar</button>
                                <button class="patients-btn" type="submit" id="patient-search">Buscar</button>
                            </div>
                        </form>

                        @if ($pendingDocument)
                            <div class="patients-pending-banner" id="patient-pending-banner" role="status">
                                <strong>Ficha pendiente de completar</strong>
                                <span>A este paciente le faltan datos operativos de la ficha. Puede completarlos aunque no tenga una cita próxima.</span>
                                @if (($pendingDocument['mode'] ?? null) === 'one')
                                    <button class="patients-btn patients-btn--primary" type="button"
                                        data-complete-patient="{{ $pendingDocument['id'] }}">
                                        Completar ficha
                                    </button>
                                @else
                                    <a class="patients-btn patients-btn--primary" href="{{ $pendingListUrl }}">Ver fichas pendientes</a>
                                @endif
                            </div>
                        @endif

                        <div class="patients-source-note" id="patient-date-source-note" role="note">
                            @if ($pendingView)
                                <strong>Ficha pendiente de completar:</strong> todavía faltan datos del paciente.
                            @else
                                Cada fila es la ficha del paciente. La fecha todavía no filtra este listado.
                            @endif
                        </div>

                        <div class="patients-table-region" id="patients-table-region" tabindex="0"
                            aria-label="Listado operativo de pacientes. Un clic selecciona; el menú contextual permite abrir.">
                            <table class="patients-table {{ $pendingView ? 'patients-table--pending' : '' }}">
                                <thead>
                                    @if ($pendingView)
                                        <tr>
                                            <th scope="col">Documento</th>
                                            <th scope="col">HCE</th>
                                            <th scope="col">Paciente</th>
                                            <th scope="col">Próxima cita</th>
                                            <th scope="col">Médico</th>
                                            <th scope="col">Datos faltantes</th>
                                            <th scope="col">Acción</th>
                                        </tr>
                                    @else
                                        <tr>
                                            <th scope="col">N.° Registro</th>
                                            <th scope="col">HCE</th>
                                            <th scope="col">Documento</th>
                                            <th scope="col">Paciente</th>
                                            <th scope="col">Fecha de atención</th>
                                            <th scope="col">Usuario</th>
                                            <th scope="col">Estado</th>
                                        </tr>
                                    @endif
                                </thead>
                                <tbody id="patients-table-body">
                                    @forelse ($patients as $patient)
                                        @php
                                            $patientName = trim($patient->apellido_paterno.' '.$patient->apellido_materno.' '.$patient->nombre);
                                        @endphp
                                        <tr class="patients-table__row" tabindex="0" data-patient-id="{{ $patient->id }}"
                                            aria-label="Abrir ficha de {{ $patientName }}">
                                            @if ($pendingView)
                                                <td>
                                                    <span class="patients-document-type">{{ $patient->tipo_identificacion }}</span>
                                                    <span>{{ $patient->numero_identidad }}</span>
                                                </td>
                                                <td class="patients-cell--hce">{{ $patient->historia_clinica ?: '—' }}</td>
                                                <td class="patients-cell--name">{{ $patientName }}</td>
                                                <td>{{ $patient->pending_visit }}</td>
                                                <td>{{ $patient->pending_doctor }}</td>
                                                <td>
                                                    {{ implode(', ', $patient->pending_gaps ?? []) ?: '—' }}
                                                    @if (!empty($patient->pending_recommended))
                                                        <small class="patients-recommended">Datos recomendados pendientes: {{ implode(', ', $patient->pending_recommended) }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <button class="patients-btn patients-btn--primary" type="button"
                                                        data-complete-patient="{{ $patient->id }}">
                                                        Completar ficha
                                                    </button>
                                                </td>
                                            @else
                                                <td class="patients-cell--pending" title="Pendiente de modelo de atención">—</td>
                                                <td class="patients-cell--hce">{{ $patient->historia_clinica ?: '—' }}</td>
                                                <td>
                                                    <span class="patients-document-type">{{ $patient->tipo_identificacion }}</span>
                                                    <span>{{ $patient->numero_identidad }}</span>
                                                </td>
                                                <td class="patients-cell--name">{{ $patientName }}</td>
                                                <td class="patients-cell--pending" title="No equivale a fecha de cita">—</td>
                                                <td>{{ $patient->user?->name ?: '—' }}</td>
                                                <td><span class="patients-state patients-state--{{ strtolower($patient->estado) }}">{{ $patient->estado }}</span></td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr class="patients-table__empty">
                                            <td colspan="7">
                                                {{ $pendingView ? 'No hay fichas pendientes de completar.' : 'No hay pacientes que coincidan con los filtros.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <footer class="patients-list-footer">
                            <span>Un clic selecciona. Clic derecho abre las acciones del registro.</span>
                            @if ($patients->hasPages())
                                <div class="patients-pagination">{{ $patients->links() }}</div>
                            @endif
                        </footer>
                    </section>

                    <section class="patients-record-surface" id="patients-record-surface" data-ui-zone="record" aria-labelledby="patient-record-title" hidden>
                        <header class="patients-record-head">
                            <div class="patients-record-head__title">
                                <p class="patients-eyebrow">Pacientes / Ficha</p>
                                <h1 id="patient-record-title">Ficha del paciente</h1>
                            </div>
                            <div class="patients-record-head__actions">
                                @if(config('scheduling.enabled') && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS) && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::VIEW))
                                    <button id="patient-appointment-documents-open" class="patients-btn" type="button">Documentos de citas</button>
                                @endif

                                <button class="patients-btn patients-btn--primary" type="submit" form="patient-record-form"
                                    id="patient-save" @disabled(!$canWritePatients)>
                                    Guardar cambios
                                </button>
                                <button class="patients-btn" type="button" id="patient-back">Volver</button>
                            </div>
                        </header>

                        <div class="patients-record-context" aria-label="Contexto del paciente">
                            <div>
                                <span>HCE</span>
                                <strong id="patient-context-hce">—</strong>
                                <small id="patient-hce-note"></small>
                            </div>
                            <div>
                                <span>N.° Registro seleccionado</span>
                                <strong>—</strong>
                                <small>Pendiente de modelo de atención</small>
                            </div>
                            <div>
                                <span>Estado</span>
                                <strong id="patient-context-status">NUEVO</strong>
                                <small id="patient-context-mode">Ficha sin persistir</small>
                            </div>
                        </div>

                        <div class="patients-record-notice" id="patient-record-notice" role="status" aria-live="polite">
                            @if ($canWritePatients)
                                Los datos maestros compatibles con el esquema actual se guardan de forma explícita.
                            @else
                                Acceso de solo lectura. Completar o corregir la ficha corresponde a Admisión, Recepción y Comercial.
                            @endif
                        </div>

                        <div id="patient-appointment-documents" aria-live="polite"></div>
                        <form class="patients-record-form" id="patient-record-form" novalidate>
                            <input type="hidden" id="patient-record-id" value="">

                            <fieldset class="patients-block">
                                <legend>Identificación</legend>
                                <div class="patients-form-grid patients-form-grid--identity-document">
                                    <label class="patients-field">
                                        <span>Tipo documento</span>
                                        <select id="patient-document-type">
                                            @foreach ($documentTypes as $documentType)
                                                <option value="{{ $documentType }}">{{ $documentType }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="patients-field">
                                        <span>Número documento</span>
                                        <input type="text" id="patient-document-number" maxlength="255" autocomplete="off">
                                    </label>
                                    <div class="patients-reniec-action" id="patient-reniec-wrap">
                                        <button class="patients-btn" type="button" id="patient-reniec">Consultar RENIEC</button>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Identidad</legend>
                                <div class="patients-form-grid patients-form-grid--three">
                                    <label class="patients-field">
                                        <span>Nombre</span>
                                        <input type="text" id="patient-name" maxlength="255">
                                    </label>
                                    <label class="patients-field">
                                        <span>Apellido paterno</span>
                                        <input type="text" id="patient-paternal-name" maxlength="255">
                                    </label>
                                    <label class="patients-field">
                                        <span>Apellido materno</span>
                                        <input type="text" id="patient-maternal-name" maxlength="255">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Contacto</legend>
                                <div class="patients-form-grid patients-form-grid--two">
                                    <div class="patients-field">
                                        <span>Teléfono</span>
                                        <div class="patients-phone">
                                            <select id="patient-phone-prefix" aria-label="Código país">
                                                @foreach ($phonePrefixes as $prefix => $country)
                                                    <option value="{{ $prefix }}" @selected($prefix === '+51')>{{ $country }} {{ $prefix }}</option>
                                                @endforeach
                                            </select>
                                            <input type="tel" id="patient-phone" maxlength="32" inputmode="numeric" placeholder="999888777" autocomplete="off">
                                        </div>
                                    </div>
                                    <label class="patients-field">
                                        <span>Email</span>
                                        <input type="email" id="patient-email" maxlength="255" placeholder="persona@dominio.com" autocomplete="off">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Datos operativos</legend>
                                <div class="patients-form-grid patients-form-grid--three">
                                    <label class="patients-field"><span>Celular secundario · opcional</span><input id="patient-phone-secondary" type="tel" maxlength="32"></label>
                                    <label class="patients-field">
                                        <span>Canal de captación</span>
                                        <select id="patient-channel">
                                            <option value="">Sin indicar</option>
                                            @foreach ($channels as $channel)
                                                <option value="{{ $channel->id }}">{{ $channel->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="patients-field">
                                        <span>Medio de contacto</span>
                                        <select id="patient-interaction-medium">
                                            <option value="">Sin indicar</option>
                                            @foreach ($interactionMedia as $medium)
                                                <option value="{{ $medium->id }}">{{ $medium->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="patients-field">
                                        <span>Ocupación</span>
                                        <input type="text" id="patient-occupation" maxlength="255">
                                    </label>
                                    <label class="patients-field">
                                        <span>Grado de instrucción</span>
                                        <input type="text" id="patient-education" maxlength="255">
                                    </label>
                                    <label class="patients-field">
                                        <span>Familiar de contacto</span>
                                        <input type="text" id="patient-family-contact" maxlength="255">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Datos personales</legend>
                                <div class="patients-form-grid patients-form-grid--personal">
                                    <label class="patients-field">
                                        <span>Fecha de nacimiento</span>
                                        <input type="date" id="patient-birth-date">
                                    </label>
                                    <label class="patients-field">
                                        <span>Género</span>
                                        <select id="patient-gender">
                                            <option value="">Seleccionar</option>
                                            <option value="HOMBRE">HOMBRE</option>
                                            <option value="MUJER">MUJER</option>
                                        </select>
                                    </label>
                                    <label class="patients-field">
                                        <span>Estado civil</span>
                                        <select id="patient-civil-status">
                                            <option value="">Sin indicar</option>
                                            @foreach ($civilStatuses as $civilStatus)
                                                <option value="{{ $civilStatus }}">{{ $civilStatus }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="patients-field patients-field--address">
                                        <span>Dirección</span>
                                        <input type="text" id="patient-address" maxlength="255">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Responsable o acompañante</legend>
                                <label class="patients-check">
                                    <input type="checkbox" id="patient-register-responsible">
                                    <span>Registrar responsable o acompañante</span>
                                </label>
                                <p class="patients-help">Los pacientes menores de 18 años deben registrar un responsable o acompañante adulto.</p>
                                <div id="patient-responsible" hidden>
                                    <div class="patients-form-grid patients-form-grid--three">
                                        <label class="patients-field">
                                            <span>Parentesco</span>
                                            <select id="patient-responsible-relationship">
                                                @foreach ($relationships as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="patients-field">
                                            <span>Nombre completo</span>
                                            <input type="text" id="patient-responsible-name" maxlength="255" autocomplete="off">
                                        </label>
                                        <label class="patients-field">
                                            <span>Teléfono</span>
                                            <input type="tel" id="patient-responsible-phone" maxlength="255" autocomplete="off">
                                        </label>
                                        <label class="patients-field">
                                            <span>Tipo de documento</span>
                                            <select id="patient-responsible-document-type">
                                                @foreach ($documentTypes as $documentType)
                                                    <option value="{{ $documentType }}">{{ $documentType }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="patients-field">
                                            <span>Número de documento</span>
                                            <input type="text" id="patient-responsible-document-number" maxlength="255" autocomplete="off">
                                        </label>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block patients-block--pending">
                                <legend>Contexto / esta atención</legend>
                                <div class="patients-form-grid patients-form-grid--two">
                                    <label class="patients-field">
                                        <span>Fecha</span>
                                        <input type="date" id="patient-encounter-date" value="{{ $filters['fecha'] }}" disabled>
                                    </label>
                                    <label class="patients-field">
                                        <span>Motivo</span>
                                        <input type="text" id="patient-encounter-reason" placeholder="Pendiente de modelo de atención" disabled>
                                    </label>
                                </div>
                                <p>No se persiste: el sistema heredado no contiene todavía una entidad de atención ambulatoria.</p>
                            </fieldset>
                        </form>
                    </section>

                    <div class="patients-context-menu" id="patients-context-menu" role="menu" hidden>
                        <button type="button" role="menuitem" id="patient-context-open">Abrir ficha</button>
                        <button type="button" role="menuitem" id="patient-context-add">Agregar nuevo</button>
                        <button type="button" role="menuitem" disabled>Eliminar / Desactivar</button>
                    </div>
                </main>
            </div>
        </div>

        @include('templates.footer')
    </div>
@endsection

@section('script_data')
    @include('telemetry.collector')
    <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
    <script src="{{ asset('assets/js/custom.min.js') }}"></script>
    <script src="{{ asset('assets/js/deznav-init.js') }}"></script>
    <script src="{{ asset('js/patients/patient-phone.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda-patient-draft.js') }}"></script>
    <script src="{{ asset('js/patients/patient-workspace.js') }}"></script>
    <script src="{{ asset('js/patients/operational.js') }}"></script>
@endsection
