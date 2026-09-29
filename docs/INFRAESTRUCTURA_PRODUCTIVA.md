# Infraestructura productiva y recuperación

## 1. Estado general

**PENDIENTE DE PRODUCCIÓN:** no se accedió a hPanel, SSH, logs de despliegue, cron ni backups de la cuenta. Por tanto, la infraestructura efectiva continúa solo parcialmente descrita por código y antecedentes declarados.

Categorías usadas:

- **CONFIRMADO EN LOG:** evidencia fechada de ejecución.
- **CONFIRMADO EN PANEL:** configuración visible en el panel de la cuenta.
- **CONFIRMADO EN CÓDIGO:** comportamiento/default observable en el repositorio.
- **ANTECEDENTE DECLARADO:** información comunicada por Rodrigo, no reproducida en esta fase.
- **NO OBSERVADO:** no existe configuración versionada en el repositorio revisado.
- **PENDIENTE DE PRODUCCIÓN:** requiere panel, log o comando seguro en producción.

## 2. Versiones y configuración

| Elemento | Evidencia disponible | Estado productivo |
|---|---|---|
| PHP requerido | `composer.json`: `^8.0` | Versión efectiva PENDIENTE |
| PHP local | 8.0.30 | No demuestra producción |
| Laravel bloqueado | `composer.lock`: `laravel/framework` `9.x-dev` | Versión instalada productiva PENDIENTE |
| Livewire | `v2.12.8` en lock | Instalación productiva PENDIENTE |
| Spatie Permission | `6.25.0` en lock | Instalación productiva PENDIENTE |
| Timezone Laravel | `config/app.php`: `UTC` fijo | PENDIENTE si config cache/código desplegado difiere |
| Queue default | `sync` | PENDIENTE valor efectivo |
| Cache default | `file` | PENDIENTE valor efectivo |
| Session default | `file` | PENDIENTE valor efectivo |
| Mail default | `smtp` | Solo indica adaptador por defecto; configuración real PENDIENTE |
| Scheduler en código | Método vacío, solo ejemplo comentado | Confirmado en código |
| Jobs de aplicación | No existe `app/Jobs` | No observado en repositorio |
| APP_ENV | Local es `local`; testing fuerza `testing` | Productivo PENDIENTE |
| APP_DEBUG | Local/testing es `true` | Productivo PENDIENTE; debe verificarse con prioridad |
| MySQL/MariaDB | Configuración admite MySQL | Versión efectiva PENDIENTE |
| Timezone PHP/DB | Sin evidencia productiva | PENDIENTE |

No se copiaron valores de APP_KEY, DB, SMTP, tokens ni API keys.

## 3. Despliegue actual

### 3.1 Flujo declarado

```text
GitHub devceosalud/INTERFAZ-INICIAL-CEO-SALUD
  -> rama main
  -> Hostinger
  -> public_html
```

Este flujo, junto con la afirmación de que se ejecuta `composer install`, es un **antecedente declarado**. No se obtuvo log o captura de panel en esta fase.

### 3.2 Evidencia del repositorio

- remoto `origin`: repositorio GitHub esperado;
- no existen workflows `.github`, Dockerfile, Procfile, manifiesto de despliegue, scripts Hostinger o configuración de supervisor versionados;
- existe `.htaccess` raíz;
- no hay evidencia versionada de npm/build, migrate, caches, reinicio de cola, cron o backup;
- el scheduler de Laravel no contiene tareas activas.

### 3.3 Matriz a verificar

| Operación | Log | Panel | Repositorio | Estado |
|---|---|---|---|---|
| Deploy desde `main` | No disponible | No disponible | Remoto GitHub confirmado | PENDIENTE / antecedente declarado |
| Destino `public_html` | No disponible | No disponible | No codificado | PENDIENTE / antecedente declarado |
| Hook/webhook automático | No disponible | No disponible | No codificado | PENDIENTE |
| `composer install` | No disponible | No disponible | No script post-deploy | PENDIENTE / antecedente declarado |
| `composer update` | No disponible | No disponible | No script local | PENDIENTE, debe descartarse explícitamente |
| `npm install`/build | No disponible | No disponible | `package.json` y Mix existen | PENDIENTE |
| `php artisan migrate` | No disponible | No disponible | No script local | PENDIENTE |
| `config:cache` | No disponible | No disponible | No script local | PENDIENTE |
| `route:cache` | No disponible | No disponible | No script local | PENDIENTE |
| `view:cache` | No disponible | No disponible | No script local | PENDIENTE |
| `queue:restart` | No disponible | No disponible | Sin jobs propios | PENDIENTE |
| Cron/scheduler | No disponible | No disponible | Scheduler vacío | PENDIENTE |
| Backups automáticos | No disponible | No disponible | No codificado | PENDIENTE |

