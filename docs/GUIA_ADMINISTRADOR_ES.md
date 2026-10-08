# Guía del administrador: Itinerarios de asistencia

## Calificación nueva e histórica

Las actividades nuevas crean un itinerario principal obligatorio y utilizan calificaciones independientes por itinerario. La calificación se publica tras el cierre explícito; la finalización automática exige cerrar y aprobar todos los itinerarios obligatorios asignados. Una ausencia justificada sigue siendo ausencia; una exención de sesión autorizada elimina esa obligación. El modo profesional permite itinerarios simultáneos distintos con umbrales propios. El modo ligero conserva el itinerario principal.

Una actualización no convierte actividades existentes ni recalcula sus resultados históricos. Utilice la revisión del modo de calificación y la [guía de actualización](GUIA_ACTUALIZACION_ES.md) para la conversión explícita y conservadora. Las antiguas condiciones de finalización con un único registro y políticas de tiempo justificado no describen actividades nuevas.

La [guía del usuario](GUIA_USUARIO_ES.md) describe las reglas actuales por itinerario con sus ilustraciones.

## Obligaciones individuales de itinerario

En el nuevo modo de calificación por itinerario, abra **Informes → participante → Obligación individual de itinerario**. El personal autorizado para editar la actividad puede eximir la obligación completa o definir un periodo individual explícito. Toda decisión, incluida la reincorporación, necesita un motivo. Primero debe reabrirse un resultado final; los bloqueos y modificaciones manuales del libro de Moodle siguen protegidos.

Deje ambas fechas desactivadas para exigir todas las sesiones aplicables. El inicio es inclusivo y el fin exclusivo. Una sesión completamente fuera del periodo se excluye; una sesión que lo solapa cuenta entera. Las fechas técnicas de matriculación y asignación nunca determinan estos límites. Por ejemplo, excluir una ausencia anterior de 100 minutos y conservar una sesión de 100 minutos con 20 minutos ausentes cambia el cálculo provisional de 80/200 a 80/100. La asistencia registrada permanece en el historial.

Seleccione **Vista previa de la decisión** para comparar minutos y sesiones antes y después, con exclusiones y reincorporaciones. **Editar** vuelve a la propuesta sin guardarla. **Confirmar esta decisión** conserva el motivo y el historial; no cierra el itinerario ni publica una calificación. Si cambian la asistencia, las obligaciones o los permisos desde la vista previa, obtenga una nueva. **Cancelar** conserva la obligación actual.

La exención completa ignora las fechas, retira esta obligación de la finalización automática requerida y no crea ninguna calificación ni aprobación artificial. Los demás itinerarios obligatorios todavía deben cerrarse y aprobarse. Si todas las obligaciones están exentas, la finalización automática sigue siendo falsa; una persona autorizada puede utilizar la decisión manual nativa de Moodle. Los resúmenes identifican la exención; los informes detallados conservan los minutos históricos y señalan las sesiones fuera de la obligación individual. Las equivalencias existentes pueden impedir retirar una sesión vinculada a una decisión pendiente o aprobada.

El participante puede leer su historial de decisiones. Los demás participantes no pueden consultarlo. Una copia con usuarios incluye las decisiones y su historial; una copia de estructura los omite. Al restaurar con un desplazamiento de las fechas del curso, las sesiones y límites pedagógicos se desplazan juntos, mientras que las fechas de auditoría de decisiones y asistencia conservan sus valores históricos.


## Terminología institucional y local del curso

La administración del sitio puede definir términos singulares y plurales por separado en francés, inglés y español para cuatro conceptos profesionales: **itinerario, sesión, participante y aula**. Cada concepto tiene su propio bloqueo institucional. Un campo vacío conserva la etiqueta estándar traducida de ese idioma. Sin bloqueo, el profesorado puede personalizar cada idioma en los ajustes de la actividad; un valor local vacío hereda el ajuste del sitio para ese idioma. Solo cambia la visualización: los identificadores, permisos, copias de seguridad e integraciones permanecen estables.

## Páginas de administración del sitio

La página principal ofrece enlaces a **Terminología**, **Ajustes históricos** y la **Guía del administrador**. La guía se consulta directamente desde la administración del sitio, sin crear una actividad, incluso cuando la ayuda de las actividades está desactivada. Requiere la capacidad de configuración del sitio de Moodle.

