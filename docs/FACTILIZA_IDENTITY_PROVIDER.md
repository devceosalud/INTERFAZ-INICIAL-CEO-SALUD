# Consulta DNI opcional

Seleccionar `RENIEC_PROVIDER=factiliza`. Configuración: `FACTILIZA_BASE_URL`
(default https://api.factiliza.com/v1) y `FACTILIZA_TOKEN` en el entorno privado.
No se modificó .env ni se realizaron consultas reales durante implementación.

Provider compartido por Agenda/Pacientes detrás de Laravel. DNI de ocho dígitos,
Bearer, sin seguir redirecciones, conexión 2 segundos y timeout total máximo 5.
Solo se devuelven nombres/apellidos para revisión humana. No persiste respuestas
ni modifica pacientes; token ausente, errores, límites o timeout permiten registro
manual. AQPFACT/APISPERU siguen seleccionables.

Contrato contrastado con documentación oficial:
https://docs.factiliza.com/api-consulta/endpoint/dni

Pruebas FactilizaProviderTest, ReniecProvidersTest y AgendaReniecLookupTest:
26 passed, todas con mocks. No se imprimen ni versionan tokens reales.

Checkpoint técnico separado de registro económico y RETIRO para evitar mezclar
un proveedor HTTP opcional con escrituras de dinero. No autoriza producción.
