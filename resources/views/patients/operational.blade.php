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
                <main class="patients-workspace" id="patients-workspace"
                    data-detail-template="{{ url('/patients/__PATIENT__') }}"
                    data-store-url="{{ route('patients.operational.store') }}"
                    data-update-template="{{ url('/patients/__PATIENT__') }}"
                    data-reniec-url="{{ route('patients.operational.reniec-lookup') }}"
                    data-can-write="{{ $canWritePatients ? '1' : '0' }}"
                    data-today="{{ $filters['fecha'] }}">

                    <section class="patients-list-surface" id="patients-list-surface" aria-labelledby="patients-list-title">
                        <header class="patients-titlebar">
                            <div>
                                <p class="patients-eyebrow">Gestión asistencial</p>
                                <h1 id="patients-list-title">Pacientes</h1>
                            </div>
                            <p class="patients-titlebar__summary">{{ $patients->total() }} registros maestros</p>
                        </header>

                        <form class="patients-filterbar" method="GET" action="{{ url()->current() }}" id="patients-filter-form">
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

                        <div class="patients-source-note" id="patient-date-source-note" role="note">
                            <strong>Fuente actual:</strong> una fila representa la ficha maestra del paciente. No existe todavía
                            una entidad persistida de atención ambulatoria; por ello Fecha no filtra atenciones y N.° Registro / Fecha
                            de atención se mantienen pendientes, sin reutilizar datos de citas.
                        </div>

                        <div class="patients-table-region" id="patients-table-region" tabindex="0"
                            aria-label="Listado operativo de pacientes. Un clic selecciona; el menú contextual permite abrir.">
                            <table class="patients-table">
                                <thead>
                                    <tr>
                                        <th scope="col">N.° Registro</th>
                                        <th scope="col">HCE</th>
                                        <th scope="col">Documento</th>
                                        <th scope="col">Paciente</th>
                                        <th scope="col">Fecha de atención</th>
                                        <th scope="col">Usuario</th>
                                        <th scope="col">Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="patients-table-body">
                                    @forelse ($patients as $patient)
                                        <tr class="patients-table__row" tabindex="0" data-patient-id="{{ $patient->id }}"
                                            aria-label="Abrir ficha de {{ trim($patient->apellido_paterno.' '.$patient->apellido_materno.' '.$patient->nombre) }}">
                                            <td class="patients-cell--pending" title="Pendiente de modelo de atención">—</td>
                                            <td class="patients-cell--hce">{{ $patient->historia_clinica ?: '—' }}</td>
                                            <td>
                                                <span class="patients-document-type">{{ $patient->tipo_identificacion }}</span>
                                                <span>{{ $patient->numero_identidad }}</span>
                                            </td>
                                            <td class="patients-cell--name">
                                                {{ trim($patient->apellido_paterno.' '.$patient->apellido_materno.' '.$patient->nombre) }}
                                            </td>
                                            <td class="patients-cell--pending" title="No equivale a fecha de cita">—</td>
                                            <td>{{ $patient->user?->name ?: '—' }}</td>
                                            <td><span class="patients-state patients-state--{{ strtolower($patient->estado) }}">{{ $patient->estado }}</span></td>
                                        </tr>
                                    @empty
                                        <tr class="patients-table__empty">
                                            <td colspan="7">No hay pacientes que coincidan con los filtros.</td>
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

                    <section class="patients-record-surface" id="patients-record-surface" aria-labelledby="patient-record-title" hidden>
                        <header class="patients-record-head">
                            <div class="patients-record-head__title">
                                <p class="patients-eyebrow">Pacientes / Ficha</p>
                                <h1 id="patient-record-title">Ficha del paciente</h1>
                            </div>
                            <div class="patients-record-head__actions">
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
                                    <label class="patients-field">
                                        <span>Teléfono</span>
                                        <input type="tel" id="patient-phone" maxlength="255">
                                    </label>
                                    <label class="patients-field">
                                        <span>Email</span>
                                        <input type="email" id="patient-email" maxlength="255">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Datos operativos</legend>
                                <div class="patients-form-grid patients-form-grid--three">
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
                                        <span>Medio de interacción</span>
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
                                        <input type="text" id="patient-civil-status" maxlength="255">
                                    </label>
                                    <label class="patients-field patients-field--address">
                                        <span>Dirección</span>
                                        <input type="text" id="patient-address" maxlength="255">
                                    </label>
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
    <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
    <script src="{{ asset('assets/js/custom.min.js') }}"></script>
    <script src="{{ asset('assets/js/deznav-init.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda-patient-draft.js') }}"></script>
    <script src="{{ asset('js/patients/patient-workspace.js') }}"></script>
    <script src="{{ asset('js/patients/operational.js') }}"></script>
@endsection