Los valores predeterminados se aplican a las actividades nuevas. Un valor bloqueado se impone al guardar de nuevo una actividad; cambiar un valor predeterminado o un bloqueo no actualiza todas las actividades ni recalcula resultados definitivos. La terminología cambia inmediatamente la visualización. Las antiguas políticas de tiempo justificado solo afectan al cálculo histórico; la calificación por itinerario conserva la distinción entre ausencia justificada y exención autorizada.

Los valores predeterminados del cálculo porcentual y del libro de calificaciones se guardan como un grupo validado: no se puede activar el envío de calificaciones predeterminado con el cálculo porcentual desactivado. Moodle registra normalmente los cambios de configuración. Este ajuste no publica calificaciones provisionales. No se puede bloquear el cálculo en posición desactivada, porque la nueva calificación por itinerario lo requiere.

**Permitir umbrales propios de los itinerarios** está activado por defecto. Desactivarlo impide crear o modificar un umbral propio en un itinerario ordinario, incluida la eliminación de un umbral existente. Se conservan los umbrales registrados, los resultados definitivos y el umbral heredado de un nuevo intento personal. Los itinerarios nuevos heredan el umbral de la actividad. Reactivar la política permite cambios sujetos a las protecciones habituales de los resultados definitivos. Bloquear el umbral de la actividad no impide por sí solo los umbrales propios de itinerarios.

## Delegación de decisiones pedagógicas

Los roles de Moodle pueden autorizar por separado el cierre, la reapertura, la apertura de nuevos intentos personales y la aprobación o revocación de equivalencias. Cada decisión exige también la capacidad existente de gestión de itinerarios; siguen aplicándose las restricciones del curso, de grupos y de acceso a la actividad. Proponer una equivalencia sigue siendo distinto de aprobarla.

Al actualizar, Moodle copia a cada capacidad nueva los permisos existentes de gestión de itinerarios, incluidas las prohibiciones locales. El personal con permiso de edición conserva sus derechos hasta que el administrador adapte explícitamente el rol. Compruebe ambas capacidades al delegar una decisión; ocultar un botón no constituye por sí solo el control de acceso.

## Configuración institucional

La página **Administración del sitio → Plugins → Módulos de actividad → Itinerarios de asistencia** utiliza la API de configuración nativa de Moodle. Define los valores propuestos para las nuevas actividades: modo Light o Profesional, cálculo del porcentaje, umbral, libro de calificaciones, estado Justificado, registro de la propia asistencia por el estudiante e integración con el calendario.

El ajuste institucional **Mostrar el centro de ayuda integrado** oculta la pestaña Ayuda en todas las actividades. Una dirección existente del centro de ayuda redirige entonces a la página principal de la actividad con un aviso claro. Los documentos permanecen incluidos en el complemento y reaparecen sin pérdida de datos al reactivar el ajuste. La ayuda contextual nativa de Moodle en los formularios permanece siempre disponible.

Cada valor dispone de un bloqueo opcional. Sin bloqueo, un profesor con permiso de edición puede adaptar el valor de una actividad. Con bloqueo, el valor institucional aparece como de solo lectura y también se impone en el servidor al guardar. Una actividad existente adopta el valor bloqueado la próxima vez que se edita.

## Elegir un modo

- **Light**: sesiones sencillas, registro de asistencia, registro opcional por el estudiante e informes esenciales. Las funciones avanzadas quedan ocultas y sus URL están protegidas en el servidor.
- **Profesional**: añade itinerarios simultáneos, aforos y listas de espera, equivalencias, aulas, grupos Moodle, operaciones masivas, administración excepcional y auditoría. Las actividades Light nuevas también permiten el cierre final individual del itinerario principal.

El modo no crea dos ediciones del plugin ni duplica los datos. Únicamente adapta la experiencia disponible en cada actividad.

El formulario de actividad organiza los ajustes en las secciones Moodle **Experiencia de usuario**, **Cálculo de asistencia**, **Permisos de registro**, **Calendario de Moodle** y **Terminología**.

## Permisos recomendados

- Estudiante: ver la actividad y su propia información; registrar su propia asistencia solo cuando tanto la opción como la capacidad estén habilitadas.
- Profesor sin permiso de edición: registra la asistencia y consulta los informes autorizados.
- Profesor con permiso de edición o gestor: también administra sesiones, itinerarios y recursos operativos.
- Administrador del sitio: reinicio excepcional de datos y auditoría institucional.

