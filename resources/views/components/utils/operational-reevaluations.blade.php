@props(['compact'=>false])
@if(config('scheduling.enabled') && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS) && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::VIEW))
<section class="operational-review-panel clarity-mask {{ $compact?'is-compact':'' }}" data-clarity-mask="true" aria-label="Reevaluaciones"
    data-operational-reevaluations="{{ route('scheduling.mvp.admission.reevaluations') }}" data-detail-base="{{ route('scheduling.mvp.admission') }}">
    <header class="operational-review-heading"><div><h2>Reevaluaciones</h2><p>Misma cita · jornada de Lima · sin nuevo cobro</p></div></header>
    <div class="operational-review-tabs" role="tablist" aria-label="Estado de reevaluaciones">
        <button type="button" role="tab" data-review-group="active" aria-selected="true">Listos <span data-review-count="active">0</span></button>
        <button type="button" role="tab" data-review-group="pending" aria-selected="false" tabindex="-1">Pendientes <span data-review-count="pending">0</span></button>
        <button type="button" role="tab" data-review-group="history" aria-selected="false" tabindex="-1">Retirados / inactivos <span data-review-count="history">0</span></button>
    </div>
    <p class="operational-review-update" role="status" aria-live="polite">Consultando reevaluaciones…</p><div data-review-rows role="tabpanel"></div>
</section>
@once
<link rel="stylesheet" href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/operational-reevaluations.css') }}">
<script defer src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/operational-reevaluations.js') }}"></script>
@endonce
@endif
