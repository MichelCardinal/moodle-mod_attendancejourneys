# Registro de cambios

## 1.1.0 — 2026-10-08

Primera versión pública estable basada en RC12 validada. Versión técnica 2026100806; sin cambios de esquema, cálculo, calificación o permisos. Punto de actualización nativo sin cambios pedagógicos y enlaces públicos de código y soporte. Las entradas anteriores describen desarrollo no publicado.

## 1.1.0-rc12 — 2026-10-08

Adopción de Itinerarios de asistencia / mod_attendancejourneys, versión técnica 2026100804. Se conservan los cálculos y los flujos pedagógicos. Validación del componente en siete configuraciones nativas Moodle 4.5–5.3; transiciones respaldadas y restauraciones comprobadas por separado en copias privadas completas. Actualización de 24 capturas ficticias y guías multilingües; cadenas inglesas en el plugin y traducciones preparadas aparte. Este ZIP no actualiza directamente el componente predecesor. Las entradas anteriores describen ese predecesor no publicado; publicación pendiente.

## 1.1.0-rc11 — 2026-10-08

Versión técnica: `2026100803`. Políticas principales, terminología y ajustes históricos separados; guía administrativa disponible sin actividad. Validación conjunta del cálculo y del envío de calificaciones, que impide bloquear el cálculo requerido en posición desactivada. Política opcional para umbrales propios de nuevos itinerarios, conservando umbrales y resultados existentes. Capacidades independientes para cierre, reapertura, nuevos intentos y aprobación de equivalencias, heredadas de los permisos existentes al actualizar. Ayudas y guías actualizadas en inglés, francés y español. Sin conversión de datos pedagógicos. Publicación pendiente.

## 1.1.0-rc10 — 2026-10-08

Versión técnica: `2026100802`. Guías inglesa, francesa y española actualizadas: primer itinerario completo, capturas ficticias actuales, cierre, nuevos intentos, estados de hojas, listas de espera y equivalencias. Las obligaciones independientes muestran información en lugar del aviso histórico de conflicto; los participantes seleccionados y automáticos se identifican correctamente. No cambian el esquema, los cálculos ni los resultados finales. Las traducciones AMOS y la documentación pública se preparan por separado; nada está publicado. Los enlaces entre guías respetan los permisos del rol; la ayuda se adapta a 320 píxeles CSS. Las pruebas de documentación ya no dependen de otras clases de pruebas.

## 1.1.0-rc9 — 2026-10-08

Versión técnica: `2026100801`. Las sesiones sin participantes actuales se muestran de forma neutral y se excluyen de los contadores y filtros de asistencia pendiente. Las sesiones canceladas mantienen su estado de cancelación. Se conservan las asistencias y los resultados definitivos.

## 1.1.0-rc8 (2026100800) — 2026-10-08 (en preparación; aceptación final pendiente)

- Mostrar un marcador neutro cuando falta la fecha de auditoría en el informe individual y la entrada de asistencia; conservar fechas, minutos, calificaciones e historial.
- Conservar los archivos RC6 y RC7. Este punto de versión no transforma el esquema ni los datos.

## 1.1.0-rc7 — 2026-10-07 (candidata local; aceptación final pendiente)

- Añadir períodos individuales, dispensas completas y nuevos intentos personales con vista previa, confirmación, historial y rechazo de confirmaciones obsoletas.
- Mantener umbrales independientes de teoría/laboratorio y una calificación por obligación. Un nuevo intento hereda el umbral raíz y utiliza sesiones distintas; al abrirlo se retira la nota anterior hasta el cierre y el último resultado la sustituye aunque sea inferior.
- Distinguir resultados actuales publicados de intentos históricos en informes y exportaciones, conservando asistencias y cierres anteriores.
- Cubrir decisiones personales mediante Privacy, copias/restauraciones con o sin usuarios, actualización conservadora y protecciones de calificaciones y finalización.
- Admitir equivalencias explícitas limitadas a la asistencia registrada de una obligación de origen dispensada, sin doble crédito.
- Mantener valores Excel numéricos, fechas de auditoría ausentes vacías y títulos PDF largos ajustados; actualizar documentación EN/FR/ES y separar traducciones.
- La versión 2026100713 añade un punto de versión sin cambiar el esquema ni los datos de desarrollo 2026100712. RC6 se conserva.
- Pruebas nativas de Chrome y revisión de reglas actuales de Marketplace pendientes. Sin publicación en GitHub, Marketplace ni AMOS.

## 1.1.0-dev11 — 2026-10-07 (desarrollo no publicado)

- Permitir el cierre y la reapertura individuales autorizados en actividades Light nuevas para publicar la calificación principal; conservar las restricciones históricas y la protección de operaciones avanzadas. Corregir reglas, matrices y escenarios de aceptación en los tres idiomas.
- Mantener una sola región de desplazamiento etiquetada y accesible por teclado cuando Moodle añade un contenedor adaptable; conservar las opciones originales de la tabla y admitir las API anteriores de Moodle.
- Mostrar un resultado provisional neutro en el resumen individual hasta el cierre de un itinerario en el modo nuevo; conservar el porcentaje provisional y el comportamiento de las actividades históricas.
- Añadir cancelación y restitución de sesiones con motivo, permisos/grupos y rechazo de confirmaciones obsoletas. Conservar asistencia y resultados históricos; excluir el tiempo cancelado del cálculo, registro, calendario y exportaciones.
- Conservar el estado en copias/restauraciones; incluir historial personal solo con datos de usuarios y cubrir su eliminación/anonimización mediante Moodle Privacy.
- Aplicar permisos sobre todo el público a ediciones, borrados y fuentes de operaciones por lotes; verificar grupos y participantes fijos de destino antes de añadir/trasladar sesiones.
- Exigir itinerario en el nuevo modo, conservar públicos independientes históricos, proteger destinos cerrados y aumentar las revisiones de sesiones de forma monotónica.
- Utilizar el cálculo compartido por itinerario en las exportaciones colectivas para cancelaciones y equivalencias aprobadas; conservar el modo histórico y los resultados finales fijados.
- Explicar las calificaciones independientes, la finalización de itinerarios obligatorios y la diferencia entre ausencia justificada y exención de sesión en las guías inglesa, francesa y española; identificar las capturas anteriores como ilustraciones históricas.
- Alinear las vistas previas iniciales e interactivas con el cálculo registrado: ausencia justificada, exención de sesión y políticas históricas. Traducir el cálculo y ocultar los campos y etiquetas de minutos innecesarios.
- Permitir que los textos del cálculo se ajusten en pantallas estrechas, incluida la explicación de la exención de sesión.
- Traducir los nombres de archivos exportados y títulos PDF con las cadenas existentes de Moodle.
- Exportar minutos y porcentajes Excel como celdas numéricas con la API nativa de Moodle; conservar identificadores y observaciones como texto sin interpretarlos como fórmulas.


