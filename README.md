# Plugin WooCommerce Engel Integration

Este plugin desarrollado para **WooCommerce** (WordPress) permite la integración con la API del proveedor **Engel**, automatizando tanto la importación de productos como el envío y seguimiento de pedidos.

## 🚀 Funcionalidades principales

- 🔄 **Importación automática de productos** mediante un fichero CSV descargado desde la API de Engel.
- ⏱️ **Actualización periódica** programada con `cron` (por ejemplo, cada X horas o días).
- 🗃️ **Inserción en la base de datos** solo de productos nuevos o actualización de productos existentes.
- 📦 **Envío automático de pedidos** a Engel directamente desde WooCommerce.
- 🧭 **Seguimiento del estado de los pedidos** mediante la misma API.

## ⚙️ Requisitos

- Sitio web con WordPress + WooCommerce instalado.
- Acceso a la API del proveedor Engel (token/API key).
- Acceso al sistema para configurar tareas `cron`.

## 🛠️ Instalación

1. Clona o descarga este repositorio en la carpeta `/wp-content/plugins/` de tu instalación de WordPress.
2. Activa el plugin desde el panel de administración de WordPress.
3. Configura las credenciales de la API y los intervalos de actualización en los ajustes del plugin.
4. Asegúrate de tener configurado un `cronjob` para ejecutar las tareas periódicas (o usa WP-Cron si no tienes acceso al servidor).

## 🔧 Configuración del cron (ejemplo)

Puedes usar `wget`, `curl` o `wp-cron` para ejecutar el endpoint del plugin:

```bash
0 */6 * * * wget -q -O - https://tuweb.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1

📬 Contacto y soporte

Si tienes dudas o sugerencias, puedes abrir un issue en este repositorio.
