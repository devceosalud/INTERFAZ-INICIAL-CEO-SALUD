/* Navegación única: dropdown desktop y acordeón dentro del drawer móvil. */
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('erp-shell-toggle');
    const navigation = document.getElementById('erp-shell-navigation');

    if (!toggle || !navigation) {
        return;
    }

    const mobile = window.matchMedia('(max-width: 1100px)');
    const modules = Array.from(navigation.querySelectorAll('[data-erp-nav-module]'));
    const closeTargets = Array.from(document.querySelectorAll('[data-erp-shell-close]'));
    const overlay = document.querySelector('.erp-shell-overlay');
    let closeTimer = null;
    let pinnedModule = null;

    function setModule(module, open) {
        if (!module) {
            return;
        }
        module.classList.toggle('is-open', open);
        const trigger = module.querySelector('[data-erp-nav-trigger]');
        if (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function closeModules(except) {
        modules.forEach((module) => {
            if (module !== except) {
                setModule(module, false);
            }
        });
    }

    function openDrawer() {
        document.body.classList.add('erp-nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        if (overlay) {
            overlay.hidden = false;
        }
        const active = navigation.querySelector('[data-erp-nav-module].is-active');
        if (active) {
            setModule(active, true);
        }
    }

    function closeDrawer() {
        document.body.classList.remove('erp-nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        if (overlay) {
            overlay.hidden = true;
        }
        closeModules();
    }

    toggle.addEventListener('click', function () {
        if (document.body.classList.contains('erp-nav-open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });

    closeTargets.forEach((target) => target.addEventListener('click', closeDrawer));

    modules.forEach((module) => {
        const trigger = module.querySelector('[data-erp-nav-trigger]');
        if (!trigger) {
            return;
        }

        trigger.addEventListener('click', function (event) {
            event.stopPropagation();
            if (mobile.matches) {
                const willOpen = !module.classList.contains('is-open');
                closeModules(module);
                setModule(module, willOpen);
                return;
            }

            if (pinnedModule === module) {
                pinnedModule = null;
                setModule(module, false);
                return;
            }

            pinnedModule = module;
            closeModules(module);
            setModule(module, true);
        });

        module.addEventListener('mouseenter', function () {
            if (mobile.matches) {
                return;
            }
            if (pinnedModule) {
                return;
            }
            window.clearTimeout(closeTimer);
            closeModules(module);
            setModule(module, true);
        });

        module.addEventListener('mouseleave', function () {
            if (mobile.matches) {
                return;
            }
            if (pinnedModule === module) {
                return;
            }
            closeTimer = window.setTimeout(() => setModule(module, false), 180);
        });

        module.addEventListener('focusin', function () {
            if (!mobile.matches) {
                closeModules(module);
                setModule(module, true);
            }
        });

        module.addEventListener('focusout', function () {
            if (mobile.matches) {
                return;
            }
            window.setTimeout(function () {
                if (!module.contains(document.activeElement)) {
                    setModule(module, false);
                }
            }, 0);
        });
    });

    navigation.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', function () {
            if (mobile.matches) {
                closeDrawer();
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (!mobile.matches && !navigation.contains(event.target)) {
            pinnedModule = null;
            closeModules();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        if (mobile.matches && document.body.classList.contains('erp-nav-open')) {
            closeDrawer();
            toggle.focus();
        } else {
            pinnedModule = null;
            closeModules();
        }
    });

    mobile.addEventListener('change', function () {
        pinnedModule = null;
        closeDrawer();
    });
});
