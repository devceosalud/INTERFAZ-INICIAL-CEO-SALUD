@extends('layouts.app')
@section('body_class','admission-viewport')
@section('css_data')
<link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/admission-board.css') }}" rel="stylesheet">
<link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/medical-board.css') }}" rel="stylesheet">
@endsection
@section('body')
<div id="main-wrapper" class="menu-toggle">
@include('templates.nav-header')
@include('templates.header')
@include('templates.sidebar')
<div class="content-body admission-page"><main id="medical-board" class="admission-board"
    data-feed="{{ route('scheduling.medical.feed') }}" data-base="{{ url('/scheduling-mvp/medical/appointments') }}">
    <header class="admission-heading"><div><p class="admission-eyebrow">ATENCIÓN MÉDICA</p><h1>Mi agenda médica</h1><p>{{ $doctor->nombre }}</p></div></header>
    <form id="medical-filters" class="admission-filters" autocomplete="off">
        <div class="admission-filter-primary">
            <label>Fecha<input type="date" name="fecha" value="{{ $today }}" required></label>
            <label>Paciente<input type="search" name="patient" maxlength="100" placeholder="Nombre o historia clínica"></label>
            <label>Sede<select name="site_id"><option value="">Todas mis sedes</option>@foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->nombre }}</option>@endforeach</select></label>
            <button type="submit" class="admission-btn admission-btn--primary">Actualizar</button>
        </div>
    </form>
    <p id="medical-notice" class="admission-notice" role="status" aria-live="polite"></p>
    <div class="admission-workspace">
        <section class="admission-main" aria-label="Mis citas">
            <div class="admission-table-card"><div class="admission-table-scroll">
                <table class="admission-table"><thead><tr><th>Hora</th><th>Paciente</th><th>Servicio / sede</th><th>Atención</th></tr></thead><tbody id="medical-rows"></tbody></table>
            </div><div class="admission-pagination"><button id="medical-prev" type="button" class="admission-btn">Anterior</button><span id="medical-page"></span><button id="medical-next" type="button" class="admission-btn">Siguiente</button></div></div>
        </section>
        <aside class="admission-detail" aria-label="Atención médica"><div class="admission-section-head"><h2>Atención de mi paciente</h2><button id="medical-back" type="button" class="admission-btn">Volver a la lista</button></div>
            <div id="medical-detail" class="admission-detail-body"><p class="admission-empty">Selecciona una cita para consultar sus procedimientos y atención.</p></div>
        </aside>
    </div>
</main></div>
@include('templates.footer')
</div>
@endsection
@section('script_data')
<script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
<script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
<script src="{{ asset('assets/js/custom.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/agenda-shell.js') }}"></script>
<script src="{{ asset('assets/js/deznav-init.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/admission-board.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/medical-board.js') }}"></script>
@endsection