Estos comportamientos utilizan las capacidades de Moodle y se pueden adaptar en **Definir roles** o mediante anulaciones de cursos/actividades. Otorgar `mod/attendancejourneys:resetuserdata` únicamente a personas autorizadas para eliminar datos institucionales.

## Datos e integraciones de Moodle

- Las calificaciones utilizan la API del libro de calificaciones.
- La finalización utiliza la API de finalización personalizada.
- La integración con el calendario es opcional y está desactivada de forma predeterminada; los eventos utilizan la API de calendario.
- Los grupos creados en Itinerarios de asistencia son grupos reales de cursos de Moodle.
- Los informes utilizan los formatos de datos instalados en Moodle.
- Se admiten copias de seguridad/restauración, reinicio del curso y API de privacidad.

## Operaciones y mantenimiento

La pestaña **Administración** permite buscar o filtrar participantes e iniciar un reinicio excepcional. El aviso indica expresamente que solo se eliminan los datos de Itinerarios de asistencia; las cuentas, inscripciones, grupos, otras calificaciones y registros del sistema Moodle permanecen intactos.

El **Registro de auditoría** es independiente de los datos modificables y conserva la trazabilidad institucional de las operaciones sensibles.

Pruebe cada actualización en un sitio de preproducción y haga una copia de seguridad de la base de datos y de Moodledata antes de instalarla en producción. Después de actualizar, purgue las cachés, abra una actividad Light y otra Profesional y ejecute la prueba de aceptación incluida en el ZIP.

Los resultados provisionales no deben generar certificados. Utilice el cierre para publicar un resultado final. La reapertura debe ser una decisión autorizada y debe ser rastreable.

## Dos itinerarios obligatorios: ejemplo práctico

| | Asistencia | Umbral | Estado | Libro de calificaciones |
|---|---:|---:|---|---|
| Teoría | 70 % | 60 % | Cerrado, aprobado | 70 % |
| Laboratorio | 70 % | 80 % | Abierto, provisional | Sin calificación |

La finalización permanece incompleta mientras Laboratorio esté abierto. Si se cierra al 70 %, se publica su calificación, pero no alcanza el umbral del 80 %: la finalización sigue incompleta. Reabrir Laboratorio retira únicamente su calificación final. Tras una corrección autorizada y el cierre al 80 %, ambos itinerarios obligatorios están aprobados. Moodle controla la agregación de calificaciones del curso; aprobar Teoría no compensa un Laboratorio obligatorio no aprobado.


## Nuevos intentos de la misma obligación

En el informe individual del participante, seleccione **Abrir un nuevo intento** después de cerrar el intento actual. El personal autorizado indica un nombre y una justificación, revisa la vista previa de las consecuencias y confirma o cancela. Una calificación bloqueada o sustituida manualmente en Moodle impide la apertura. Primero debe restablecerse una obligación dispensada.

La confirmación abre un intento personal independiente con el umbral de la obligación original. Planifique nuevas sesiones: no se copian sesiones ni registros de asistencia. Se retira la calificación final anterior y se vuelve a evaluar la finalización automática hasta cerrar el nuevo intento. El último resultado cerrado sustituye al anterior, aunque sea inferior; un resultado anterior más alto nunca se usa como alternativa. Reabrir el intento actual vuelve a retirar su resultado. Corregir un intento anterior conserva su historial sin cambiar cuál es el intento actual.

Una obligación conserva un solo elemento de calificación y un solo requisito de finalización para todos sus intentos. Las obligaciones independientes de teoría y laboratorio mantienen umbrales, calificaciones y requisitos propios. El número de asignación dentro de la actividad no es el número personal de intento de una obligación.

El informe individual de una obligación única abre directamente su intento actual. Con varias obligaciones, elija el intento actual o un enlace al historial. La asistencia y los cierres anteriores siguen disponibles. Los informes colectivos y las exportaciones Excel/PDF de un itinerario físico conservan sus datos; **Resultado final anterior** o **Intento anterior** significa que este resultado no determina la calificación actual. Las exportaciones por sesión incluyen **Estado del resultado del intento**, separado del estado de asistencia como Presente.