## 1.1.0-dev10 — 2026-10-07 (desarrollo no publicado)

- Proteger la eliminación de sesiones y recorridos referenciados por equivalencias o resultados históricos; aplicar los permisos de grupos a las eliminaciones de sesiones.
- Conservar cada aprobación, rechazo y revocación de equivalencias con su autor, explicación y fecha, en la misma transacción que la decisión actual. Rechazar confirmaciones desactualizadas.
- Incluir el historial en las copias con datos de usuarios y restaurar las referencias de participantes, autores y equivalencias. Cubrir descubrimiento, exportación, borrado y anonimización mediante Moodle Privacy.
- Recuperar solamente la última decisión antigua conocida, identificada como historial parcial; las decisiones sobrescritas no pueden reconstruirse. No recalcular notas finales.
- Fuentes alfa sin empaquetar ni publicar; pendientes las verificaciones finales de compatibilidad y navegador.

## 1.1.0-dev9 — 2026-10-07 (desarrollo no publicado)

- Añadir elementos de calificación estables por recorrido, umbrales independientes y finalización de todos los recorridos obligatorios; publicar al cerrar explícitamente y reabrir un recorrido sin retirar el resultado de otro.
- Crear un recorrido principal obligatorio en las actividades nuevas; distinguir el público inscrito de las asignaciones explícitas, incluida una lista explícitamente vacía.
- Distinguir ausencia justificada y exención concedida por el personal en el nuevo modo; conservar las políticas históricas y los resultados definitivos.
- Comprobar sesiones futuras o sin registrar y aprobaciones pendientes antes del cierre; conservar la decisión exacta en el umbral pese al redondeo de calificaciones.
- Mostrar al personal los bloqueos y sustituciones manuales del libro de calificaciones y aplicar los grupos separados a la gestión de recorridos.
- Añadir una revisión de conversión histórica de solo lectura y una conversión explícita para actividades compatibles sin terminar, conservando registros y el elemento de calificación original. Mantener sin cambios los historiales incompatibles o definitivos; volver a comprobar permisos, clave de sesión y estado revisado.
- Añadir campos de esquema conservados en copia/restauración. Estas fuentes son alfa, sin paquete ni publicación; queda pendiente la auditoría final de compatibilidad y navegador.

## 1.1.0-rc6 — 2026-10-07

- Coordinar los cambios de salas con su asignación a sesiones mediante el bloqueo de actividad de Moodle. Una eliminación concurrente vuelve a comprobar el uso de la sala tras finalizar la escritura de la sesión.
- Mostrar la exportación del informe individual y el enlace al informe colectivo solo a usuarios con permiso para consultar informes. El estudiante conserva su informe y el regreso a la actividad; los permisos del servidor no cambian.
- Eliminar referencias comparativas innecesarias de las guías de instalación y actualizar los resultados del navegador. Conservar los avisos de autoría y licencia.
- Verificar la concurrencia de salas y los controles según el rol mediante los controladores reales. Comprobar en Chrome el registro del profesor con Boost y el informe del estudiante con New Learning reparado; las pruebas Excel/PDF y a 320 píxeles siguen siendo aplicables al código sin cambios.
- Sin cambios de esquema. VoiceOver sigue aplazado. La versión sigue siendo candidata; no se ha publicado un repositorio ni enviado una solicitud a Moodle.

## 1.1.0-rc5 — 2026-10-07

- Presentar los PDF como tablas de campos por registro mediante la biblioteca PDF de Moodle, con encabezados repetidos entre páginas. Conservar campos vacíos, ceros y observaciones como texto escapado.
- Cubrir los informes individual, colectivo, de itinerario, detallado por itinerario y de auditoría. Los demás formatos conservan su exportación nativa.
- Validar 85 pruebas y 537 aserciones en siete configuraciones Moodle 4.5–5.3, además de las exportaciones y denegaciones de acceso en los controladores. Mantener alineadas las 868 cadenas en inglés, francés y español.
- Sin cambios en el esquema de base de datos. Quedan pendientes la finalización de las descargas en el navegador y el recorrido completo a 320 píxeles estables. Versión candidata.


## 1.1.0-rc4 — 2026-10-07

- Permitir que los nombres y correos se ajusten dentro de las tarjetas estrechas y limitar el selector nativo de exportación a su contenedor.
- Actualizar las guías de pruebas en inglés, francés y español: matriz de 81 pruebas, preparación de PHPUnit por rama y limitaciones conocidas del navegador. Incluir Moodle 5.3 en las instrucciones francesas de instalación manual.
- Sin cambios de comportamiento PHP ni de esquema. Moodle Stylelint valida el CSS actualizado. Esta versión sigue siendo candidata.

## 1.1.0-rc3 — 2026-10-07

