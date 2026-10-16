# Despliegue controlado — Etapa A Agenda + UX/OCR

Estado: runbook, NO certifica producción ni autoriza modificarla.
PR #35: semáforo, Nicol/salida, Profesional, 2 migraciones.
PR #36: adelantos compactos, OCR, validaciones. Sin migraciones adicionales.
Hostinger HEAD observado previamente: 2b4f1e8db81b8629aa8508cb41f45b0a263e660c.

## Antes de fusionar
- Volver a confirmar que Hostinger usa main con AUTO-DESPLIEGUE DESACTIVADO en hPanel **ahora**, no solo en captura antigua. Si está activo no fusionar.
- Hacer respaldo actual e íntegro de DB, archivos, storage/documentos privados y configuración, sin publicar secretos. Confirmar restauración practicable fuera de producción.
- Elegir ventana sin usuarios ni pagos activos, tener autorización, persona responsable y plan de rollback.
- Revisar permisos de Nicol con diagnóstico de solo lectura descrito en docs/AGENDA_35_REVISION_FINAL.md, sin conceder permisos sin autorización.
- CI de ambos PR en verde. Fusionar primero #35. Reorientar después #36 a main y revisar diff para fusionar solamente UX/OCR.
- Anotar commit main final real después de las fusiones.

## Despliegue manual Hostinger
En SSH antes de actuar:
    cd ~/domains/srv-prd01.ceosalud.com/public_html
    git rev-parse HEAD
    git status --short
    php artisan migrate:status --no-ansi | tail -n 8

Confirmado respaldo y ventana, activar mantenimiento:
    php artisan down

En hPanel Git usar Redistribuir una vez al SHA final de main. Comprobar que mantenimiento no se perdió. En SSH:
    git rev-parse HEAD
    git status --short
    php -l database/migrations/2026_10_15_230000_add_explicit_diabetic_wound_flag.php
    php -l database/migrations/2026_10_15_231000_add_explicit_appointment_departure.php
    php artisan migrate:status --no-ansi | tail -n 8

Solo deben figurar pendientes las dos migraciones nuevas. Tabla appointments debe existir. Previsualizar una a una (lectura; ADD COLUMN nullable):
    php artisan migrate --path=database/migrations/2026_10_15_230000_add_explicit_diabetic_wound_flag.php --pretend --force --no-ansi
    php artisan migrate --path=database/migrations/2026_10_15_231000_add_explicit_appointment_departure.php --pretend --force --no-ansi

Con preview coherente, respaldos confirmados y autorización, ejecutar de forma dirigida:
    php artisan migrate --path=database/migrations/2026_10_15_230000_add_explicit_diabetic_wound_flag.php --force --no-ansi
    php artisan migrate --path=database/migrations/2026_10_15_231000_add_explicit_appointment_departure.php --force --no-ansi

Comprobar:
    php artisan migrate:status --no-ansi | tail -n 8
    php artisan tinker --execute='foreach(["has_diabetic_wound","hora_salida"] as $name){echo $name,": ",Schema::hasColumn("appointments",$name)?"EXISTE":"FALTA",PHP_EOL;}'
    php artisan --version

No ejecutar PHPUnit, migrate:fresh, migrate:reset, migrate:rollback, composer update ni cambios SQL manuales en producción. Si correcto, abrir mantenimiento:
    php artisan up

## Smoke navegador
1. Agenda y filtro Profesional; admin ve creador solo en citas permitidas. Reserva privada asignada la ve solo su comercial; sin dueño la ve creador.
2. Blanco sin pago, amarillo con pago, turquesa llegada, rojo cancelación/retiro; chips fucsia/plomo solo en nombre, sin pintar slot. Cancelada roja y confirmada a misma hora siguen visibles y la cancelada no ocupa.
3. Nicol: llegada, ATENDIDO, Registrar salida explícita solo tras ATENDIDO; tachado posterior, sin tocar dinero.
4. Cotización, cita doble, Guardar reserva (sin pago), Confirmar cita bajo regla de 50%. No crear duplicados.
5. Adelanto: OCR JPG/PNG ficticio para importe y número operación con ceros iniciales; sugerencias revisables, no acredita pago. PDF manual.
6. Errores 422 visibles con foco y valores conservados. Escritorio y móvil. Revisar respuesta del backend y laravel.log sin datos sensibles.
7. Confirmar funcionamiento del ERP existente, admisión, roles y caja sin escribir cobros de prueba.

## Rollback
- Ante error tras merge/despliegue, detener nuevas operaciones y reponer solo código al commit anterior usando flujo de rollback de Hostinger, conservando las dos columnas y datos/auditoría.
- Nunca hacer migrate:rollback ni sobreescribir pagos posteriores restaurando DB antigua.
- Si falla migración mantener mantenimiento; no repetir si ya existe columna y hay discrepancia con migrations.
- Si se vuelve temporalmente a código anterior, suspender la función clínica HERIDA/salida porque la UI vieja no la representa.

## Pendientes fuera del alcance
Yape Corporativo/Mari y pagos combinados (#28), aviso 1h postoperatorio (#29), numeración llegada (#30), break (#31), notas privadas Nicol (#32). No declararlos desplegados.
