@extends('layouts.app')
@section('body_class', 'admission-viewport')
@section('css_data')
<link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/admission-board.css') }}" rel="stylesheet">
@endsection
@section('body')
<div id="main-wrapper" class="menu-toggle">
    @include('templates.nav-header')
    @include('templates.header')
    @include('templates.sidebar')
    <div class="content-body admission-page"><main id="admission-board" class="admission-board"
        data-feed="{{ route('scheduling.mvp.admission.feed') }}"
        data-base="{{ url('/scheduling-mvp/admission/appointments') }}"
        data-availability="{{ route('scheduling.mvp.availability') }}"
        data-agenda="{{ route('scheduling.mvp.agenda') }}">
        <header class="admission-heading"><div><p class="admission-eyebrow">CEO SALUD · OPERACIÓN DIARIA</p>
            <h1>Agenda de admisión</h1><p>Pacientes, atención y pagos en una misma jornada.</p></div>
            <div class="admission-actions"><button type="button" id="admission-refresh" class="admission-btn">Actualizar</button>
            <a class="admission-btn admission-btn--primary" href="{{ route('scheduling.mvp.agenda') }}" id="admission-create">+ Agendar cita</a></div>
        </header>
        @unless($defaultSite)<p class="admission-warning" role="status">Magdalena del Mar aún no está identificada de forma única y activa en el catálogo. Selecciona una sede disponible o revisa las citas sin sede; no se asignarán históricos automáticamente.</p>@endunless
        <section class="admission-kpis" aria-label="Indicadores del conjunto filtrado">
        @foreach($labels as $key=>$label)<button type="button" class="admission-kpi" data-group="{{ $key === 'total' ? '' : $key }}"><span>{{ $label }}</span><strong data-kpi="{{ $key }}">—</strong></button>@endforeach
        </section>
        <form id="admission-filters" class="admission-filters" autocomplete="off">
            <label>Desde<input id="admission-date" name="fecha" type="date" value="{{ $today }}" required></label>
            <label>Hasta<input id="admission-until" name="hasta" type="date" value="{{ $today }}" required></label>
            <label>Sede<select name="site_id" id="admission-site"><option value="">Todas / sin sede</option>
                @foreach($sites as $site)<option value="{{ $site->id }}" @selected($defaultSite?->id === $site->id)>{{ $site->nombre }}</option>@endforeach</select></label>
            <label>Especialidad<select name="specialty_id" id="admission-specialty"><option value="">Todas</option>@foreach($specialties as $specialty)<option value="{{ $specialty->id }}">{{ $specialty->nombre }}</option>@endforeach</select></label>
            <label>Médico<select name="doctor_id" id="admission-doctor"><option value="">Todos</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" data-specialty="{{ $doctor->specialty_id }}" data-active="{{ $doctor->estado === 'ACTIVO' ? '1' : '0' }}">{{ $doctor->nombre }}{{ $doctor->estado === 'ACTIVO' ? '' : ' (inactivo)' }}</option>@endforeach</select></label>
            <label>Paciente / documento<input type="search" name="patient" maxlength="100" placeholder="Nombre, DNI o H.C."></label>
            <label>Estado de atención<select name="estado" id="admission-state"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state }}">{{ str_replace('_',' ',$state) }}</option>@endforeach</select></label>
            <label>Grupo<select name="grupo" id="admission-group"><option value="">Todos</option>@foreach($labels as $key=>$label)@if($key !== 'total')<option value="{{ $key }}">{{ $label }}</option>@endif @endforeach<option value="reservas">Reservas pendientes</option><option value="otros">Otros / revisar</option></select></label>
            <div class="admission-filter-actions"><button type="button" id="admission-today" class="admission-btn" data-today="{{ $today }}">Hoy</button><button type="submit" class="admission-btn admission-btn--primary">Aplicar filtros</button></div>
        </form>
        <p class="admission-scope" id="admission-scope">Indicadores, tabla y totales corresponden al mismo conjunto filtrado.</p>
        <p id="admission-notice" class="admission-notice" role="status" aria-live="polite"></p>
        <div class="admission-workspace">
            <section class="admission-main" aria-label="Citas y disponibilidad">
                <div class="admission-table-card"><div class="admission-section-head"><h2>Citas <span id="admission-count"></span></h2><span id="admission-reservations"></span></div>
                    <div class="admission-table-scroll"><table class="admission-table"><thead><tr><th>Hora / fecha</th><th>Paciente</th><th>Médico / servicio</th><th>Atención / Agenda</th><th>Llegada / inicio / fin</th><th>Precio / abonado / saldo</th><th>Responsable</th><th></th></tr></thead><tbody id="admission-rows"></tbody></table></div>
                    <div class="admission-pagination"><button type="button" id="admission-prev" class="admission-btn">Anterior</button><span id="admission-page"></span><button type="button" id="admission-next" class="admission-btn">Siguiente</button></div>
                    <dl class="admission-totals" id="admission-totals"></dl><p class="admission-footnote">Totales del conjunto filtrado; incluye todos los estados. Cobros acreditados y snapshots históricos se muestran por separado. Los casos sin atribución financiera se excluyen de abonados y saldos hasta conciliación. TICKET es un documento interno, no facturación electrónica.</p>
                </div>
                <section class="admission-availability"><div class="admission-section-head"><h2>Turnos y disponibilidad</h2><span>Fecha inicial del filtro</span></div><p id="admission-availability-note"></p><div id="admission-availability" class="admission-availability-grid"></div></section>
            </section>
            <aside class="admission-detail" aria-label="Detalle de cita"><div class="admission-section-head"><h2>Detalle de cita</h2></div><div id="admission-detail"><p class="admission-empty">Selecciona una cita para consultar su información y acciones autorizadas.</p></div></aside>
        </div>
    </main></div>
    @include('templates.footer')
</div>
@endsection
@section('script_data')
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/global/global.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/js/custom.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/agenda-shell.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/js/deznav-init.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/admission-board.js') }}"></script>
@endsection