- Eliminar las instancias mediante la API completa de Moodle antes de desinstalar el plugin, para no dejar registros de finalización huérfanos.
- Utilizar las acciones actuales del formato de curso en Moodle 5.2+ y la API anterior compatible en las versiones previas.
- Probar la limpieza de finalización, archivos, calificaciones y contextos, la conservación de otras actividades y una segunda llamada sin instancias.
- Sin cambios de esquema. El ciclo nativo de desinstalación y reinstalación se verifica en un laboratorio aislado.

## 1.1.0-rc2 — 2026-10-07

- Traducir los detalles de asistencia en el informe individual, la auditoría administrativa y su exportación, conservando los registros originales y las notas del personal.
- Corregir la etiqueta del filtro individual para referirse a sesiones. Sincronizar las cadenas inglesas, francesas y españolas.
- Añadir pruebas de regresión para todos los estados, el historial inmutable, las observaciones multilínea, los formatos desconocidos y el escape HTML.
- Sin cambios de esquema. Versión candidata para evaluar antes de utilizarla en producción.

## 1.1.0-rc1 — 2026-10-07

- Ampliar la compatibilidad a Moodle 5.3. Validar 79 pruebas y 498 aserciones en las cinco ramas de Moodle 4.5 a 5.3, incluido Moodle 5.3 con PHP 8.4 y PostgreSQL 17.
- Aplicar las normas de código y documentación de Moodle; compilar los módulos AMD con Moodle Grunt y utilizar el diálogo de confirmación de Moodle para los cambios colectivos de asistencia.
- Incluir solo las cadenas inglesas en el plugin instalable. Conservar por separado las traducciones francesas y españolas para AMOS y las personalizaciones lingüísticas de los laboratorios.
- Exportar las acciones atribuidas al personal mediante la API Privacy; eliminar la atribución de los itinerarios al borrar todos los datos, tratar los historiales huérfanos, rechazar contextos ajenos a los módulos y coordinar el borrado con las escrituras de asistencia.
- Sin cambios de esquema. Versión candidata: las comprobaciones de instalación, actualización y tema siguen en curso.

## 1.0.4 — 2026-09-07

- Corregir la apertura normal de formularios y confirmaciones administrativas sin clave de sesión; mantener la validación de clave al enviar cambios.
- Limitar el historial individual de revisiones a la actividad actual y conservar los nombres de las sesiones al filtrar el informe.
- Verificar la actualización directa desde 1.0.0, la copia/restauración nativa entre laboratorios con usuarios, notas y finalización, y los recorridos de docente y estudiante en el navegador. Añadir regresiones de navegación y aislamiento del historial entre cursos.
- Sin cambios en el esquema de la base de datos.

## 1.0.3 — 2026-09-07

- Registro por páginas de 100 participantes, guardado y aprobación limitados a la página, coordinación con operaciones administrativas, foco de teclado reforzado y guías rápidas. Validación de Moodle 5.0/5.1 y restauración de la demostración.
- Sin cambios en el esquema.

## 1.0.2 — 2026-09-07

- Bloqueo de Moodle por actividad para envíos del profesorado y declaraciones individuales o colectivas del alumnado. Lectura actualizada bajo bloqueo para evitar conflictos entre formularios y calificaciones simultáneas.
- Clave de sesión válida y versión explícita de la hoja del profesorado.
- Menos consultas y campos cargados en el informe colectivo de grupos grandes.
- Eliminación de un punto de referencia principal anidado en la página inicial.
- Pruebas adicionales de bloqueos, exportación de datos personales y eliminación selectiva; grupos ficticios de 30, 300 y 1 000 estudiantes.
- Sin cambios en el esquema de la base de datos.

## 1.0.1 — 2026-09-07

- Ampliación de la compatibilidad declarada a Moodle 5.2, con validación en Moodle 5.2.2 y pruebas de regresión en Moodle 4.5.13.
- Conservación de espacios, selectores, insignias y etiquetas accesibles con Bootstrap 4 y 5.
- Atributos de grupo para PHPUnit 11 manteniendo las anotaciones de PHPUnit 9 en Moodle 4.5.
- 75 pruebas y 426 aserciones correctas en Moodle 4.5/PHP 8.3 y Moodle 5.2.2/PHP 8.3 y 8.4.
- Sin cambios del esquema de datos; la actualización conserva las asistencias.

## 1.0.0 — 2026-08-15

- Publicación de la primera versión estable después de la campaña completa de validación de las versiones candidatas.
- Confirmación de los modos profesional y Light, la terminología multilingüe, el centro de ayuda ilustrado y las integraciones nativas de Moodle.
- Validación de las páginas principales del plugin en Moodle 4.5 y Moodle 5.0.
- Se superaron 75 pruebas automatizadas y 426 aserciones en Moodle 4.5.

## 0.99.57-rc85 — 2026-08-15

- Se sincronizaron las presentaciones francesa, inglesa y española del paquete con las funciones auditadas y los metadatos actuales.
- Se documentaron el modo Light, la terminología profesional multilingüe, las aulas, los grupos de Moodle, las series avanzadas y el centro de ayuda ilustrado opcional.
- Se completó la auditoría técnica, lingüística, estructural y visual previa a la versión final en Moodle 4.5 y Moodle 5.0.
- Se superaron 75 pruebas automatizadas y 426 aserciones en Moodle 4.5.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.56-rc84 — 2026-08-15

- Se añadió un ajuste administrativo de Moodle que permite ocultar institucionalmente la pestaña Ayuda en todas las actividades.
- Las direcciones antiguas del centro de ayuda redirigen de forma segura a la página principal de la actividad cuando está desactivado.
- Se conservan la ayuda contextual nativa de Moodle y todos los documentos incluidos, que reaparecen inmediatamente.
- Se actualizaron las guías del administrador y la cobertura automatizada en francés, inglés y español.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.55-rc83 — 2026-08-15

