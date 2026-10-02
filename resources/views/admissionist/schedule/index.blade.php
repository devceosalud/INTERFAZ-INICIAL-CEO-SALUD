@extends('layouts.app')

@section('body_class', 'schedule-viewport')

@section('css_data')
    <link href="{{ asset('assets/vendor/fullcalendar/css/main.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/scheduling/schedule-workspace.css') }}" rel="stylesheet">
@endsection

@section('body')
    @include('templates.preloader')

    <div id="main-wrapper" class="menu-toggle schedule-shell-compact">
        @include('templates.nav-header')
        @include('templates.chat-box')
        @include('templates.header')
        @include('templates.sidebar')

        <div class="content-body schedule-page">
            <div class="container-fluid schedule-page__container">
                <main class="schedule-workspace" id="schedule-workspace"
                    data-feed="{{ route('admissionit.doctor.schedule.calendar') }}"
                    data-store="{{ route('admissionit.doctor.schedule.store') }}"
                    data-update="{{ route('admissionit.doctor.schedule.update') }}"
                    data-delete="{{ route('admissionit.doctor.schedule.delete') }}"
                    data-overlap="{{ route('admissionit.doctor.schedule.overlap') }}"
                    data-impact-template="{{ route('admissionit.doctor.schedule.impact', ['doctorSchedule' => '__ID__']) }}"
                    data-can-manage="{{ $canManageSchedules ? 'true' : 'false' }}"
                    data-today="{{ $today }}">

                    <header class="schedule-toolbar" aria-label="Filtros de horarios médicos">
                        <div class="schedule-toolbar__filters">
                            <label>
                                <span>Sede</span>
                                <select id="schedule-filter-site">
                                    <option value="">Todas</option>
                                    @foreach ($sites as $site)
                                        <option value="{{ $site->id }}">{{ $site->nombre }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Especialidad</span>
                                <select id="schedule-filter-specialty">
                                    <option value="">Todas</option>
                                    @foreach ($specialties as $specialty)
                                        <option value="{{ $specialty->id }}">{{ $specialty->nombre }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Médico</span>
                                <select id="schedule-filter-doctor">
                                    <option value="">Todos los médicos</option>
                                    @foreach ($doctors as $doctor)
                                        <option value="{{ $doctor->id }}" data-specialty-id="{{ $doctor->specialty_id }}">
                                            {{ $doctor->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="schedule-toolbar__navigation" aria-label="Navegación de fecha">
                            <button type="button" class="schedule-btn" data-calendar-action="prev">Anterior</button>
                            <button type="button" class="schedule-btn" data-calendar-action="today">Hoy</button>
                            <button type="button" class="schedule-btn" data-calendar-action="next">Siguiente</button>
                        </div>

                        <div class="schedule-toolbar__views" role="group" aria-label="Vista del calendario">
                            <button type="button" class="schedule-view-btn" data-calendar-view="timeGridDay">Día</button>
                            <button type="button" class="schedule-view-btn" data-calendar-view="timeGridWeek">Semana</button>
                            <button type="button" class="schedule-view-btn is-active" data-calendar-view="dayGridMonth">Mes</button>
                        </div>
                    </header>

                    <section class="schedule-heading">
                        <div>
                            <p class="schedule-heading__eyebrow">Configuración operativa</p>
                            <h1>Horarios médicos</h1>
                            <p id="schedule-range-label" aria-live="polite"></p>
                        </div>
                        <div class="schedule-heading__actions">
                            <button type="button" class="schedule-btn schedule-btn--muted" disabled
                                title="Requiere modelo de excepciones por fecha">+ Ausencia</button>
                            <button type="button" class="schedule-btn schedule-btn--muted" disabled
                                title="Requiere modelo de excepciones por fecha">+ Horario excepcional</button>
                            <button type="button" class="schedule-btn schedule-btn--primary" id="schedule-add"
                                @disabled(! $canManageSchedules)>+ Horario</button>
                        </div>
                    </section>

                    @unless ($canManageSchedules)
                        <div class="schedule-readonly" role="status">
                            Vista de consulta. La matriz provisional permite modificar horarios únicamente a Admisión.
                        </div>
                    @endunless

                    <div class="schedule-main">
                        <section class="schedule-calendar-panel" aria-label="Calendario de horarios médicos">
                            <div id="schedule-load-state" class="schedule-load-state" hidden></div>
                            <div id="schedule-calendar"></div>
                        </section>

                        <aside class="schedule-side" aria-label="Leyenda y detalle de horario">
                            <section class="schedule-side__section" id="schedule-compare">
                                <h2 id="schedule-compare-title">Comparar médicos</h2>
                                <p id="schedule-compare-summary"></p>
                                <div id="schedule-doctor-legend" class="schedule-doctor-legend"></div>
                            </section>

                            <section class="schedule-side__section" id="schedule-selection" hidden>
                                <p id="schedule-selection-count"></p>
                                <div class="schedule-selection__actions">
                                    <button type="button" class="schedule-btn schedule-btn--primary" id="schedule-selection-configure">Configurar selección</button>
                                    <button type="button" class="schedule-btn" id="schedule-selection-clear">Limpiar selección</button>
                                </div>
                            </section>

                            <section class="schedule-side__section" id="schedule-presets" hidden>
                                <h2>Horarios frecuentes</h2>
                                <p id="schedule-preset-hint" class="schedule-preset-hint" hidden></p>
                                <div id="schedule-preset-list"></div>
                            </section>

                            <section class="schedule-side__section schedule-detail" id="schedule-detail">
                                <h2>Detalle</h2>
                                <p class="schedule-detail__empty">Seleccione un bloque para revisar su configuración.</p>
                            </section>

                            <section class="schedule-side__section schedule-rule-note">
                                <h2>Disponibilidad efectiva</h2>
                                <p><strong>Horario activo</strong> define el rango.</p>
                                <p><strong>Duración programada por cita</strong> define la cadencia que consume Agenda.</p>
                                <p class="schedule-rule-note__pending">Ausencias y excepciones por fecha: pendiente de Horarios MVP-B.</p>
                            </section>
                        </aside>
                    </div>

                    @include('admissionist.schedule.workspace-modal')
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
    <script src="{{ asset('assets/vendor/fullcalendar/js/main.min.js') }}"></script>
    <script src="{{ asset('js/scheduling/schedule-time.js') }}"></script>
    <script src="{{ asset('js/scheduling/schedule-workspace.js') }}"></script>
@endsection
