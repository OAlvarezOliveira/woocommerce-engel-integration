# Engel Sync Plugin for WordPress

Este plugin permite sincronizar productos desde una API externa (Engel) directamente con tu tienda WordPress/WooCommerce.

## Características

- Guardado de configuración de API URL y token.
- Sincronización completa de productos desde la API.
- Actualización de stock de productos de forma independiente.
- Registro básico de sincronizaciones en la base de datos (`wp_engel_sync_log`).

## Requisitos

- WordPress 5.0 o superior
- WooCommerce instalado y activo
- PHP 7.4 o superior
- Acceso a una API compatible (Engel) con endpoints de productos y stock

## Instalación

1. Clona o descarga este repositorio en el directorio `wp-content/plugins/engel-sync`:

   ```bash
   git clone https://github.com/tu-usuario/engel-sync.git wp-content/plugins/engel-sync

 