- Se corrigió la interpretación del singular y del plural cuando un término francés tiene la misma forma en ambos números, especialmente `parcours`.
- Se añadieron la elisión y las contracciones francesas para términos personalizados que comienzan por vocal, generando formas como `un horaire`, `l’horaire`, `de l’horaire` y `les horaires`.
- Se añadió cobertura automatizada y se validó visualmente el resultado con terminología local temporal, eliminada después de la prueba.
- Se superaron 74 pruebas automatizadas y 423 aserciones en Moodle 4.5.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.54-rc82 — 2026-08-15

- Se completaron las guías ilustradas en francés, inglés y español con 54 capturas de demostración éticas creadas con Moodle Boost.
- Se corrigió el acceso del estudiante a su propia ficha individual, manteniendo la barrera de capacidades de Moodle que impide consultar las fichas de otros participantes.
- Se protegieron las direcciones técnicas de imágenes y enlaces frente a la sustitución de terminología profesional personalizada.
- Se auditaron visualmente todas las páginas principales en Moodle 4.5 y Moodle 5.0, sin desbordamiento horizontal, cadenas ausentes ni errores de página.
- Se superaron 73 pruebas automatizadas y 421 aserciones en Moodle 4.5.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.51-rc79 — 2026-08-15

- Se añadió una pestaña Ayuda integrada que selecciona automáticamente los documentos en francés, inglés o español.
- Los documentos administrativos, de pruebas y de actualización se restringen según las capacidades de Moodle y el estado de administrador del sitio.
- La documentación mostrada utiliza la terminología profesional personalizada de la actividad.
- Se añadieron encabezados visibles Singular y Plural sobre los campos compactos.
- Se añadió cobertura automatizada de los 24 documentos incluidos.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.50-rc78 — 2026-08-15

- Se trasladó la terminología cerca del final de los ajustes propios de la actividad y la sección queda contraída de forma predeterminada.
- Se sustituyó la lista extensa por una fila compacta singular/plural para cada concepto profesional.
- El idioma del curso aparece primero y los idiomas adicionales utilizan los campos avanzados nativos de Moodle.
- Se conservaron todos los valores multilingües, las reglas de herencia y los bloqueos institucionales independientes.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.49-rc77 — 2026-08-15

- Se amplió la terminología contextual a itinerarios, sesiones, participantes y aulas.
- Se añadieron valores singulares y plurales independientes en francés, inglés y español a nivel del sitio y de la actividad.
- Se añadió un bloqueo institucional independiente para cada concepto profesional.
- Todos los conceptos se conservan en las copias de seguridad/restauraciones de Moodle y los términos de itinerario de RC76 se mantienen al actualizar.
- Se ampliaron las pruebas automatizadas y la documentación trilingüe.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.48-rc76 — 2026-08-15

- Se añadió terminología independiente en francés, inglés y español a nivel del sitio y de la actividad.
- Se migraron los términos monolingües existentes al idioma correspondiente sin perderlos.
- Los términos multilingües se conservan al copiar, restaurar y eliminar actividades en Moodle.
- Se actualizaron las guías trilingües y las pruebas automatizadas de terminología.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.47-rc75 — 2026-08-15

- Se corrigió la resolución del contexto para utilizar la terminología guardada de la actividad en todas las páginas del complemento.
- Se amplió el tratamiento a todos los textos visibles del complemento en los tres idiomas, incluidas las etiquetas dinámicas.
- Se añadió una prueba de regresión para la detección automática de la actividad actual.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.46-rc74 — 2026-08-15

- Se añadió terminología institucional y propia de la actividad, en singular y plural, para los itinerarios.
- Se añadió un ajuste nativo de Moodle para que la administración bloquee la terminología institucional en todas las actividades.
- Los términos contextuales se aplican a espacios de trabajo, formularios, informes y exportaciones sin cambiar identificadores internos ni API.
- Los términos se conservan en copias de seguridad y restauraciones de Moodle; también se actualizaron la documentación trilingüe y las pruebas automatizadas.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.45-rc73 — 2026-08-15

- Se añadió el paquete de idioma español completo con las 809 cadenas del plugin.
- Se añadieron en español las guías de usuario y administrador, las matrices de modos y roles y las guías de actualización, aceptación, accesibilidad y pruebas.
- Se conservaron todas las variables de Moodle y se validó la paridad con los paquetes de inglés y francés.
- Esta entrega sigue siendo una versión candidata y no es la versión definitiva.

## 0.99.44-rc72 — 2026-08-15

- Se eliminaron las referencias nombradas del entorno del cliente del historial distribuido.
- Neutralidad verificada en código, lenguajes, estilos, pruebas, documentación y nombres de archivos.
- Confirmado que ninguna lógica depende de IOMAD, de un tema comercial, de un dominio o de un cliente en particular.
- Esta versión sigue siendo candidata y no es la versión final.

## 0.99.43-rc71 — 2026-08-15

- Realicé un pase de aceptación de RC70 en vivo en Moodle 4.5 en modo Light y Moodle 5.0 en modo Professional.
- Páginas principales validadas, espacios de trabajo profesionales, redirecciones ligeras y formularios sin etiquetas faltantes.
- Se corrigió el espaciado de Markdown en la descripción institucional para que los caracteres `\\n\\n` literales nunca se muestren.
- Esta versión sigue siendo candidata y no es la versión final.

## 0.99.42-rc70 — 2026-08-14

- Completé la auditoría pre-final de los modos Light y Professional, permisos, mutaciones protegidas, idiomas, ayuda, privacidad, respaldo e integraciones de Moodle.
- Se agregaron explicaciones específicas de campo para cada valor institucional en la página de administración del sitio.
- Se agregaron guías de administrador bilingües y matrices de modo Ligero/Profesional al ZIP.
- Se preservó el alcance estable: no se introdujo ninguna integración de CRM ni regla de negocio especulativa en este candidato.

