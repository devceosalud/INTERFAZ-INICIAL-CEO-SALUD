# CEO Salud · candidato de integración · 14/10/2026

Estado de trabajo, no certificación productiva. Rama `codex/rc-integracion-preproductiva`; worktree propio. Main y PR originales sin cambios. Ver HEAD/base de los nueve PR en `evidence/rc/pr-inputs.json`.

## Integración realizada

Partiendo de main `1a2c1895`, avance exclusivo de la rama RC por #5→#6→#7→#8→#9→#10→#12, luego merges locales de #11 y #13. Git integró los tres archivos compartidos de #13 sin conflictos. No se fusionaron PR de GitHub ni se modificó main. El bloque de instalación de Clarity debe conservarse sin cambios; el layout sí recibe el asset local de alertas para eliminar la dependencia funcional de CDN.

## Entorno aislado

MariaDB 11.8.9 nuevo, 127.0.0.1:33322, esquema `ceosalud_qa_rc`, datadir `.worktrees/.rc-mariadb-1189/data`; usuario local de QA. Guard estricto en IsolatedRcQa antes de cualquier migración. PHP CLI 8.3.35 de runtime previo verificado, sin `.env`, proveedores sustituidos por destinos loopback y dobles de mail/colas. Perfiles antiguos delegan a RC únicamente con `AGENDA_QA_RUNTIME=rc-11.8.9`. No tocar 33318–33321. No Docker disponible; Windows no acredita equivalencia con Hostinger Linux/PHP web.

Chrome instalado 155.0.8059.79 y Playwright empaquetado disponibles, sin descargas. Preparar contextos/cookies independientes y comprobar viewport en página con meta viewport; about:blank móvil usa por defecto layout 980 y no acredita CSS móvil. Runtime node_repl agotó tiempo dos veces; no hubo rechazo de autorización. Ediciones continúan con apply_patch/PowerShell y Node CLI.

## Correcciones y QA en curso

- [x] Integrar los nueve HEAD publicados y conservar historial.
- [x] Polling de Nicole 5 s: no solapamiento, visibilidad, edición/confirmaciones, respuesta tardía, revisión por actor, respuesta mínima si no cambia, error conserva datos. Solo identidad de la página visible; paginación ajustada si disminuyen resultados.
- [x] JS focal 12/12 y PHP focal Nicole 14/194 aprobados. No son la regresión final.
- [ ] Horarios: defecto comprobado, escritura depende solo de ADMISION/COMERCIAL y bloquea ADMIN; falta capacidad efectiva. Revisar protección de citas confirmadas/fondeadas y concurrencia.
- [ ] Colisión de reservas: faltan oferta y conversión explícita a ADICIONAL sin recrear cita/pago. RegularReservationAvailability ya bloquea pagos al intervalo perdido. No convertir automáticamente.
- [ ] Pruebas reales de permisos dinámicos y sesiones independientes, negativos CSRF con verificación real (APP_ENV=testing omite CSRF por defecto; el router QA debe forzarlo).
- [ ] Jornada integral UI y persistencia; variantes históricas/denegaciones/concurrencia.
- [ ] OCR ficticio adicional BBVA; navegador Yape/BCP/ambiguo/ilegible, privacidad y assets.
- [ ] Bench Nicole 100/500/2000: medir antes de optimizar el posible costo cuadrático de AppointmentEconomicPosition, contar SQL, p50/p95/bytes/memoria, simultaneidad.
- [ ] Backup/restore ensayado exclusivamente con BD/adjuntos ficticios; manual sin restaurar BD vieja sobre pagos nuevos.
- [ ] Análisis estático y suites completas sobre árbol final, todas reejecutadas; matriz R01–R30 ampliada con evidencia y anexos; ocho entregables y PR Draft contra main.

## Riesgos ya conocidos

Clarity intacto, revisión de privacidad/admisibilidad pendiente y separada; requisito antes de datos reales. No ejecutar Hostinger. R25–R30 solo verificar bloqueo seguro existente; no implementar ledger general. R21–R23 cancelados. Sede real aún requiere procedimiento controlado y horarios futuros explícitos; no backfill de datos heredados. La ausencia de staging y de preflight web productivo impide certificar producción. Dictamen provisional NO-GO hasta completar/verificar criterios.

## Checkpoint de ejecución (todavía no cierre)

- PHP completo integrado: 866 pruebas / 6.719 aserciones; JavaScript 258/258. Se repetirán si cambia código relevante.
- Horarios: capacidad explícita doctor.schedule.manage para los tres roles aprobados, versión optimista, bloqueo transaccional y auditoría. 107 pruebas focales / 530 aserciones. Crear, Día/Semana/Mes y revocación comprobados en Chrome; editar/inactivar y carreras reales todavía pendientes.
- Colisión: conversión explícita de la misma reserva a ADICIONAL, sin nuevo dinero ni confirmación. 25 pruebas / 258 aserciones; falta recorrido completo UI y concurrencia.
- Chrome 155: ADMIN creó seis trabajadores desde UI y siete contextos independientes iniciaron sesión; CREATE y gestión de horarios otorgados/revocados desde editor real y denegación backend comprobada. No es todavía la jornada completa.
- Nicole 100/500/2.000 con pagos atribuidos: proyección final p95 38,317 / 171,338 / 611,067 ms, 20 SQL, hasta 23.508 bytes; respuesta sin cambios 96 bytes. Medición de kernel caliente local Windows, no SLA de Hostinger ni prueba de carga multiusuario. Intento previo 2.000 p95 1.641,705 ms falló el objetivo propuesto y se preserva.
- Dos fallos de preparación/navegación preservados: router QA usaba API Log no disponible; SweetAlert global dependía de CDN bloqueado. Router corregido y asset local 11.22.4 con licencia, hash y lockfile; npm audit de ese paquete: 0 hallazgos. No representa auditoría de todas las dependencias.
- Agenda/Horarios abrían fecha UTC y Nicole fecha Lima después de 00:00 UTC. Se alinean valores predeterminados con operational_timezone, sin cambiar históricos ni zona global. Regresión de frontera y nueva navegación pendientes.
- Esperando decisión sobre solapamientos compatibles de horarios: política heredada avisa, no rechaza todos; no imponer nueva política sin respuesta.
- Resultados privados locales en storage/app/qa-rc (ignorados): no publicar cookies, contraseña ficticia ni tokens. Publicar únicamente evidencia saneada al cerrar.