La documentación pública de Hostinger indica que su integración Git puede ejecutar automáticamente `composer update` cuando encuentra `composer.json`; eso no prueba la configuración de CEO Salud y entra en tensión con el antecedente de `composer install`. Debe resolverse leyendo el build output real antes de un futuro push. Fuente: [How to Deploy a Git Repository](https://support.hostinger.com/en/articles/1583302-how-to-deploy-a-git-repository).

## 4. Recolección segura pendiente

### 4.1 En SSH productivo, por operador autorizado

Comandos que no modifican el proyecto:

```bash
pwd
php -v
php artisan --version
composer --version
git rev-parse HEAD
git branch --show-current
git status --short
date
php -r "echo date_default_timezone_get(), PHP_EOL;"
```

Condiciones:

- no ejecutar `git pull`, `composer install/update`, Artisan de cache, migrate ni queue restart;
- no imprimir `.env`;
- no usar `phpinfo()` públicamente;
- ocultar usuario/ruta de cuenta si se comparte la salida fuera del equipo.

Si la versión instalada soporta `php artisan about`, puede capturarse su salida después de revisar que no se hayan añadido service providers con efectos externos. No es requisito para continuar.

### 4.2 En base de datos, con cuenta de solo lectura

```sql
SELECT VERSION() AS version_motor,
       NOW() AS ahora_bd,
       @@global.time_zone AS timezone_global,
       @@session.time_zone AS timezone_sesion;
```

### 4.3 En hPanel

Registrar sin secretos:

- rama, URL de repositorio parcialmente redactada y ruta de instalación;
- auto-deploy activo/inactivo;
- último commit y build output;
- versión PHP seleccionada;
- cron configurado y frecuencia;
- fechas disponibles de backup de archivos y BD;
- historial de restauración;
- hooks o comandos adicionales;
- política observable de retención.

## 5. Configuración productiva a reportar

| Clave/capacidad | Formato permitido de reporte |
|---|---|
| APP_ENV | Valor no secreto (`production`, etc.) |
| APP_DEBUG | `true`/`false` |
| APP_KEY | CONFIGURADO / NO CONFIGURADO / PENDIENTE |
| DB credentials | CONFIGURADO / NO CONFIGURADO / PENDIENTE |
| RENIEC/RUC credentials | CONFIGURADO / NO CONFIGURADO / PENDIENTE |
| SMS credentials | CONFIGURADO / NO CONFIGURADO / PENDIENTE |
| SMTP credentials | CONFIGURADO / NO CONFIGURADO / PENDIENTE |
| SUNAT/certificado | CONFIGURADO / NO CONFIGURADO / PENDIENTE |
| queue/cache/session/mail driver | Nombre no secreto del driver |

Nunca adjuntar `.env`, dumps de configuración ni logs sin sanitizar.

## 6. Backups y rollback

### 6.1 Capacidad genérica del proveedor

La documentación pública de Hostinger indica que hPanel permite restaurar archivos y bases de datos desde backups automatizados, pero esto no demuestra que el plan de CEO Salud tenga fechas disponibles ni que una restauración haya sido exitosa. Fuente: [How to Restore Backups at Hostinger](https://support.hostinger.com/en/articles/4283700-how-to-restore-backups-at-hostinger).

Hostinger declara backups semanales en sus planes de hosting y diarios en determinados planes; para backups diarios señala una retención de siete días. La cobertura concreta depende del plan contratado y debe verificarse en hPanel. Fuente: [How to Activate Daily Backups](https://support.hostinger.com/en/articles/1665153-how-to-activate-daily-backups).

### 6.2 Estado de CEO Salud

| Control | Estado |
|---|---|
| Backup de archivos existe | PENDIENTE DE PANEL |
| Backup de base existe | PENDIENTE DE PANEL |
| Periodicidad | PENDIENTE DE PANEL |
| Retención | PENDIENTE DE PANEL |
| Descarga externa disponible | PENDIENTE |
| Restauración de archivos probada | NO DEMOSTRADA |
| Restauración de BD probada | NO DEMOSTRADA |
| Tiempo de restauración conocido | NO DEMOSTRADO |
| RPO/RTO aceptados por negocio | PENDIENTE DE NEGOCIO |

**BACKUP EXISTE** y **RESTAURACIÓN PROBADA** son estados distintos. No se realizará una restauración productiva durante esta fase.

## 7. Riesgos de infraestructura

1. Si auto-deploy ejecuta `composer update` en lugar de instalar el lock, el artefacto no es reproducible.
2. APP_DEBUG productivo no está verificado.
3. Laravel está bloqueado como `9.x-dev`; debe confirmarse el commit instalado y su soporte antes de actualizar.
4. No hay pipeline versionado que impida migraciones o integraciones externas accidentales.
5. Scheduler/cron/workers no están demostrados; los procesos TO-BE no deben depender de ellos hasta verificarlos.
6. No existe evidencia de restauración probada.
7. Config cache podría hacer que `.env` y configuración efectiva difieran.

## 8. Conclusión

La infraestructura productiva continúa **PENDIENTE DE VERIFICACIÓN**. La única evidencia directa de esta fase proviene del repositorio local; no existe evidencia en log o panel productivo.