## 0.99.41-rc69 — 2026-08-14

- Se movió cada explicación de bloqueo institucional directamente debajo de la configuración que rige en el formulario de actividad de Moodle.
- Se preservó la aplicación verificada del lado del servidor y se restauró la configuración del sitio de prueba después de la validación.

## 0.99.40-rc68 — 2026-08-14

- Se agregaron configuraciones de administración del sitio nativas de Moodle para valores predeterminados institucionales.
- Se agregaron bloqueos opcionales del lado del servidor para el modo de experiencia, cálculos, umbral, libro de calificaciones, ausencias justificadas, autorregistro de los estudiantes y publicación del calendario.
- Se reemplazó toda la eliminación directa de eventos del calendario en el código del complemento activo y las actualizaciones heredadas con la API del calendario de Moodle.
- Se migraron los comportamientos de JavaScript en línea restantes a módulos AMD reutilizables.
- Se agregó documentación bilingüe y cobertura automatizada para políticas institucionales y limpieza de calendario.

## 0.99.30-rc58 — 2026-08-14

- Equivalencias aprobadas integradas con la calculadora central de asistencia, informes, finalización y libro de calificaciones.
- Mantuvo la duración esperada de la sesión en el mayor tiempo posible mientras se transferían los minutos actuales reales con un límite de esa duración.
- Se priorizó la asistencia normal, se evitó el doble conteo fuente-sesión y se identificó el crédito de equivalencia en el informe individual.
- Resultados cerrados protegidos y cobertura unitaria agregada para diferentes duraciones y crédito limitado.

## 0.99.29-rc57 — 2026-08-14

- Se agregaron decisiones de aprobación, rechazo y revocación de equivalencias en el informe individual de participante.
- Se registró una nota de decisión, miembro del personal y marca de tiempo para un historial institucional auditable.
- Se revalidó la elegibilidad en el momento de la aprobación y se evitó cerrar un resultado mientras una solicitud permanece pendiente.
- Las equivalencias aprobadas permanecen neutrales desde el punto de vista del cálculo hasta el siguiente paso de integración.

## 0.99.28-rc56 — 2026-08-14

- Se agregó la creación de solicitudes de equivalencia al informe de participante individual.
- Las sesiones de reemplazo deben pertenecer a otro itinerario y ya contener la asistencia del participante.
- Requirió una justificación, evitó solicitudes activas duplicadas y mostró el estado de la solicitud en el registro del participante.
- Las solicitudes permanecen neutrales desde el punto de vista del cálculo hasta el paso de aprobación.

## 0.99.27-rc55 — 2026-08-14

- Se agregó la base de datos para equivalencias de sesiones entre itinerarios.
- El modelo distingue sesión esperada, sesión de reemplazo asistida, participante, justificación y decisión auditable.
- Los resultados de asistencia no se ven afectados hasta que se complete el flujo de trabajo de aprobación en un paso posterior.

## 0.99.26-rc54 — 2026-08-14

- Se agregó un historial administrativo visible de promociones y eliminaciones en lista de espera.
- Se muestra el participante, la decisión, la fecha de la decisión y el miembro del personal responsable.
- Operación bilingüe preservada y los datos de auditoría existentes respaldados por Moodle.

## 0.99.25-rc53 — 2026-08-14

- Se agregó una lista de espera ordenada opcional para cada itinerario de asistencia.
- Se agregaron acciones deliberadas de promoción y eliminación controladas por el personal con un historial de estado auditable.
- Advertido ante una promoción intencionada más allá de la capacidad, manteniendo la flexibilidad operativa autorizada.
- Datos de lista de espera integrados con copia de seguridad, restauración, privacidad, reinicio de cursos y ciclos de vida de reinicio de participantes de Moodle.
- Validé 56 pruebas y 317 aserciones en Moodle 4.5.13.

## 0.99.24-rc52 — 2026-08-14

- Se agregó capacidad de itinerario opcional independientemente de la capacidad de el aula.
- Se agregaron indicadores de lugar disponible, capacidad total y capacidad excedida a la gestión de itinerarios y vistas detalladas.
- Se requiere una confirmación autorizada explícita al crear, reducir o agregar un participante más allá de su capacidad, sin imponer un bloqueo rígido.
- Capacidad de itinerario preservada en la copia de seguridad y restauración de Moodle.
- Validé 56 pruebas y 311 aserciones en Moodle 4.5.13.

## 0.99.23-rc51 — 2026-08-14

- Se agregó un espacio de trabajo de grupo de curso nativo de Moodle dentro de Itinerarios de asistencia.
- Los usuarios autorizados pueden crear grupos, editar su identidad y administrar la membresía del curso inscrito sin duplicar los datos del grupo.
- Los grupos quedan inmediatamente disponibles para itinerarios y sesiones, mientras que las operaciones avanzadas de eliminación y agrupación permanecen en la administración de Moodle.
- Se aplicó el permiso nativo `moodle/course:managegroups` de Moodle y se verificó la separación entre profesores que editan y no editan.
- Validé 55 pruebas y 307 aserciones en Moodle 4.5.13.

## 0.99.22-rc50 — 2026-08-14

- Se agregaron aulas locales de actividades reutilizables con código, capacidad, direcciones, notas y ciclo de vida activo/inactivo.
- Integré aulas guardadas en sesiones presenciales e híbridas conservando las ubicaciones manuales.
- Instantáneas de ubicación legibles conservadas y asignaciones de aulas en la copia de seguridad y restauración de Moodle.
- Se evitó la eliminación de aulas ya utilizadas por las sesiones.
- Validé 55 pruebas y 304 aserciones en Moodle 4.5.13.

## 0.99.21-rc49 — 2026-08-14

