# Accesibilidad

Itinerarios de asistencia se basa en los componentes y convenciones de Moodle y pretende seguir siendo totalmente utilizable con un teclado y tecnologías de asistencia.

- Los campos tienen etiquetas explícitas, incluidas etiquetas que están visualmente ocultas.
- Los grupos de estado utilizan botones de opción dentro de un `fieldset` con una leyenda.
- Las actualizaciones de cálculo y progreso se anuncian a través de `aria-live` regiones.
- Los errores de validación de minutos están vinculados al campo afectado con `aria-describedby` y `aria-invalid`.
- Las tablas anchas pueden recibir foco y desplazarse horizontalmente con un teclado.
- Se conserva un indicador de enfoque visible en enlaces, botones y campos.
- El color no es la única forma en que se comunica el estado de una interfaz: las insignias van acompañadas de texto o etiquetas.

También se debe verificar la conformidad final con el tema de Moodle utilizado en producción porque el tema puede cambiar colores, espacios y estilos de enfoque.