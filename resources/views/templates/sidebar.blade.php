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