- Se agregó selección de audiencia directamente al flujo de trabajo de duplicación masiva de sesiones.
- Las copias pueden conservar cada asignación de origen o asignarse juntas a todos los participantes, un grupo de Moodle o un itinerario activo.
- Se amplió la vista previa para mostrar fechas, audiencias y itinerarios originales y propuestos antes de la creación.
- Advertencias de superposición recalculadas contra la audiencia propuesta y objetivos revalidados al momento de la confirmación.
- Validé 55 pruebas y 299 aserciones en Moodle 4.5.13.

## 0.99.20-rc48 — 2026-08-14

- Se agregó duplicación masiva de sesiones seleccionadas con un día calendario o un desplazamiento de minutos.
- Se agregó vista previa antes/después, detección de superposición detallada y confirmación explícita de conflictos intencionales.
- Las copias conservan la programación y la información de la audiencia, pero nunca copian los registros de asistencia ni los resultados finales.
- Ámbitos excluidos protegidos por resultados finales activos y protección de concurrencia optimista agregada.
- Validé 55 pruebas y 296 aserciones en Moodle 4.5.13.

## 0.99.19-rc47 — 2026-08-14

- Se agregó cambio de nombre masivo en vista previa para sesiones existentes, incluido un nombre compartido o numeración cronológica estable.
- Se agregó reemplazo masivo o eliminación de descripciones de sesiones sin alterar audiencias, cálculos de asistencia o resultados finales.
- Protección de concurrencia optimista extendida a nombres y descripciones de sesiones.
- Se agregaron etiquetas bilingües nativas de Moodle y orientación para los nuevos cambios administrativos.
- Validé 54 pruebas y 291 aserciones en Moodle 4.5.13.

## 0.99.18-rc46 — 2026-08-14

- Se agregó una vista previa de la asignación masiva de sesiones vacías a todos los participantes, grupos de Moodle o itinerarios activos.
- Apliqué automáticamente el grupo configurado de cada itinerario y detecté conflictos para la audiencia propuesta.
- Se excluye toda sesión que contenga asistencia o contribuya a un resultado final.
- Se agregó protección de concurrencia, actualizaciones de calendario y recálculo después de asignaciones confirmadas.
- Validé 53 pruebas y 288 aserciones en Moodle 4.5.13.

## 0.99.17-rc45 — 2026-08-14

- Se agregaron cambios masivos en vista previa para la duración, modalidad, ubicación y enlaces de clases virtuales.
- Se agregaron opciones de modalidad nativas de Moodle y soporte para borrar ubicaciones obsoletas o valores de enlace de reunión.
- Duraciones protegidas que contribuyen a resultados finales activos y al mismo tiempo permiten correcciones seguras de la información de entrega.
- Se volvieron a verificar los conflictos después de cambios de duración y se mantuvo la confirmación explícita y la protección de concurrencia.
- Validé 52 pruebas y 283 aserciones en Moodle 4.5.13.

## 0.99.16-rc44 — 2026-08-14

- Se agregaron movimientos de programación masiva para sesiones existentes seleccionadas del catálogo de sesiones.
- Se agregaron turnos de minutos y días calendario con una vista previa explícita de antes/después.
- Se agregó detección de conflictos entre la misma audiencia y reconocimiento explícito de superposiciones intencionales.
- Sesiones excluidas protegidas por resultados finales activos y protección de concurrencia optimista agregada.
- Eventos del calendario de Moodle actualizados, finalización y calificaciones después de cambios confirmados.
- Validé 51 pruebas y 279 aserciones en Moodle 4.5.13.

## 0.99.15-rc43 — 2026-08-14

- Auditó el ciclo de vida completo de eliminación segura de itinerarios y sus sesiones.
- Se agregó prueba automatizada de que al eliminar un itinerario sin registros se eliminan los eventos del calendario de Moodle.
- Confirmado que las sesiones independientes no relacionadas y sus eventos de calendario permanecen intactos.
- Se mantuvo la protección existente contra la eliminación de itinerarios que contengan asistencia o resultados finales.
- Validé 49 pruebas y 274 aserciones en Moodle 4.5.13.

## 0.99.14-rc42 — 2026-08-14

- Completé una revisión visual en vivo de un borrador de ocho sesiones en un diseño de curso Moodle con bloques de barra lateral.
- Confirmadas las tarjetas de serie larga, el resumen y el informe detallado de superposición en el tema instalado.
- Se cambiaron los controles de selección y clasificación a acciones secundarias de Moodle para que la creación y la aplicación sigan siendo visualmente primarias.
- Creó solo un borrador privado temporal durante la validación; no se guardó ninguna sesión de prueba.

## 0.99.13-rc41 — 2026-08-14

- Se reelaboraron las vistas previas de series de sesiones largas en tarjetas de sucesos claramente separadas.
- Proporcionó a los controles de edición masiva un panel de planificación distinto y mejor ajuste de acciones.
- Controles de fecha, campos y botones mejorados en columnas estrechas de Moodle y en pantallas móviles.
- Mantuve sin cambios el flujo de trabajo del formulario Moodle establecido y todos los datos de la serie.

## 0.99.12-rc40 — 2026-08-14

- Se agregó clasificación cronológica estable después de cambios de programación manuales y masivos.
- Se agregó un tablero de series con indicadores incluidos, excluidos, duración, período, modalidad y conflicto.
- Totales calculados solo de las reuniones incluidas y retenidos todos los metadatos de ocurrencia durante la clasificación.
- Revisión de series largas mejorada sin cambiar el flujo de trabajo del formulario estándar de Moodle.
- Se amplió la suite validada a 49 pruebas y 270 aserciones.

## 0.99.11-rc39 — 2026-08-14

