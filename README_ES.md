> **Transición de nombre:** esta versión 1.1.0 / 2026100806 utiliza `mod_attendancejourneys`. No es una actualización ordinaria de una instalación `mod_attendanceplus`. No desinstale el predecesor ni sustituya simplemente su carpeta. Los laboratorios existentes requieren una transición independiente, con respaldo y validación. Los respaldos RC11 originales se conservan; restáurelos en un entorno RC11 correspondiente antes de la transición y cree un nuevo respaldo. No se afirma que el nuevo componente restaure automáticamente respaldos del componente anterior.

# Itinerarios de asistencia / Attendance Journeys / Parcours d’assiduité

Itinerarios de asistencia es un módulo de actividad de Moodle para la gestión profesional de la asistencia. Admite sesiones individuales, series de sesiones, itinerarios formativos, grupos de Moodle, asistencia parcial expresada como minutos de ausencia, umbrales configurables, resultados finales, integración con el libro de calificaciones e historial de auditoría institucional.

## Versión

- Versión: `1.1.0`
- Número técnico: `2026100806`
- Compatibilidad prevista: Moodle 4.5 a 5.3
- Madurez: estable

Esta versión adopta Itinerarios de asistencia y el componente independiente `mod_attendancejourneys`. Conserva los cálculos, las políticas institucionales y los permisos pedagógicos del predecesor. Sus archivos históricos permanecen intactos. Las instalaciones anteriores requieren la transición independiente, respaldada y validada descrita arriba. La matriz local de siete configuraciones Moodle 4.5–5.3 y las transiciones en copias completas han pasado. Código y seguimiento de incidencias: https://github.com/MichelCardinal/moodle-mod_attendancejourneys. Las contribuciones AMOS siguen a la aprobación del componente.

El plugin instalable contiene solo cadenas inglesas. Las traducciones francesas y españolas se conservan por separado para AMOS y las personalizaciones locales del laboratorio. La documentación ilustrada sigue siendo multilingüe.

Las actividades nuevas incluyen un itinerario principal obligatorio. El modo Light permite su cierre y reapertura individuales; el modo profesional permite además varios itinerarios simultáneos y su gestión avanzada. La finalización automática exige cerrar y aprobar todos los itinerarios obligatorios asignados. Una ausencia justificada conserva el tiempo exigido; una exención de sesión autorizada por el personal lo excluye del cálculo.

Asignar a un participante a otro itinerario no transfiere automáticamente su asistencia ni elimina la obligación original. El personal autorizado puede revisar y confirmar períodos individuales, dispensas de toda la obligación y nuevos intentos personales. Un nuevo intento utiliza sesiones distintas dentro de la obligación original, hereda su umbral y conserva un solo elemento de calificación. Al abrirlo se retira la calificación final anterior hasta el cierre; el último resultado cerrado sustituye al anterior aunque sea inferior. Se conserva el historial de asistencia.

## Funciones principales

- Sesiones cuya duración se calcula a partir de las fechas y horas de inicio y fin.
- Sesiones de itinerario para participantes inscritos, grupos de Moodle o participantes asignados explícitamente; las sesiones comunes y de grupo independientes se conservan en las actividades históricas.
- Series con vista previa, ajustes por sesión y avisos de superposición.
- Gestión de aulas dentro del curso y gestión nativa de grupos de Moodle.
- Modalidades presencial, en línea, híbrida o no especificada.
- Integración opcional con el calendario de Moodle, desactivada de forma predeterminada.
- Estados Presente, Ausente, Parcial, Justificado, Exento por decisión del personal y No registrado.
- Asistencia parcial introducida como minutos de ausencia.
- Porcentaje de asistencia opcional y umbral de aprobación configurable.
- Tratamiento histórico configurable de las ausencias justificadas, conservado en las actividades existentes.
- Registro de la propia asistencia por el estudiante, con aprobación del personal y proceso de corrección.
- Informes individuales y colectivos de asistencia.
- Cierre manual del resultado de un participante o de un itinerario.
- Integración con el libro de calificaciones y la finalización de actividad de Moodle.
- Exportación en CSV, Excel, JSON, ODS, HTML y PDF.
- Registro institucional de auditoría de solo lectura.
- Compatibilidad con las API de privacidad, copia de seguridad, restauración y reinicio de curso de Moodle.
- Valores institucionales predeterminados nativos de Moodle con bloqueos opcionales aplicados en el servidor.
- Un modo Light simplificado junto al modo profesional completo.
- Terminología profesional multilingüe configurable por la institución o la actividad, con bloqueos opcionales.
- Un centro de ayuda ilustrado integrado en francés, inglés y español, que la administración puede ocultar sin desactivar la ayuda contextual de Moodle.

