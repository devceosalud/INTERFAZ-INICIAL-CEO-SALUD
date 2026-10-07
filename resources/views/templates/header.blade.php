<header class="erp-shell-header">
    <div class="erp-shell-actions">
        <button type="button" class="erp-shell-theme dz-theme-mode" aria-label="Cambiar tema">
            <i id="icon-light" class="fas fa-sun" aria-hidden="true"></i>
            <i id="icon-dark" class="fas fa-moon" aria-hidden="true"></i>
            <span>Tema</span>
        </button>

        <div class="dropdown erp-shell-user">
            <button type="button" class="erp-shell-user__trigger" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="erp-shell-user__name">{{ auth()->user()->name }}</span>
                <span class="erp-shell-user__chevron" aria-hidden="true"></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end erp-shell-user__menu">
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="erp-shell-user__logout">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </div>
</header>
