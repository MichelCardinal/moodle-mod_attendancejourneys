> **Transición de nombre:** esta candidata de trabajo 1.1.0-rc12 / 2026100804 utiliza `mod_attendancejourneys`. No es una actualización ordinaria de una instalación `mod_attendanceplus`. No desinstale el predecesor ni sustituya simplemente su carpeta. Los laboratorios existentes requieren una transición independiente, con respaldo y validación. Los respaldos RC11 originales se conservan; restáurelos en un entorno RC11 correspondiente antes de la transición y cree un nuevo respaldo. No se afirma que el nuevo componente restaure automáticamente respaldos del componente anterior.

# Instalar y probar Itinerarios de asistencia

Estas instrucciones acompañan `1.1.0-rc10` (`2026100802`) para Moodle 4.5 a 5.3. Utilice el archivo correspondiente y valide primero en un laboratorio. Nada está publicado.

## Instalación

Utilice Administración del sitio → Plugins → Instalar plugins. Suba el ZIP AttendanceJourneys correspondiente y siga la validación y actualización de Moodle. El componente debe ser `mod_attendancejourneys`.

Para instalar manualmente, coloque `attendancejourneys` en `mod/` con Moodle 4.5/5.0 o `public/mod/` con Moodle 5.1 a 5.3. Abra las notificaciones de administración. No necesita Composer para instalar este plugin.

Antes de actualizar un sitio utilizado, respalde su base de datos, Moodledata y código. Pruebe primero en una copia. Volver a una versión anterior exige un respaldo coherente; sustituir solo el directorio del plugin por un ZIP antiguo no basta.

## Primera prueba

1. Cree un curso de prueba con un profesor y tres estudiantes ficticios.
2. Añada Itinerarios de asistencia. El modo ligero comienza con un itinerario principal; el profesional admite obligaciones separadas y operaciones avanzadas.
3. Cree una sesión pasada o en curso de 60 minutos.
4. Como profesor, registre presencia completa, ausencia y asistencia parcial con 15 minutos ausentes. Guarde y revise el informe.
5. Para probar la declaración propia, habilítela, cree una sesión elegible e inicie sesión como estudiante ficticio. La declaración espera aprobación docente.

La presencia completa representa todos los minutos, la ausencia ninguno y la parcial resta minutos ausentes. El porcentaje permanece provisional sin calificación final hasta el cierre individual. Primero deben terminar las sesiones exigidas y resolverse los registros y aprobaciones pendientes.

Los estudiantes no deben consultar informes ajenos ni acciones administrativas. Revise los permisos de sus roles personalizados.

## Ejemplo ilustrado y configuración

Siga la [guía del usuario](GUIA_USUARIO_ES.md) para teoría al 60%, laboratorio al 80% y cierre explícito. Una demostración anterior conserva su política histórica; restaurarla no convierte automáticamente la calificación a obligaciones independientes.

- Guarde antes de cambiar de página de 100 participantes.
- Ajuste calificaciones y finalización a la política pedagógica.
- Calendario, calificaciones y finalización siguen las opciones habilitadas.
- Revise las vistas previas y confirmaciones antes de reiniciar o borrar.
- Utilice las herramientas de privacidad Moodle y la política institucional de conservación.

El plugin funciona sin servicio externo. Valide los temas y extensiones adicionales de su instalación. Las personalizaciones locales francés/español y futuras traducciones AMOS son independientes del ZIP instalable en inglés.