## Instalación y actualización

Instale el ZIP mediante **Administración del sitio → Plugins → Instalar plugins**, o coloque el directorio `attendancejourneys` en `mod/attendancejourneys`.

En Moodle 5.1 y posteriores, la instalación manual se hace en `public/mod/attendancejourneys`. El ZIP conserva un único directorio raíz `attendancejourneys`.

El componente interno debe seguir siendo `mod_attendancejourneys` y el directorio debe conservar el nombre `attendancejourneys`. La carga de un ZIP más reciente actualiza la instalación existente de Itinerarios de asistencia. No importa ni sustituye otro módulo de actividad.

Antes de actualizar un sitio en producción, haga una copia de seguridad de la base de datos y de Moodledata. Pruebe primero cada versión en un sitio de preproducción.

## Permisos

- Estudiante: consulta la actividad y sus propios datos; puede registrar su propia asistencia únicamente cuando esta función está habilitada.
- Profesor sin permiso de edición: registra la asistencia y consulta los informes autorizados.
- Profesor con permiso de edición: también administra las sesiones y los itinerarios.
- Gestor: posee de forma predeterminada las mismas capacidades operativas que un profesor con permiso de edición.
- Administrador del sitio: también accede al reinicio excepcional de datos de participantes y al registro institucional de auditoría.

Las capacidades se pueden adaptar mediante las anulaciones de roles de Moodle.

## Documentación

- Español: `docs/GUIA_USUARIO_ES.md`, `docs/GUIA_ADMINISTRADOR_ES.md`, `docs/MATRIZ_MODOS_ES.md`, `docs/GUIA_ACTUALIZACION_ES.md`, `docs/PRUEBA_ACEPTACION_ES.md`, `docs/MATRIZ_ROLES_ES.md`, `docs/ACCESIBILIDAD_ES.md` y `docs/PRUEBAS_ES.md`.
- Français : `docs/GUIDE_UTILISATEUR_FR.md`, `docs/GUIDE_ADMINISTRATEUR_FR.md`, `docs/MATRICE_MODES_FR.md`, `docs/MISE_A_NIVEAU_FR.md`, `docs/RECETTE_FONCTIONNELLE_FR.md`, `docs/MATRICE_ROLES_FR.md`, `docs/ACCESSIBILITE_FR.md` et `docs/TESTS_FR.md`.
- English: `docs/USER_GUIDE_EN.md`, `docs/ADMINISTRATOR_GUIDE_EN.md`, `docs/MODE_MATRIX_EN.md`, `docs/UPGRADE_GUIDE_EN.md`, `docs/ACCEPTANCE_TEST_EN.md`, `docs/ROLE_MATRIX_EN.md`, `docs/ACCESSIBILITY_EN.md` and `docs/TESTING_EN.md`.
- `CHANGELOG.md`, `CHANGELOG_FR.md` y `CHANGELOG_ES.md`: historial de versiones en los tres idiomas.

## Licencia

GNU General Public License v3 o posterior.

## Documentation and support

- [English illustrated guide](docs/USER_GUIDE_EN.md)
- [Guide illustré français](docs/GUIDE_UTILISATEUR_FR.md)
- [Guía ilustrada en español](docs/GUIA_USUARIO_ES.md)
- [Bug reports and feature requests](https://github.com/MichelCardinal/moodle-mod_attendancejourneys/issues)
- [Private vulnerability reporting](https://github.com/MichelCardinal/moodle-mod_attendancejourneys/security/advisories/new)
