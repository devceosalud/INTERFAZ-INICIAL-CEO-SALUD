# Verificación del hotfix

## Resultados finales

| Comprobación | Resultado |
|---|---|
| PHP completo 8.3.35 / SQLite en memoria | 1026 pruebas, 8391 aserciones, cero fallos/errores/omisiones |
| Hotfix específico | 13 pruebas, 161 aserciones |
| JavaScript completo | 305/305 |
| Navegador local | Marcado/desmarcado, tres roles, filtros, teclado, contraste y móvil verificados |
| CSRF real sin token | HTTP 419 |
| Snapshot de diez citas y tablas protegidas | Idéntico; LEGADO conservado |

## Automatización

- PHP: suite completa con PHP 8.3.35 portátil oficial y SQLite en memoria. Configuración phpunit.xml fuerza entorno testing y servicios ficticios. El hash del ZIP del runtime se verificó contra php.net: 25a8e2ac9ff30f1d768d1447c09a600617fa6e6082729f6e95f008b59c91fe45.
- Pruebas nuevas: tests/Feature/Scheduling/ManualReevaluationTest.php (13 casos). Cubren marcar/desmarcar sin exámenes, auditoría, reintentos, rechazo de claves reutilizadas, conflictos de versión y ABA, roles y permisos revocados, cuenta inactiva, privacidad de reservas, estados inválidos, campos económicos manipulados, filtros/paginación/totales, prioridad visual, eventos con igual hora, revisión del feed y rollback si falla la auditoría.
- JavaScript: node --test tests/JavaScript/*.test.js, 305/305 aprobadas. Las tres nuevas comprueban ocultamiento visual/accesible del chip heredado sin retirarlo del layout, preservación de avisos operativos y conservación de LEGADO/etiqueta para el detalle.
- CI exclusivo: .github/workflows/admission-manual-reevaluation-checks.yml ejecuta la suite PHP y JavaScript completa y adjunta JUnit. Tiene permisos de lectura y no contiene pasos de despliegue.

Se detectó inicialmente que PHP 8.0.30/SQLite antiguo de XAMPP no soporta código y DDL que ya estaban en main. Esa ejecución no se presenta como aprobada: 18 fallos, 1 error y 1 omisión. La validación completa se realizó con PHP 8.3 y su SQLite actualizado, sin modificar XAMPP ni código ajeno al hotfix. Runtime oficial: https://www.php.net/downloads.php?os=windows&version=8.3 . No se ejecutó una carrera real contra MariaDB; las pruebas de conflictos simulan lecturas concurrentes y verifican la versión y la atomicidad en SQLite.

## Navegador local

- Acceso real mediante login/CSRF para ADMISION, ADMINISTRADOR y COMERCIAL.
- Verde oscuro → marcado/naranja → desmarcado/verde; persistencia al recargar.
- Reevaluaciones incluye solo marcadas; el desmarcado sale de la pestaña. Filtro de paciente inexistente devuelve cero sin abandonar la pestaña. Cambio por teclado entre pestañas.
- Salida registrada permanece turquesa y tachada aunque tenga marca manual.
- COMERCIAL consulta sin checkbox; ADMINISTRADOR y ADMISION tienen checkbox autorizado.
- Chips principales visibles, 12–12,5 px, 700 y aproximadamente 24 px de altura. Ajuste final Agenda heredada invisible solo en tabla, ausente de innerText y de accesibilidad; el detalle la conserva. Fila de referencia sin variación de altura: 91,86 px.
- En viewport móvil la página no tiene desbordamiento horizontal; el scroll se limita a la tabla existente. Cero errores JavaScript en consola.
- PATCH real sin token CSRF: HTTP 419. Escrituras con CSRF desde UI aprobadas.
- Comparación completa contra snapshot anterior: las diez citas, pagos, comprobantes, indicaciones y horarios ficticios permanecen idénticos; solo se agregan operaciones/eventos de marca. LEGADO se conserva.

Capturas: [verde](evidence/admision-verde.jpg), [naranja y salida](evidence/reevaluaciones-naranja.jpg), [móvil](evidence/admision-movil.jpg).

## Reproducción manual aislada

Usar un checkout sin .env y crear los directorios bootstrap/cache, storage/framework/{cache/data,sessions,views}, storage/logs y storage/app/hotfix-qa. Instalar dependencias bloqueadas. Crear un archivo vacío storage/app/hotfix-qa/browser.sqlite.

Ejecutar php tests/Support/manual-reevaluation-qa-seed.php y luego php -S 127.0.0.1:8097 -t public tests/Support/manual-reevaluation-qa-router.php. El seed destruye exclusivamente esa SQLite ficticia después de comprobar su ruta y entorno. No ejecutarlo en un checkout con .env. Cuentas ficticias: admision@example.invalid, administrador@example.invalid y comercial@example.invalid; contraseña local fija only-local-manual-qa-2026, sin acceso fuera de este fixture. Abrir / y luego /scheduling-mvp/admission.

Al terminar, ejecutar php tests/Support/manual-reevaluation-qa-verify.php con el mismo runtime usado para el seed (para comparar también los tipos PDO estrictamente) y detener el servidor. La SQLite, logs y runtime portátil se quedan en storage y no forman parte del PR. No usar estos helpers para producción.
