@extends('layouts.app')

@section('body')
    <main class="container py-4" id="agenda-heatmap" data-endpoint="{{ route('scheduling.mvp.agenda.heatmap.data') }}">
        <h1>Mapa de clics · ERP</h1>
        <p>Preview sanitizado: zonas de la interfaz, sin datos ni capturas de sesiones reales.</p>
        <p>Rango de fechas en {{ $timezone }}; almacenamiento de eventos en UTC.</p>
        <a href="{{ route('scheduling.mvp.agenda') }}">Volver a Agenda</a>
        @unless ($installed)
            <p role="alert">La tabla de telemetría todavía no está instalada. Agenda continúa operativa.</p>
        @endunless
        <form id="agenda-heatmap-filters" class="d-flex flex-wrap gap-3 my-3">
            <label>Módulo <select name="screen" id="heatmap-module">@foreach($modules as $key => $module)<option value="{{ $key }}">{{ $module['label'] }}</option>@endforeach</select></label>
            <label>Desde <input type="date" name="from" value="{{ now($timezone)->subDays(6)->toDateString() }}" required></label>
            <label>Hasta <input type="date" name="to" value="{{ now($timezone)->toDateString() }}" required></label>
            <label>Vista <select name="view_mode" id="heatmap-view"></select></label>
            <label>Zona <select name="zone" id="heatmap-zone"></select></label>
            <button type="submit" class="btn btn-primary" @disabled(!$installed)>Consultar</button>
        </form>
        <p id="agenda-heatmap-status" role="status" aria-live="polite"></p>
        <canvas id="agenda-heatmap-canvas" width="1200" height="760" style="width:100%;max-width:1200px;border:1px solid #ccc" aria-label="Preview sanitizado del módulo con densidad de clics por zona"></canvas>
        <p>Geometría v2: cada clic se normaliza dentro de su zona y se proyecta en esta plantilla estática. Los paneles y modales se muestran separados para comparar actividad; no reproduce píxeles, scroll ni contenido real. Los eventos v1 se conservan, pero no se mezclan con v2.</p>
    </main>
@endsection
@section('script_data')
    <script src="{{ asset('js/scheduling/agenda-click-telemetry.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda-heatmap.js') }}"></script>
@endsection
