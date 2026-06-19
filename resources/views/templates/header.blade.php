<div class="header">
    <div class="header-content">
        <nav class="navbar navbar-expand">
            <div class="collapse navbar-collapse justify-content-between">
                <div class="header-left">
                    <div class="dashboard_bar">
                        Dashboard
                    </div>
                </div>

                <ul class="navbar-nav header-right">
                    <li class="nav-item dropdown notification_dropdown">
                        <a class="nav-link bell dz-theme-mode" href="javascript:void(0);">
                            <i id="icon-light" class="fas fa-sun"></i>
                            <i id="icon-dark" class="fas fa-moon"></i>
                        </a>
                    </li>
                    <li class="nav-item dropdown header-profile">
                        <a class="nav-link" href="javascript:;" role="button" data-bs-toggle="dropdown">
                            <img src="{{ asset('assets/images/profile/12.png') }}" width="20" alt="">
                            <div class="header-info">
                                <span>Hola,<strong> {{ auth()->user()->name }}  </strong></span>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class='dropdown-item ai-icon' href='/app-profile'>
                                <svg id="icon-user1" xmlns="http://www.w3.org/2000/svg" class="text-primary"
                                    width="18" height="18" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span class="ms-2">Portafolio</span>
                            </a>
                            <a class='dropdown-item ai-icon' href='/email-inbox'>
                                <svg id="icon-inbox" xmlns="http://www.w3.org/2000/svg" class="text-success"
                                    width="18" height="18" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path
                                        d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                    </path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <span class="ms-2">Mensajes </span>
                            </a>

                            <form class="mx-auto d-flex justify-content-center" action="{{ route('admin.logout') }}"
                                method="POST">
                                @csrf
                                <input type="submit" class="btn btn-danger" value="Cerrar">
                            </form>

                        </div>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</div>
