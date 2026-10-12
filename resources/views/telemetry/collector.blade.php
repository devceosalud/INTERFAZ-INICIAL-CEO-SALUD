@if(config('scheduling.enabled') && auth()->check())
    <span id="ui-telemetry-config" data-endpoint="{{ route('ui.telemetry.click-events') }}" hidden></span>
    <script src="{{ ($agendaAssetVersions ?? false) ? \App\Support\Scheduling\AgendaAssets::url('js/scheduling/agenda-click-telemetry.js') : asset('js/scheduling/agenda-click-telemetry.js') }}"></script>
@endif
