<div class="deznav">
    <div class="deznav-scroll">
        <ul class="metismenu" id="menu">
            <li>
                <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
                    <i class="flaticon-381-home"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
                <ul aria-expanded="false">
                    <li><a href="{{ route('admin.dashboard.index') }}">Inicio</a></li>
                </ul>
            </li>

            {{-- ENLACES ADM --}}
            @if (auth()->user()->getRoleNames()->contains('ADMINISTRADOR'))
                @include('templates.nav.administrador')
            @endif
            {{-- ENLACES ADM --}}




            {{-- ENLACES ADMISION --}}
            @if (auth()->user()->getRoleNames()->contains('ADMISION'))
                @include('templates.nav.admision')
            @endif
            {{-- ENLACES ADMISION --}}


            @if (auth()->user()->getRoleNames()->contains('COMERCIAL'))
                @include('templates.nav.comercial')
            @endif


            {{-- ENLACES RECEPCION --}}
            @if (auth()->user()->getRoleNames()->contains('RECEPCION'))
                @include('templates.nav.recepcion')
            @endif
            {{-- ENLACES RECEPCION --}}




            {{-- ENLACES CAJA --}}

            {{-- ENLACES FACTURACION --}}



            {{-- ENLACES GERENCIA --}}



        </ul>

        {{--
        <a href='#'>
            <div class="plus-box">
                <p class="fs-16 font-w500 mb-1">Celendario y Horarios</p>
                <p class="text-white fs-26"> <i class="las la-long-arrow-alt-right"></i></p>
            </div>
        </a>
        

        <div class="copyright">
            <p class="fs-14 font-w200"><strong class="font-w400">ERP CEO SALUD </strong> © 2026
                Todos los Derechos Reservados</p>
            <p class="fs-12">Diseño 1 <span class="heart"></span> Maquetación</p>
        </div>
        --}}
    </div>
</div>
