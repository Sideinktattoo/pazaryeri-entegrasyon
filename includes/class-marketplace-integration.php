<?php
if (!defined('ABSPATH')) {
    exit;
}

class PY_KZ_Marketplace_Integration {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Pazar yeri entegrasyonları için hook'lar
    }

    public function render_marketplace_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'trendyol';
        
        include PY_KZ_PLUGIN_DIR . 'templates/admin/marketplace-settings.php';
    }

    public function export_to_marketplace($products, $marketplace) {
        switch ($marketplace) {
            case 'trendyol':
                return $this->export_to_trendyol($products);
            case 'hepsiburada':
                return $this->export_to_hepsiburada($products);
            default:
                return new WP_Error('invalid_marketplace', __('Geçersiz pazar yeri.', 'pazar-yeri-kar-zarar'));
        }
    }

    private function export_to_trendyol($products) {
        // Trendyol API entegrasyonu
        $api_url = 'https://api.trendyol.com/sapigw/suppliers/{supplierId}/products';
        $api_key = get_option('py_kz_trendyol_api_key');
        $api_secret = get_option('py_kz_trendyol_api_secret');
        $supplier_id = get_option('py_kz_trendyol_supplier_id');
        
        if (empty($api_key) || empty($api_secret) || empty($supplier_id)) {
            return new WP_Error('missing_credentials', __('Trendyol API bilgileri eksik.', 'pazar-yeri-kar-zarar'));
        }
        
        $payload = array();
        
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }
            
            $payload[] = array(
                'barcode' => $product->get_sku(),
                'title' => $product->get_name(),
                'productMainId' => $product->get_sku(),
                'brandId' => $this->get_trendyol_brand_id($product),
                'categoryId' => $this->get_trendyol_category_id($product),
                'quantity' => $product->get_stock_quantity(),
                'stockCode' => $product->get_sku(),
                'dimensionalWeight' => $this->calculate_dimensional_weight($product),
                'description' => $product->get_description(),
                'currencyType' => 'TRY',
                'listPrice' => $product->get_regular_price(),
                'salePrice' => $product->get_sale_price() ? $product->get_sale_price() : $product->get_regular_price(),
                'vatRate' => 18,
                'cargoCompanyId' => 1,
                'images' => $this->get_product_images($product),
                'attributes' => $this->get_product_attributes($product),
            );
        }
        
        // API isteği gönder
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret),
            ),
            'body' => json_encode($payload),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['errors'])) {
            return new WP_Error('api_error', __('Trendyol API hatası: ', 'pazar-yeri-kar-zarar') . implode(', ', $body['errors']));
        }
        
        return true;
    }

    // Diğer yardımcı metodlar...
}
