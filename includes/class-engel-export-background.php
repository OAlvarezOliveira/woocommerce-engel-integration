<?php
if (!defined('ABSPATH')) exit;

class Engel_Export_Background {
    use Engel_Connection_Trait;

    private $filename;
    private $language;
    private $elements_per_page;
    private $max_pages;
    private $file_path;

    public function __construct($elements_per_page = null, $max_pages = null, $language = 'es') {
        $upload_dir = wp_upload_dir();

        $timestamp = date('Ymd_His');
        $this->filename = "engel_products_{$timestamp}.csv";
        $this->file_path = $upload_dir['basedir'] . '/' . $this->filename;

        // Usa opciones guardadas o valores por defecto si no se pasan
        $this->elements_per_page = $elements_per_page ?? (int) get_option('engel_elements_per_page', 50);
        $this->max_pages = $max_pages ?? (int) get_option('engel_max_pages', 100);
        $this->language = $language;
    }

    public function get_file_url() {
        return wp_upload_dir()['baseurl'] . '/' . $this->filename;
    }

    public function start_export() {
        $handle = fopen($this->file_path, 'w');
        if (!$handle) {
            throw new Exception('No se pudo crear el archivo CSV');
        }

        $headers = ['ID', 'SKU', 'Nombre', 'Descripción corta', 'Descripción larga', 'Precio', 'Stock', 'Marca', 'EAN', 'Categoría', 'IVA', 'Peso (kg)'];
        fputcsv($handle, $headers);
        fclose($handle);

        update_option('engel_export_page', 0);
        update_option('engel_export_in_progress', true);
        update_option('engel_export_filename', $this->filename);

        $this->log("Exportación CSV iniciada: {$this->filename}");
    }

    public function process_page($page) {
        $filename = get_option('engel_export_filename');
        if (!$filename) {
            throw new Exception('No hay exportación iniciada');
        }

        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $handle = fopen($file_path, 'a');
        if (!$handle) {
            throw new Exception('No se pudo abrir el archivo CSV');
        }

        $token = $this->get_token();
        if (!$token) {
            fclose($handle);
            throw new Exception('Token no disponible');
        }

        $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$this->elements_per_page}/{$this->language}";

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => "Bearer $token",
                'Accept' => 'application/json',
            ],
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            fclose($handle);
            $this->log("Error al obtener productos página $page: " . $response->get_error_message(), 'error');
            throw new Exception("Error al obtener productos página $page: " . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            fclose($handle);
            $this->log("Respuesta inválida en página $page", 'error');
            throw new Exception("Respuesta inválida en página $page");
        }

        foreach ($data as $p) {
            $row = [
                $p['Id'] ?? '',
                $p['ItemId'] ?? '',
                $p['Description'] ?? '',
                $p['CompleteDescription'] ?? '',
                $p['Price'] ?? '',
                $p['Stock'] ?? '',
                $p['BrandName'] ?? '',
                $p['EANs'][0] ?? '',
                $p['Families'][0] ?? '',
                $p['IVA'] ?? '',
                $p['Kgs'] ?? '',
            ];
            fputcsv($handle, $row);
        }

        fclose($handle);

        $this->log("Página $page exportada con " . count($data) . " productos.");

        // Si hay menos productos que el límite o max páginas alcanzado, terminamos
        if (count($data) < $this->elements_per_page || $page >= $this->max_pages - 1) {
            update_option('engel_export_in_progress', false);
            $this->log("Exportación CSV completada: {$filename}", 'info');
            return false; // No hay más páginas
        }

        update_option('engel_export_page', $page + 1);
        return true; // Hay más páginas
    }

    private function log(string $message, string $type = 'info') {
        if (function_exists('engel_log')) {
            engel_log($message, $type);
        } else {
            error_log("[Engel Export][$type] $message");
        }
    }
}

