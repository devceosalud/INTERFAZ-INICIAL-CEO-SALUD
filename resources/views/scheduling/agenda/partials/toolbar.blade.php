<header class="agenda-commandbar" data-ui-zone="toolbar">
    <div class="agenda-commandbar__identity">
        <span class="agenda-commandbar__eyebrow">Agendamiento</span>
        <h1>Agenda operativa</h1>
    </div>

    <div class="agenda-commandbar__filters" aria-label="Filtros de agenda">
        <label class="agenda-field agenda-field--site">
            <span class="agenda-field__label">Sede</span>
            <select class="agenda-field__input" id="agenda-site">
                <option value="">Todas las sedes</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->nombre }}</option>
                @endforeach
            </select>
        </label>

        <label class="agenda-field agenda-field--specialty">
            <span class="agenda-field__label">Especialidad</span>
            <select class="agenda-field__input" id="agenda-specialty">
                <option value="">Todas</option>
                @foreach ($specialties as $specialty)
                    <option value="{{ $specialty->id }}">{{ $specialty->nombre }}</option>
                @endforeach
            </select>
        </label>

        <div class="agenda-field agenda-field--doctor">
            <span class="agenda-field__label">Médico</span>
            <button type="button" class="agenda-filter-button" id="agenda-doctor-filter">
                <span id="agenda-doctor-filter-summary">Primer médico disponible</span>
            </button>
        </div>

        <label class="agenda-field agenda-field--date">
            <span class="agenda-field__label">Fecha</span>
            <input type="date" class="agenda-field__input" id="agenda-date" value="{{ $today }}">
        </label>
    </div>

    <div class="agenda-commandbar__actions">
        <div class="agenda-btn-group" role="group" aria-label="Navegación de fecha">
            <button type="button" class="agenda-btn agenda-btn--step" id="agenda-prev" aria-label="Periodo anterior">Anterior</button>
            <button type="button" class="agenda-btn" id="agenda-today">Hoy</button>
            <button type="button" class="agenda-btn agenda-btn--step" id="agenda-next" aria-label="Periodo siguiente">Siguiente</button>
        </div>

        <div class="agenda-btn-group agenda-view-switch" role="group" aria-label="Vista de agenda">
            @foreach ($views as $view)
                <button type="button" class="agenda-btn agenda-btn--view @if ($view === 'dia') is-active @endif"
                    data-view="{{ $view }}" aria-pressed="{{ $view === 'dia' ? 'true' : 'false' }}">
                    {{ ucfirst($view) }}
                </button>
            @endforeach
        </div>
    </div>
</header>
