@extends('layouts.app')
@section('body_class', 'admission-viewport')
@section('css_data')
<link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/agenda-reschedule-history.css') }}" rel="stylesheet">
<link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/agenda-indicators.css') }}" rel="stylesheet">
<link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/appointment-patient-documents.css') }}" rel="stylesheet">
<link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/admission-board.css') }}" rel="stylesheet">
@endsection
@section('body')
<div id="main-wrapper" class="menu-toggle">
    @include('templates.nav-header')
    @include('templates.header')
    @include('templates.sidebar')
    <div class="content-body admission-page"><main id="admission-board" class="admission-board"
        data-clinical-only="{{ $clinicalOnly ? '1' : '0' }}" data-feed="{{ route('scheduling.mvp.admission.feed') }}" data-base="{{ url('/scheduling-mvp/admission/appointments') }}"
        data-availability="{{ route('scheduling.mvp.availability') }}" data-agenda="{{ route('scheduling.mvp.agenda') }}">
        <div class="admission-commandbar">
            <header class="admission-heading"><div><p class="admission-eyebrow">ATENCIÓN Y SEGUIMIENTO</p><h1>Agenda de admisión</h1><p>Atención y seguimiento del paciente, en una misma vista.</p></div>
                <div class="admission-actions"><button type="button" id="admission-refresh" class="admission-btn">Actualizar</button><a class="admission-btn admission-btn--primary" href="{{ route('scheduling.mvp.agenda') }}" id="admission-create" @if($clinicalOnly) hidden @endif>{{ auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::CREATE) ? '+ Agendar cita' : 'Agenda operativa' }}</a></div>
            </header>
            <section class="admission-kpis" aria-label="Indicadores del conjunto filtrado">
                @foreach(['total'=>'Citas','por_llegar'=>'Por llegar','en_espera'=>'En espera','en_consultorio'=>'En atención','reevaluaciones_pendientes'=>'Pendientes de reevaluación','reevaluaciones'=>'Listos para reevaluación','atendidos'=>'Atendidos'] as $key=>$label)
                    <button type="button" class="admission-kpi" @if($key==='reevaluaciones') data-review-only @elseif($key==='reevaluaciones_pendientes') data-pending-review-only @else data-group="{{ $key==='total'?'':$key }}" @endif><span>{{ $label }}</span><strong data-kpi="{{ $key }}">—</strong></button>
                @endforeach
            </section>
            <form id="admission-filters" class="admission-filters" autocomplete="off">
                <div class="admission-filter-primary">
                    <label class="admission-search">Buscar paciente<input id="admission-search" type="search" name="patient" maxlength="100" placeholder="Nombre, DNI o historia clínica"></label>
                    <label>Fecha<input id="admission-date" name="fecha" type="date" value="{{ $today }}" required></label>
                    <label class="admission-doctor">Profesional<select name="doctor_id" id="admission-doctor"><option value="">Todos los profesionales</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" data-specialty="{{ $doctor->specialty_id }}" data-active="{{ $doctor->estado==='ACTIVO'?'1':'0' }}">{{ $doctor->nombre }}{{ $doctor->estado==='ACTIVO'?'':' (inactivo)' }}</option>@endforeach</select></label>
                    <div class="admission-primary-actions"><button type="submit" class="admission-btn admission-btn--primary">Aplicar filtros</button><button type="button" id="admission-today" class="admission-btn admission-btn--quiet" data-today="{{ $today }}">Hoy</button><button type="button" id="admission-reset" class="admission-btn admission-btn--quiet">Restablecer</button></div>
                </div>
                <details id="admission-more-filters" class="admission-more-filters"><summary>Más filtros <span id="admission-more-count"></span></summary>
                <div class="admission-filter-secondary">
                    <label class="admission-review-filter"><input type="checkbox" name="only_pending_reevaluations" value="1" id="admission-only-pending-reviews"> Pendientes de reevaluación</label>
                    <label>Etapa pendiente<select id="admission-reevaluation-stage" name="reevaluation_stage"><option value="">Todas las pendientes</option><option value="PRE_PENDING">Por confirmar</option><option value="PENDING">Confirmadas, pendientes</option></select></label>
                    <label class="admission-review-filter"><input type="checkbox" name="only_reevaluations" value="1" id="admission-only-reviews"> Solo listos para reevaluación</label>
                </div>

                    <div class="admission-filter-advanced">
                        <label>Hasta<input id="admission-until" name="hasta" type="date" value="{{ $today }}" required></label>
                        <label>Sede<select name="site_id" id="admission-site"><option value="">Todas / sin sede</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected($defaultSite?->id===$site->id)>{{ $site->nombre }}</option>@endforeach</select></label>
                        <label>Especialidad<select name="specialty_id" id="admission-specialty"><option value="">Todas</option>@foreach($specialties as $specialty)<option value="{{ $specialty->id }}">{{ $specialty->nombre }}</option>@endforeach</select></label>
                        <label>Estado de atención<select name="estado" id="admission-state"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state }}">{{ str_replace('_',' ',$state) }}</option>@endforeach</select></label>
                        <label>Categoría visual<select name="visual" id="admission-visual"><option value="">Todas</option>@foreach($visuals as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        <label>Grupo de atención<select name="grupo" id="admission-group"><option value="">Todos</option>@foreach($labels as $key=>$label)@if($key!=='total')<option value="{{ $key }}">{{ $label }}</option>@endif @endforeach<option value="reservas">Reservas pendientes</option><option value="otros">Otros / revisar</option></select></label>
                    </div>
                </details>
            </form>
            <div class="admission-context-line"><div class="admission-filter-summary"><span id="admission-active-filter" role="status"></span> <button id="admission-clear-category" type="button" class="admission-btn" hidden>Retirar filtro rápido</button></div><details class="admission-scope-info"><summary>Alcance e indicadores</summary><div><p class="admission-scope" id="admission-scope">Indicadores y totales del conjunto filtrado.</p><p class="admission-scope">Reevaluación: <strong data-kpi="reevaluaciones_prependientes">—</strong> por confirmar · <strong data-kpi="reevaluaciones_confirmadas_pendientes">—</strong> confirmadas, pendientes · <strong data-kpi="reevaluaciones">—</strong> listas.</p></div></details><details class="admission-financial-summary" @if($clinicalOnly) hidden @endif><summary>Resumen económico <strong id="admission-paid-total">—</strong> abonado · <strong id="admission-balance-total">—</strong> saldo</summary><dl class="admission-totals" id="admission-totals"></dl><p class="admission-footnote">Totales del conjunto de fecha, profesional, sede, estado y búsqueda, antes de los filtros rápidos. Pagos acreditados y snapshots históricos se distinguen; los importes sin atribución se excluyen hasta conciliación. TICKET es un documento interno.</p></details></div>
            <p id="admission-notice" class="admission-notice" role="status" aria-live="polite"></p>
        </div>
        <div class="admission-workspace">
            <section class="admission-main" aria-label="Pacientes y reevaluaciones">
                <details id="admission-reschedule-panel" class="admission-reschedule-panel" hidden>
                    <summary>Reprogramaciones del día <span id="admission-reschedule-count"></span></summary>
                    <p>Referencias históricas según los filtros. No forman parte de la cola de atención, los indicadores ni los importes. Las acciones corresponden a la cita vigente.</p>
                    <div id="admission-reschedule-list"></div>
                    <button id="admission-reschedule-previous" type="button" class="admission-btn" hidden>Referencias anteriores</button>
                    <button id="admission-reschedule-latest" type="button" class="admission-btn" hidden>Referencias recientes</button>
                </details>
                <div class="admission-list-tabs" role="tablist" aria-label="Listados de admisión"><button id="admission-patients-tab" role="tab" aria-selected="true" aria-controls="admission-patients-panel" type="button">Pacientes <span id="admission-count"></span></button><button @if($clinicalOnly) hidden @endif id="admission-queue-tab" role="tab" aria-selected="false" aria-controls="admission-queue-panel" type="button" tabindex="-1">Reevaluaciones de hoy <span id="admission-queue-count"></span></button><div class="admission-list-caption"><span id="admission-reservations"></span><span>Selecciona un paciente para gestionar su atención</span></div></div>
                <div id="admission-patients-panel" role="tabpanel" aria-labelledby="admission-patients-tab" class="admission-table-card">
                    <div class="admission-table-scroll"><table class="admission-table"><thead><tr><th>Hora</th><th>Paciente / motivo</th><th>Profesional / servicio</th><th>Atención</th><th>Procedimientos / alertas</th></tr></thead><tbody id="admission-rows"></tbody></table></div>
                    <div class="admission-pagination"><button type="button" id="admission-prev" class="admission-btn">Anterior</button><span id="admission-page"></span><button type="button" id="admission-next" class="admission-btn">Siguiente</button></div>
                </div>
                <div id="admission-queue-panel" role="tabpanel" aria-labelledby="admission-queue-tab" hidden>@unless($clinicalOnly)<x-utils.operational-reevaluations :compact="true" />@endunless</div>
            </section>
            <aside class="admission-detail" aria-label="Detalle de cita"><div class="admission-section-head"><h2>Detalle del paciente</h2><button id="admission-back-list" type="button" class="admission-btn admission-btn--quiet">Volver a la lista</button></div><div id="admission-detail" class="admission-detail-body"><p class="admission-empty">Selecciona un paciente para consultar el motivo y gestionar su atención.</p></div></aside>
        </div>
        <details class="admission-availability" id="admission-availability-panel" @if($clinicalOnly) hidden @endif><summary>Turnos y disponibilidad <span>Consulta complementaria · fecha inicial del filtro</span></summary><p id="admission-availability-note"></p><div id="admission-availability" class="admission-availability-grid"></div>@unless($defaultSite)<p class="admission-footnote">Magdalena del Mar no tiene una identificación única en el catálogo. Usa el filtro de sede; no se reasignan citas históricas.</p>@endunless</details>
    </main></div>
    @include('templates.footer')
</div>
@endsection
@section('script_data')
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/agenda-appointment-indicators.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/appointment-patient-documents.js') }}" data-patient-document-module="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/pdfjs/pdf.min.js') }}" data-patient-document-worker="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/pdfjs/pdf.worker.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/global/global.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/js/custom.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/agenda-shell.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/js/deznav-init.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/admission-board.js') }}"></script>
@endsection
