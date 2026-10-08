> **Transición de nombre:** esta candidata de trabajo 1.1.0-rc12 / 2026100804 utiliza `mod_attendancejourneys`. No es una actualización ordinaria de una instalación `mod_attendanceplus`. No desinstale el predecesor ni sustituya simplemente su carpeta. Los laboratorios existentes requieren una transición independiente, con respaldo y validación. Los respaldos RC11 originales se conservan; restáurelos en un entorno RC11 correspondiente antes de la transición y cree un nuevo respaldo. No se afirma que el nuevo componente restaure automáticamente respaldos del componente anterior.

# Instalación y actualización

La candidata de trabajo 1.1.0-rc12 (2026100804) adopta Itinerarios de asistencia y está destinada a Moodle 4.5–5.3. Conserva las funciones pedagógicas; los laboratorios existentes requieren una transición del componente validada por separado. No se ha publicado ninguna versión. Utilice las versiones PHP y de base de datos compatibles con su Moodle. Desde Moodle 5.1, la instalación manual utiliza `public/mod/attendancejourneys`; la carpeta raíz del ZIP es `attendancejourneys`.

El ZIP contiene ahora solo cadenas inglesas, de acuerdo con las directrices de Moodle. Las traducciones francesas y españolas se conservan por separado para AMOS después de la aprobación. Antes de actualizar un laboratorio que use las traducciones incluidas anteriormente, consérvelas como personalizaciones locales en Moodledata (`lang/fr_local/attendancejourneys.php` y `lang/es_local/attendancejourneys.php`). Combine las personalizaciones existentes en lugar de sobrescribirlas y purgue las cachés de idioma. Los paquetes locales tienen prioridad sobre futuras traducciones oficiales y deberán revisarse cuando estén disponibles en AMOS. El plugin funciona en inglés sin estos paquetes opcionales.

## Actividades históricas y cambio de calificación

La actualización de la base de datos no convierte las actividades existentes: conserva su modo de cálculo, las calificaciones y los resultados definitivos. Las actividades nuevas utilizan un recorrido principal obligatorio y calificaciones por recorrido, publicadas al cerrarlo explícitamente.

Una persona con permisos para editar actividades y gestionar recorridos puede abrir **Revisar el modo de calificación** en una actividad histórica, incluida la interfaz ligera. Los grupos separados también requieren acceso a todos los grupos. La revisión es de solo lectura: sesiones, registros, resultados definitivos, ausencias justificadas, calificaciones existentes y protecciones del libro de calificaciones.

La conversión directa se limita a actividades sin resultados definitivos, calificaciones numéricas (incluido cero), bloqueos/sustituciones manuales, decisiones de finalización existentes ni políticas históricas incompatibles. Conserva el elemento de calificación original, los registros y sus minutos, así como el recorrido y las asignaciones existentes. Si no hay recorrido, crea uno principal y vincula las sesiones. El umbral y el público propuestos se muestran antes de confirmar.

Las calificaciones siguen sin publicarse hasta el cierre. La finalización automática exige entonces cerrar y aprobar todos los recorridos obligatorios asignados; se sustituyen las reglas históricas basadas en los registros de asistencia. El cambio no puede deshacerse desde los ajustes. Si los datos revisados han cambiado, se rechaza la confirmación y se exige una nueva revisión.

Varios recorridos históricos, sesiones dentro y fuera de recorridos, equivalencias, resultados definitivos o ausencias justificadas anteriormente acreditadas/excluidas requieren un plan específico de conservación. La revisión explica estos límites; la actividad sigue siendo utilizable en su modo histórico. No elimine calificaciones o historiales para eludir el control.

## Antes de la instalación

1. Primero utilice un sitio de preparación con la misma versión de Moodle que producción representativo de producción.
2. Haga una copia de seguridad de la base de datos y del directorio Moodledata.
3. Confirme que el ZIP contiene directamente el directorio `attendancejourneys`.
4. Confirme que Moodle identifica el paquete como `mod_attendancejourneys`; no cambie el nombre de su componente o directorio.

## Instalación o actualización

1. Abra Administración del sitio → Complementos → Instalar complementos.
2. Sube el nuevo ZIP.
3. Confirme que Moodle identifica `mod_attendancejourneys`.
4. Continúe con la actualización de la base de datos.
5. Purgue las cachés de Moodle si una etiqueta anterior permanece visible.

## Comprobaciones mínimas posteriores a la actualización

1. Abra una actividad de Itinerarios de asistencia existente.
2. Sesiones Abiertas, itinerarios, Toma de asistencia, Informes y Administración.
3. Cree una sesión temporal y registre la asistencia.
4. Verifique el informe individual y el registro de auditoría.
5. Confirme la calificación y la finalización con una cuenta de prueba.
6. Haga una copia de seguridad y restaure la actividad en un curso de prueba.

## Revertir

Nunca cargue un ZIP antiguo en una base de datos que ya haya ejecutado una actualización más reciente. Para revertir, restaure el código, la base de datos y los datos de Moodle juntos desde la misma copia de seguridad previa a la actualización.

La versión 2026100710 añade el estado de cancelación y su historial. Las sesiones existentes siguen programadas; no se recalculan notas finales. La cancelación requiere el nuevo modo por itinerarios, reabrir cierres activos y resolver equivalencias pendientes/aprobadas. Sin datos de usuarios, la copia conserva el estado pero omite autores y motivos.


## Decisiones individuales e intentos personales explícitos

La versión 2026100711 añade períodos individuales, dispensas de toda la obligación y su historial de decisiones. Los participantes existentes mantienen la obligación predeterminada, sin exclusiones ni dispensas deducidas. La versión 2026100712 añade un vínculo explícito con el itinerario raíz y las decisiones personales de apertura de nuevos intentos. Todos los itinerarios existentes siguen siendo independientes: no se deduce un nuevo intento de nombres, números de asignación ni fechas anteriores. Se conservan la asistencia, los cierres y las calificaciones protegidas.

Un nuevo intento utiliza sesiones distintas dentro de la misma obligación lógica, con un solo elemento de calificación y el umbral original. Su apertura explícita retira la calificación final anterior y vuelve a evaluar la finalización automática; el último resultado cerrado sustituye al anterior aunque sea inferior. Se conserva el historial. La apertura, reapertura y corrección respetan los bloqueos y calificaciones sustituidas de Moodle.

Las copias con usuarios remapean identificadores de raíz e intento y decisiones personales. Las copias sin usuarios conservan los vínculos estructurales pero omiten aperturas personales e historiales de decisiones. El desplazamiento de fechas del curso mueve los períodos pedagógicos, no las fechas de auditoría. El borrado de datos y la anonimización de autores cubren los nuevos datos personales.

Después de actualizar, compruebe un intento original aprobado, abra un nuevo intento justificado y confirme la retirada de la calificación anterior. Planifique nuevas sesiones, cierre el intento y compruebe el único elemento de calificación, los estados actual/histórico, la finalización, las exportaciones y la copia/restauración. Pruebe por separado teoría y laboratorio con umbrales distintos. Esta actualización no convierte automáticamente ninguna actividad existente.
