# PDF.js para archivos privados de la cita

Versión fijada: 6.4.299, distribución oficial pdfjs-dist de Mozilla. Solo módulo, worker y licencia. Los archivos upstream .mjs se conservan sin cambios de bytes como .js para servidores que no reconocen el MIME de .mjs. Se cargan como módulos ES; módulo y worker deben actualizarse juntos.

PROVENANCE.json registra fuente, integridad SHA-512 del tarball y SHA-256 de los archivos. No se ejecutaron scripts de instalación. No hay CDN, envío de documentos a terceros, evaluaciones ni formularios/XFA. AppointmentPatientDocuments limita tamaño, imagen, canvas y ciclo de vida del worker; dibuja sin capa de anotaciones ejecutables.

Para actualizar: revisar avisos oficiales, seleccionar versión concreta, verificar integridad npm y licencia, reemplazar ambos archivos y generar nuevos hashes. Ejecutar JavaScript y los recorridos Chrome de PDF privado, permisos, cancelación y archivos inválidos. No actualizar automáticamente durante despliegue.

Fuentes: https://mozilla.github.io/pdf.js/ y https://github.com/mozilla/pdf.js/security/advisories.