- Se agregaron movimientos masivos de días calendario para cualquier subconjunto seleccionado de reuniones propuestas.
- Se agregaron turnos de minutos positivos o negativos preservando la duración de las reuniones.
- Horas locales preservadas durante las transiciones de horario de verano para movimientos diurnos.
- Advertencias de superposición detalladas recalculadas después de cada cambio de programación.
- Se amplió la suite validada a 48 pruebas y 263 aserciones.

## 0.99.10-rc38 — 2026-08-14

- Se reemplazó el recuento de superposiciones genérico con un informe de conflicto detallado y procesable.
- Se muestran los nombres de las sesiones, los tiempos completos, la duración de la superposición, la audiencia y la fuente del conflicto.
- Se agregó un enlace directo para revisar una sesión conflictiva existente en una pestaña separada sin perder el borrador.
- Mantuvo superposiciones intencionales disponibles mediante reconocimiento explícito.
- Validé 47 pruebas y 257 aserciones en Moodle 4.5.13.

## 0.99.9-rc37 — 2026-08-14

- Se agregó la selección con un solo clic de todas las reuniones incluidas y la eliminación de la selección masiva.
- Se conserva cada edición de vista previa mientras se aplican los comandos de selección.
- Se detectaron superposiciones dentro de una serie propuesta y con sesiones existentes para la misma audiencia.
- Se requiere reconocimiento explícito antes de que se puedan crear reuniones superpuestas intencionales.
- Se amplió la suite validada a 47 pruebas y 252 aserciones.

## 0.99.8-rc36 — 2026-08-14

- Se agregó modo de entrega por ocurrencia, ubicación, enlace en línea y descripción a la vista previa de la serie.
- Se agregaron cambios en el modo de entrega masiva, la ubicación y el enlace en línea para reuniones seleccionadas.
- Validó cada ocurrencia incluida de forma independiente antes de que comience la transacción.
- Se conserva la segmentación por audiencia, grupo y itinerario común para evitar cambios accidentales en las asignaciones.
- Validé 45 pruebas y 248 aserciones en Moodle 4.5.13.

## 0.99.7-rc35 — 2026-08-14

- Se agregó numeración automática opcional cuando se genera una serie de sesiones.
- Se agregaron controles de selección independientes para cambios de vista previa masiva sin alterar las opciones de inclusión.
- Se agregaron nombres secuenciales masivos, nombres compartidos y cambios de duración para cualquier subconjunto seleccionado.
- Horarios de reuniones locales preservados durante las transiciones de horario de verano.
- Se amplió la suite validada a 45 pruebas y 245 aserciones.

## 0.99.6-rc34 — 2026-08-14

- Se agregó un paso de revisión nativo de Moodle para las series de sesiones generadas preservando al mismo tiempo la creación inmediata.
- Permitió que cada reunión propuesta tuviera su propio nombre, fecha y hora o fuera excluida antes de guardarla.
- Se mantuvieron las vistas previas privadas para el usuario y la actividad actuales, con vencimiento automático de dos horas.
- Revalidé audiencias y itinerarios activos en la confirmación y creé las reuniones seleccionadas de forma transaccional.
- Se amplió la suite validada a 42 pruebas y 230 aserciones.

## 0.99.5-rc33 — 2026-08-13

- Se corrigieron las exportaciones colectivas para que las declaraciones pendientes de los estudiantes nunca contribuyan a los totales oficiales de asistencia.
- Cálculos de informes oficiales verificados, filtros de auditoría combinados y los seis formatos de exportación de Moodle con contenido con acento francés.
- Se agregó cobertura de validación para fechas de sesiones y itinerarios, grupos, modos de entrega y enlaces en línea.
- Comprobaciones completas de lenguaje bilingüe, XMLDB, permisos, punto final de mutación e interfaz receptiva.
- Se amplió la suite validada a 40 pruebas y 225 aserciones.

## 0.99.4-rc32 — 2026-08-13

- Compensación centralizada de asistencia a sesiones en un asistente seguro para transacciones.
- Verificó que la compensación mantiene la sesión, elimina el historial de asistencia y revisión y avanza su versión de concurrencia.
- Verificado que los resultados finales activos impiden la limpieza hasta que se reabran.
- Se agregaron pruebas que distinguen las ediciones de contenido inofensivas de los cambios estructurales y de audiencia.
- Se amplió la suite validada a 32 pruebas y 187 aserciones.

## 0.99.3-rc31 — 2026-08-13

- Se agregó validación de propiedad defensiva antes de eliminar cualquier itinerario.
- Eliminación segura verificada de itinerarios sin registros y sus sesiones conservando sesiones no relacionadas.
- Verificado que los itinerarios que contienen asistencia no se pueden eliminar.
- Se verificó que el reinicio del participante elimina solo los registros de Itinerarios de asistencia específicos, el historial de revisión, las tareas y los resultados finales.
- Verificó que las cuentas de Moodle, las inscripciones y los registros de otros participantes permanezcan intactos.
- Se amplió la suite validada a 29 pruebas y 167 aserciones.

## 0.99.2-rc30 — 2026-08-13

- Se agregaron pruebas de integración para sesiones comunes y orientación a grupos de Moodle.
- Se verificó que una audiencia de itinerario congelada excluye a los participantes que no fueron asignados.
- Verificado que las inscripciones suspendidas desaparecen de las nuevas hojas de asistencia sin borrar el historial.
- Verificado que la reinscripción en un nuevo itinerario recibe un nuevo número de intento y nunca reactiva la autorregistro para el intento anterior.
- Se amplió la suite validada a 25 pruebas y 143 aserciones.

## 0.99.1-rc29 — 2026-08-13

- Centralizó la regla de bloqueo de edición de la declaración del estudiante utilizada por las páginas de entrada individuales y masivas.
- Verificado que las declaraciones pendientes y las correcciones solicitadas sigan siendo editables por su autor.
- Verificado que las declaraciones aprobadas y la asistencia ingresada por el personal permanecen bloqueadas para los estudiantes.
- Se agregaron pruebas de autoridad de aprobación, historial de revisión inmutable y instantáneas de auditoría.
- Se amplió la suite validada a 21 pruebas y 126 aserciones.

