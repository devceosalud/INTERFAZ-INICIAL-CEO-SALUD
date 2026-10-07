# ERP Shell — navegación global compacta

> **LOCAL, SIN COMMIT Y NO APROBADO PARA DEPLOY.**

Este ajuste transversal acompaña la revisión de MVP-2C, pero no cambia reglas de agenda,
autorización ni negocio. Se documenta por separado para permitir un commit independiente.

## 1. Shell AS-IS

- `resources/views/layouts/app.blade.php` gobierna estilos, scripts y Livewire.
- Las 33 páginas autenticadas ensamblan los mismos parciales `nav-header`, `header` y
  `sidebar`; el login usa el layout sin esos parciales.
- El sidebar construía su contenido con un bloque común y cuatro parciales por rol:
  `ADMINISTRADOR`, `ADMISION`, `COMERCIAL` y `RECEPCION`.
- La visibilidad se resolvía por nombre de rol en Blade. La autorización real ya estaba en
  middleware y rutas, y continúa allí.
- DexignZone imponía cabecera de 5.5 rem, sidebar fijo de aproximadamente 18 rem y margen
  izquierdo al contenido.
- No existía una marcación activa uniforme por módulo y opción.

## 2. Arquitectura nueva

La misma jerarquía Blade se renderiza una sola vez:

- desktop: navegación superior y dropdown vertical compacto;
- tablet/móvil: drawer temporal y los mismos módulos como acordeones;
- `templates.nav.module` resuelve estructura, módulo activo y opción activa;
- los parciales por rol conservan exactamente las ramas de visibilidad heredadas;
- el enlace a Agenda solo aparece si el feature flag está activo y el usuario ya posee
  `appointment.mvp.access` y `appointment.view`.

No se creó una matriz de permisos frontend. Ocultar o mostrar enlaces no sustituye los
middlewares existentes.

## 3. Interacción

En desktop:

- hover abre el dropdown;
- click lo fija hasta otro click, click exterior o `Escape`;
- existe un retardo de cierre de 180 ms;
- `focus` de teclado abre el módulo y los enlaces mantienen orden tabular;
- módulo activo usa peso tipográfico y borde inferior;
- opción activa usa peso tipográfico, fondo neutro e indicador lateral.

En pantallas de hasta 1100 px:

- el header muestra logo, usuario y botón Menú;
- el drawer bloquea temporalmente el fondo;
- el módulo activo se abre al mostrar el drawer;
- un enlace cierra el drawer antes de navegar;
- `Escape`, overlay y botón Cerrar lo cierran.

## 4. Compatibilidad

- No se cambió ninguna ruta ni middleware.
- No se modificaron componentes Livewire.
- Las páginas heredadas conservan su contenido; solo desaparece el margen del sidebar.
- Dashboard, Pacientes, Responsables, Citas y Horarios se cubren con smoke tests de render.
- Agenda mantiene Día/Semana/Mes, motor de disponibilidad, permisos y feature flag.
- El control de tema existente se conserva en desktop.

## 5. Validación visual local

Se usa el preview SQLite desechable, usuario ficticio y servicios externos anulados. Las
capturas se emiten en la sesión de revisión y no se agregan al repositorio.

Resoluciones objetivo:

- 1920 × 1080;
- 1600 × 900;
- 1366 × 768;
- 1024 × 768;
- 390 × 844.

Escenarios: menú desktop cerrado, dropdown activo abierto, Agenda a ancho completo, Dashboard
heredado, navegación tablet cerrada/abierta, drawer móvil y Agenda móvil con intervalo
seleccionado.

## 6. Riesgos y pendientes

1. Los módulos heredados conservan su diseño original; algunos no aprovecharán el ancho hasta
   su propio retrabajo.
2. `deznav-init.js` sigue cargado desde páginas heredadas por compatibilidad con el tema, pero
   el nuevo shell no depende de `.deznav`.
3. La matriz local de roles/capacidades todavía debe reconciliarse antes de cualquier deploy.
4. Debe hacerse prueba operativa con cada rol real antes de considerar el shell aprobado.
