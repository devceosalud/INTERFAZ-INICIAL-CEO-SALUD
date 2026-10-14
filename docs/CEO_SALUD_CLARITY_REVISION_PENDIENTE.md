# Clarity — decisión y revisión pendiente

14/10/2026. Decisión del usuario: conservar instalado, sin PR para limitar, retirar, sustituir o modificar integración. No autoriza uso con datos sensibles. No se cambia resources/views/layouts/app.blade.php ni producción.

Evidencia primaria: [términos oficiales Microsoft Clarity](https://clarity.microsoft.com/terms), leídos en su página renderizada el 14/10. La sección 1.b.ii restringe uso con contenido sensible, incluidos salud y servicios financieros; 4.4.c.ii impide comunicar datos sensibles conscientemente. El tag está en el layout común del ERP. Es un riesgo pendiente del responsable de privacidad y negocio antes de pacientes/comprobantes reales, no resuelto por mantener la instalación.

[FAQ oficial](https://learn.microsoft.com/en-us/clarity/faq): el enmascarado de parámetros tiene límites para referrers y enlaces pulsados. [Masking oficial](https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-masking): cambios de masking no son retroactivos. El enmascarado por sí solo no demuestra admisibilidad contractual.

Pendiente: alcance autorizado de captura, contenidos/rutas/URLs, grabaciones, contactos, datos médicos y bancarios, acceso al proyecto, retención y evidencia de evaluación por el responsable. No se usa información real en QA; ningún comprobante real se envía a OCR externo. El desarrollo P0 continúa. No declarar publicación lista para operar con pacientes reales mientras esta revisión siga abierta.
