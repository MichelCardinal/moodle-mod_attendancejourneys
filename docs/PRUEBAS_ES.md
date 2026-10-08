# Ejecutar las pruebas automatizadas

## Validación del componente renombrado RC12 — 8 de octubre de 2026

El componente `mod_attendancejourneys` supera 225 pruebas / 1 675 aserciones en cada una de siete configuraciones nativas: Moodle 4.5, 5.0, 5.1, 5.2 y 5.3 con PHP 8.3/MySQL, y Moodle 5.3 con PHP 8.4/MySQL y PHP 8.3/PostgreSQL 17. Pasan los controles oficiales phplint, savepoints, validate, phpcs y phpdoc; cinco módulos AMD se reconstruyen mediante Grunt nativo.

Las transiciones en copias completas conservan los registros de 510 tablas en 4.5 y 515 tablas en 5.3 tras normalizar las referencias técnicas previstas. Se valida la restauración al predecesor en ambas copias. El nuevo respaldo limitado al prefijo y su restauración también conservan las 510 tablas de la copia 4.5, sin eliminar una base de datos. Las colisiones y las personalizaciones lingüísticas se verifican por separado. Son procedimientos de laboratorio previos a la publicación; el ZIP no actualiza directamente otro componente.

Las 24 capturas muestran la actividad renombrada con participantes ficticios, Chrome nativo y Boost: ocho por idioma. Se revisa la presentación de las guías integradas en inglés, francés y español. Dieciséis tablas del plugin y catorce de calificaciones permanecen sin cambios tras las capturas. VoiceOver sigue aplazado. Los resultados RC11 siguientes describen la validación histórica del predecesor, sin una nueva certificación de este componente.

## Validación administrativa RC11 — 8 de octubre de 2026

La suite funcional RC11 superó **225 pruebas y 1675 aserciones** en las siete configuraciones de la tabla siguiente. Los nuevos casos cubren políticas de cálculo, guardado administrativo nativo, configuración impuesta, umbrales conservados y permisos independientes. La actualización privada RC10 a RC11 conservó datos pedagógicos y copió permisos existentes, incluidas prohibiciones locales. Ocho rutas reales rechazaron solicitudes sin el permiso requerido. Las páginas administrativas se renderizaron en inglés, francés y español. Son validaciones locales, no certificación Marketplace. La revisión visual administrativa sigue formando parte de la revisión previa a la publicación; VoiceOver está aplazado.

## Referencia validada RC9 — 8 de octubre de 2026

La suite RC9 conservada superó **216 pruebas y 1 614 aserciones** en cada configuración siguiente. RC10 cambia documentación y mensajes de itinerarios; conserva esta referencia y utiliza comprobaciones específicas independientes para esos cambios. Estos resultados no certifican todas las versiones PHP/base de datos ni la aceptación en Moodle Marketplace.

| Moodle | PHP | Base de datos |
|---|---|---|
| 4.5.13 | 8.3.30 | MySQL 8.0.44 |
| 5.0.9, 5.1.6, 5.2.2, 5.3 | 8.3.30 | MySQL 8.4.11 |
| 5.3 | 8.4.17 | MySQL 8.4.11 |
| 5.3 | 8.3.30 | PostgreSQL 17.11 |

La suite cubre cálculos, permisos, privacidad, copia/restauración, calificaciones, finalización, calendario, historial, nuevos intentos y limpieza de desinstalación. Conserve las pruebas e identifique el archivo exacto y el entorno de cada ejecución posterior.

## Entorno de desarrollo aislado

Utilice una copia de desarrollo de la rama Moodle que desea probar, con sus dependencias de desarrollo Composer. Reserve una base de datos o un prefijo de tablas independiente y un directorio de datos independiente para PHPUnit. Nunca utilice los datos ordinarios del sitio para PHPUnit. Configure PHP según los requisitos de la rama, incluido `max_input_vars=5000` como mínimo.

Ejemplo de configuración de desarrollo:

```php
$CFG->phpunit_dbname = 'attendancejourneys_phpunit';
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/ruta/separada/attendancejourneys-phpunitdata';
```

Coloque el ejecutable PHP deseado al principio de PATH. Desde la raíz del repositorio Moodle, inicialice con la orden correspondiente:

```bash
# Moodle 4.5 y 5.0.
php admin/tool/phpunit/cli/init.php

# Moodle 5.1 y posteriores.
php public/admin/tool/phpunit/cli/init.php
```

Ejecute únicamente la suite del plugin:

```bash
php -d max_input_vars=5000 vendor/bin/phpunit --testsuite mod_attendancejourneys_testsuite
```

La inicialización restablece el entorno reservado de PHPUnit. No ejecute PHPDoc Checker simultáneamente con PHPUnit en la misma copia Moodle: el plugin temporal del verificador puede interferir con el descubrimiento de componentes.

## Comprobaciones adicionales

Utilice Moodle Plugin CI para sintaxis PHP, puntos de actualización, estructura del plugin, estándares Moodle y PHPDoc; utilice Moodle Grunt para los builds AMD, ESLint y CSS. El paquete no contiene plantillas Mustache. Conserve las traducciones francesas y españolas fuera del plugin instalable para AMOS y compruebe sus claves y parámetros frente al inglés.

Verifique también instalación y actualización nativas, copia/restauración, recorridos de profesor/estudiante en el navegador, descargas, teclado y diseño adaptable. Las pruebas RC9 incluyen descargas Excel/PDF finalizadas y recorridos representativos de Chrome a 320 píxeles CSS; permanecen separadas de los controles de documentación y etiquetas RC10. Las capturas utilizan datos ficticios y no certifican todas las pantallas, temas o navegadores. VoiceOver sigue aplazado. Repita las verificaciones apropiadas cuando cambie el comportamiento o los entornos compatibles. Estas pruebas locales no conceden aceptación en Marketplace ni publican traducciones AMOS.
