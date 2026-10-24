<div class="erp-shell-overlay" data-erp-shell-close hidden></div>

<nav class="erp-shell-nav" id="erp-shell-navigation" aria-label="Navegación principal">
    <div class="erp-shell-nav__drawer">
        <div class="erp-shell-nav__mobile-head">
            <img src="{{ asset('assets/images/logo-full.png') }}" alt="CEO Salud">
            <button type="button" data-erp-shell-close aria-label="Cerrar menú">Cerrar</button>
        </div>

        <ul class="erp-nav" id="erp-nav-tree">
            @include('templates.nav.module', [
                'key' => 'common-home',
                'label' => 'Inicio',
                'activePatterns' => ['admin.dashboard.*'],
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'admin.dashboard.index', 'patterns' => ['admin.dashboard.*']],
                ],
            ])

            @if (config('scheduling.enabled') && (\App\Support\Scheduling\AdmissionAccess::operational(auth()->user()) || (\App\Support\Scheduling\ClinicalCapability::isMedical(auth()->user()) && auth()->user()->can(\App\Support\Scheduling\ClinicalCapability::VIEW))))
                @include('templates.nav.module', ['key'=>'operational-admission', 'label'=>'Admisión',
                    'activePatterns'=>['scheduling.mvp.admission*'], 'items'=>[
                        ['label'=>'Agenda de admisión', 'route'=>'scheduling.mvp.admission', 'patterns'=>['scheduling.mvp.admission*']],
                    ]])
            @endif

            @if (!\App\Support\Scheduling\ClinicalCapability::isMedical(auth()->user()) && !auth()->user()->hasAnyRole(\App\Support\Scheduling\MvpOperationalAccess::LEGACY_ROLES)
                && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
                && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::VIEW))
                @include('templates.nav.mvp')
            @endif

            @if (auth()->user()->getRoleNames()->contains('ADMINISTRADOR'))
                @include('templates.nav.administrador')
            @endif

            @if (auth()->user()->getRoleNames()->contains('ADMISION'))
                @include('templates.nav.admision')
            @endif

            @if (auth()->user()->getRoleNames()->contains('COMERCIAL'))
                @include('templates.nav.comercial')
            @endif

            @if (auth()->user()->getRoleNames()->contains('RECEPCION'))
                @include('templates.nav.recepcion')
            @endif
        </ul>
    </div>
</nav>
