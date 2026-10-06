@if(config('scheduling.enabled') && auth()->check())
    <span id="ui-telemetry-config" data-endpoint="{{ route('ui.telemetry.click-events') }}" hidden></span>
    <script src="{{ asset('js/scheduling/agenda-click-telemetry.js') }}"></script>
@endif
