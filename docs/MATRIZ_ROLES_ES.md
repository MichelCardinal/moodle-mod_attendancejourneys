# Matriz de roles y permisos

Esta matriz describe los permisos predeterminados en Itinerarios de asistencia. Los administradores de Moodle pueden adaptar estas capacidades en roles personalizados.

| Acción | Estudiante | Profesora no editora | Profesor de edición | Gerente | Administrador del sitio |
|---|---:|---:|---:|---:|---:|
| Ver la actividad y su propia situación | Sí | Sí | Sí | Sí | Sí |
| Registrar su propia asistencia cuando esté habilitado | Sí | No | No | No | Sí, por capacidad |
| Tomar o aprobar asistencia | No | Sí | Sí | Sí | Sí |
| Ver informes de participantes autorizados | No | Sí | Sí | Sí | Sí |
| Crear y gestionar sesiones | No | No | Sí | Sí | Sí |
| Crear y gestionar itinerarios | No | No | Sí | Sí | Sí |
| Cerrar y publicar resultados definitivos | No | No | Sí | Sí | Sí |
| Reabrir resultados definitivos | No | No | Sí | Sí | Sí |
| Abrir un nuevo intento personal | No | No | Sí | Sí | Sí |
| Aprobar, rechazar o revocar equivalencias | No | No | Sí | Sí | Sí |
| Cambiar la configuración de la actividad | No | No | Sí | Sí | Sí |
| Restablecer los datos de un participante | No | No | No | No | Sí por defecto |

## Capacidades de Moodle

- `mod/attendancejourneys:view`: ver la actividad.
- `mod/attendancejourneys:canbelisted`: pertenece a la audiencia estudiantil elegible.
- `mod/attendancejourneys:selfrecord`: registrar la propia asistencia.
- `mod/attendancejourneys:takeattendance`: tomar y aprobar asistencia.
- `mod/attendancejourneys:viewreports`: ver informes permitidos por el contexto y las reglas del grupo.
- `mod/attendancejourneys:managesessions`: gestionar sesiones.
- `mod/attendancejourneys:managejourneys`: gestionar itinerarios; las decisiones específicas también requieren su capacidad independiente.
- `mod/attendancejourneys:closejourneys`: cerrar itinerarios y publicar resultados definitivos.
- `mod/attendancejourneys:reopenjourneys`: reabrir resultados definitivos.
- `mod/attendancejourneys:manageattempts`: abrir un nuevo intento personal.
- `mod/attendancejourneys:approveequivalences`: aprobar, rechazar o revocar equivalencias.
- `mod/attendancejourneys:manage`: cambia la configuración específica de la actividad.
- `mod/attendancejourneys:resetuserdata`: utiliza funciones de administración destructivas.

Se respetan las restricciones de grupos separados y la capacidad `moodle/site:accessallgroups` de Moodle. A un estudiante nunca se le concede acceso al informe individual de otro participante.
