<?php
class PYE_Importer {
    private static $instance;

    public static function instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_pye_import_products', [$this, 'ajax_import']);
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'pazar-yeri-entegrasyon') === false) return;
        
        wp_enqueue_script(
            'pye-import',
            PYE_PLUGIN_URL . 'assets/js/import.js',
            ['jquery'],
            PYE_VERSION
        );
        
        wp_localize_script('pye-import', 'pye_import', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pye-import-nonce')
        ]);
    }

    public function render_import_page() {
        include PYE_PLUGIN_DIR . 'templates/admin/import.php';
    }

    public function ajax_import() {
        check_ajax_referer('pye-import-nonce', 'nonce');
        
        $xml_url = esc_url_raw($_POST['xml_url']);
        $margin = floatval($_POST['margin']);
        $supplier_id = intval($_POST['supplier_id']);
        
        $result = $this->import_from_xml($xml_url, $margin, $supplier_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    public function import_from_xml($url, $margin, $supplier_id) {
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $xml = simplexml_load_string(wp_remote_retrieve_body($response));
        
        if (!$xml) {
            return new WP_Error('invalid_xml', __('Invalid XML format', 'pazar-yeri-entegrasyon'));
        }
        
        $stats = ['imported' => 0, 'updated' => 0];
        
        foreach ($xml->product as $product) {
            $product_id = $this->process_product($product, $margin, $supplier_id);
            if ($product_id) $stats['imported']++;
        }
        
        return $stats;
    }

    private function process_product($data, $margin, $supplier_id) {
        $sku = (string)$data->sku;
        $price = (float)$data->price * (1 + ($margin / 100));
        
        $product = new WC_Product();
        $product->set_name((string)$data->name);
        $product->set_sku($sku);
        $product->set_regular_price($price);
        $product->set_stock_quantity((int)$data->stock);
        
        update_post_meta($product->save(), '_pye_cost_price', (float)$data->price);
        update_post_meta($product->save(), '_pye_supplier_id', $supplier_id);
        
        return $product->get_id();
    }
}
