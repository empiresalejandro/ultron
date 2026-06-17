=== Ultron ===
Contributors: alejandro.network
Tags: monitor, admin, database, wordpress, maintenance
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hub de gestión y monitoreo para WordPress. Módulos de monitoreo de instalación, base de datos, almacenamiento y plugins.

== Description ==

Ultron es un plugin de gestión centralizada para instalaciones de WordPress. Proporciona un hub con módulos activables individualmente:

**WordPress Monitor** — Snapshot completo de la instalación: versión de WordPress, servidor, configuración (wp-config.php), seguridad, archivos expuestos, endpoints activos y usuarios.

**Database Monitor** — Lista de tablas de la base de datos con tamaño, filas y estado de salud. Incluye truncate seguro con doble confirmación para tablas no-core.

**Storage Monitor** — Inodos y espacio usado de la instalación completa y de la carpeta uploads, desglosado por año y mes. Vigilancia de archivos error.log.

**Plugins Monitor** — Detección de plugins recomendados con estado de instalación/activación, alertas de conflicto entre categorías, gestión de licencias premium y acciones rápidas.

== Installation ==

1. Sube la carpeta `ultron` al directorio `/wp-content/plugins/`.
2. Activa el plugin desde el menú "Plugins" de WordPress.
3. Ve a **Ultron → Módulos** para activar los módulos que necesites.

== Changelog ==

= 1.2.0 =
* Sistema de actualizaciones definido: el core se actualiza manualmente; los módulos se actualizan desde GitHub vía modules.json.
* Los módulos core ahora tienen repositorio propio y son actualizables individualmente, aunque siguen viniendo preinstalados.
* Botón "Actualizar" en la pestaña Módulos para cualquier módulo con nueva versión disponible.

= 1.1.0 =
* Opciones ahora es un submenú independiente (antes pestaña del hub).
* Nueva pestaña Información en el hub: banner de versión, modo de uso y novedades.
* Eliminada la configuración de Ultron Master del core (se moverá a un módulo de telemetría).

= 1.0.0 =
* Versión inicial.
* Hub con pestañas Dashboard, Módulos y Opciones.
* Módulo WordPress Monitor.
* Módulo Database Monitor con truncate seguro.
* Módulo Storage Monitor con desglose por año/mes.
* Módulo Plugins Monitor con detección de conflictos y licencias.

== Frequently Asked Questions ==

= ¿Los módulos se pueden activar/desactivar individualmente? =

Sí. Desde la pestaña Módulos del hub puedes activar o desactivar cada módulo de forma independiente.

= ¿Dónde se guardan los datos de los monitores? =

WordPress Monitor y Storage Monitor guardan snapshots históricos en tablas propias de la base de datos (`{prefix}ultron_wp_monitor` y `{prefix}ultron_st_monitor`). Database Monitor y Plugins Monitor consultan en tiempo real sin persistencia.

= ¿Qué pasa con los datos al desinstalar? =

Por defecto los datos se conservan. Si activas la opción "Eliminar todos los datos" en Opciones antes de desinstalar, se eliminarán tablas y opciones de la base de datos.