## 0.99.0-rc28 — 2026-08-13

- Se agregaron pruebas de integración del calendario de Moodle para configuraciones habilitadas y deshabilitadas.
- Verificó que las ediciones de la sesión actualicen un evento existente sin crear duplicados.
- Se verificó que al deshabilitar la integración del calendario se eliminan solo los eventos de Itinerarios de asistencia.
- Se verificó que la limpieza de eventos obsoletos nunca elimina las entradas del calendario de otro módulo.
- Se amplió la suite validada a 17 pruebas y 107 aserciones.

## 0.98.0-rc27 — 2026-08-13

- Se agregaron pruebas de integración del libro de calificaciones que demuestran que solo los resultados cerrados publican las calificaciones finales.
- Verificado que al reabrir un resultado se borra la calificación final conservando el historial.
- Se agregaron pruebas para reglas de finalización basadas en registros, de todas las sesiones, de resultados cerrados y de umbral de aprobación.
- Se amplió la suite validada a 14 pruebas y 94 aserciones.

## 0.97.0-rc26 — 2026-08-13

- Se agregaron pruebas de integración de restauración y respaldo de cursos reales de Moodle.
- Restauración completa verificada de sesiones, itinerarios, registros de asistencia, historial de revisión, tareas y resultados finales.
- Se verificó que una restauración solo de estructura excluye los datos del usuario y vuelve a abrir los itinerarios copiados de manera coherente.
- Se amplió la suite validada a 12 pruebas y 83 aserciones.

## 0.96.0-rc25 — 2026-08-13

- Se agregó un generador de datos de prueba estándar de Moodle para Itinerarios de asistencia.
- Se agregaron pruebas automatizadas de capacidad de rol predeterminado.
- Se agregaron pruebas de descubrimiento de privacidad que cubren a participantes, asistentes, aprobadores, revisores y administradores de itinerario.
- Se agregó una prueba de ciclo de vida de cierre y reapertura de resultados de itinerario.
- Se amplió la suite validada a 10 pruebas y 67 aserciones.

## 0.95.0-rc24 — 2026-08-13

- Se agregó un entorno Moodle PHPUnit aislado y reutilizable para Itinerarios de asistencia.
- Se corrigieron los valores predeterminados de cadenas vacías no válidas detectados por Moodle XMLDB durante la instalación de prueba.
- Se agregó la ruta de actualización que elimina esos valores predeterminados de las instalaciones existentes.

## 0.94.0-rc23 — 2026-08-13

- Copia de seguridad auditada, restauración, eliminación de actividades, restablecimiento del curso y manejo del ciclo de vida de la privacidad.
- Se completó el descubrimiento de usuarios de privacidad para revisores y administradores de resultados del itinerario.
- Se agregó una prueba de base de datos que garantiza que la eliminación de actividades elimine todos los registros operativos dependientes.

## 0.93.0-rc22 — 2026-08-13

- Audité la matriz de capacidades predeterminada de Moodle para estudiantes, profesores que no editan, profesores que editan, gerentes y administradores del sitio.
- Se agregó documentación bilingüe sobre permisos de roles y accesibilidad.
- Errores de validación de minutos vinculados a sus campos para tecnologías de asistencia.
- Se agregaron anuncios en vivo para actualizaciones de asistencia calculada.
- Enfoque de teclado visible estandarizado en todos los controles del complemento.

## 0.92.0-rc21 — 2026-08-13

- Completé la documentación operativa en francés e inglés.
- Se agregaron guías de prueba de aceptación, actualización y usuario en inglés.
- Terminología armonizada entre la interfaz y la documentación.

## 0.91.0-rc20 — 2026-08-13

- Se completó la ayuda contextual nativa de Moodle en todas las configuraciones, finalización, sesiones y itinerarios.
- Se agregaron explicaciones concisas en francés e inglés para los cálculos, las ausencias justificadas y la finalización.
- Se agregaron ventanas emergentes de ayuda accesibles para fechas, ubicaciones, enlaces en línea, recurrencia y asignaciones de itinerarios.

## 0.90.0-rc19 — 2026-08-13

- Se agregó el conjunto de documentación listo para producción.
- Se agregaron instrucciones de instalación, actualización y reversión.
- Se agregó una guía operativa francesa y un protocolo de aceptación institucional.
- Capacidades documentadas, privacidad, copias de seguridad y limitaciones de liberación.

## 0.89.0-rc18 — 2026-08-13

- Completé la campaña de estabilización de Moodle 4.5.
- Se corrigió la carga de reinicio del curso de ayudantes de historial de auditoría inmutables.
- Copia de seguridad y restauración nativa validada, incluido el historial de auditoría.
- Validé seis formatos de exportación de datos de Moodle.

## 0.88.0-rc17 — 2026-08-13

- Se agregó un registro de auditoría institucional central de solo lectura.
- Se agregaron filtros por participante, sesión, grupo, acción y período.
- Se agregaron exportaciones de registros de auditoría.
- Se agregaron acciones inmutables creadas y actualizadas por asistencia.

## 0.87.0-rc16 — 2026-08-13

- Se agregó un historial inmutable de aprobación, solicitud de corrección y reenvío.
- Se agregó historial a informes individuales, exportaciones de privacidad y copias de seguridad de Moodle.

## Candidatos de lanzamiento anterior

Las iteraciones anteriores introdujeron itinerarios e intentos, finalización manual de resultados, grupos de Moodle, manejo de inscripciones inactivas, separación de roles, autorregistro y aprobación de los estudiantes, integración del libro de calificaciones y finalización, informes, exportaciones, control de calendario, modos de entrega, herramientas de administración y la interfaz profesional alineada con Moodle.
