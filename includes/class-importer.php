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
<?php
if (!defined('ABSPATH')) {
    exit;
}

class PY_KZ_Importer {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_py_kz_import_products', array($this, 'ajax_import_products'));
        add_action('py_kz_hourly_import', array($this, 'process_scheduled_imports'));
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'pazar-yeri-import',
            PY_KZ_PLUGIN_URL . 'assets/js/import.js',
            array('jquery'),
            PY_KZ_VERSION,
            true
        );
        
        wp_localize_script('pazar-yeri-import', 'py_kz_import_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'import_nonce' => wp_create_nonce('py_kz_import_nonce'),
            'importing_text' => __('Ürünler içe aktarılıyor...', 'pazar-yeri-kar-zarar'),
        ));
    }

    public function render_import_page() {
        include PY_KZ_PLUGIN_DIR . 'templates/admin/import-settings.php';
    }

    public function ajax_import_products() {
        check_ajax_referer('py_kz_import_nonce', 'security');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz yok.', 'pazar-yeri-kar-zarar'));
        }
        
        $xml_url = isset($_POST['xml_url']) ? esc_url_raw($_POST['xml_url']) : '';
        $profit_margin = isset($_POST['profit_margin']) ? floatval($_POST['profit_margin']) : 0;
        $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
        
        if (empty($xml_url)) {
            wp_send_json_error(__('XML URL boş olamaz.', 'pazar-yeri-kar-zarar'));
        }
        
        $result = $this->import_from_xml($xml_url, $profit_margin, $supplier_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => sprintf(__('%d ürün başarıyla içe aktarıldı.', 'pazar-yeri-kar-zarar'), $result['imported']),
                'stats' => $result
            ));
        }
    }

    public function import_from_xml($xml_url, $profit_margin = 0, $supplier_id = 0) {
        $response = wp_remote_get($xml_url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $xml_content = wp_remote_retrieve_body($response);
        
        if (empty($xml_content)) {
            return new WP_Error('empty_xml', __('XML içeriği boş.', 'pazar-yeri-kar-zarar'));
        }
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array();
            
            foreach ($errors as $error) {
                $error_messages[] = $error->message;
            }
            
            libxml_clear_errors();
            return new WP_Error('invalid_xml', __('Geçersiz XML formatı: ', 'pazar-yeri-kar-zarar') . implode(', ', $error_messages));
        }
        
        $stats = array(
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0
        );
        
        foreach ($xml->product as $product) {
            $result = $this->import_product($product, $profit_margin, $supplier_id);
            
            if ($result === 'imported') {
                $stats['imported']++;
            } elseif ($result === 'updated') {
                $stats['updated']++;
            } elseif ($result === 'skipped') {
                $stats['skipped']++;
            } else {
                $stats['failed']++;
            }
        }
        
        return $stats;
    }

    private function import_product($product_data, $profit_margin, $supplier_id) {
        // Ürün SKU'sunu al
        $sku = (string)$product_data->sku;
        
        if (empty($sku)) {
            return 'skipped';
        }
        
        // Ürünün zaten var olup olmadığını kontrol et
        $product_id = wc_get_product_id_by_sku($sku);
        
        // Ürün fiyatını hesapla (kar marjını ekle)
        $cost_price = (float)$product_data->price;
        $selling_price = $cost_price * (1 + ($profit_margin / 100));
        
        // Ürün verilerini hazırla
        $product_args = array(
            'post_title'   => (string)$product_data->name,
            'post_content' => (string)$product_data->description,
            'post_status'  => 'publish',
            'post_type'    => 'product',
        );
        
        // Ürün özellikleri
        $product_meta = array(
            '_sku'               => $sku,
            '_regular_price'     => $selling_price,
            '_price'            => $selling_price,
            '_manage_stock'      => 'yes',
            '_stock'             => (int)$product_data->stock,
            '_stock_status'      => (int)$product_data->stock > 0 ? 'instock' : 'outofstock',
            '_py_kz_cost_price'  => $cost_price,
            '_py_kz_supplier_id' => $supplier_id,
        );
        
        if ($product_id) {
            // Ürün güncelleme
            $product_args['ID'] = $product_id;
            wp_update_post($product_args);
            
            foreach ($product_meta as $key => $value) {
                update_post_meta($product_id, $key, $value);
            }
            
            return 'updated';
        } else {
            // Yeni ürün oluştur
            $product_id = wp_insert_post($product_args);
            
            if (is_wp_error($product_id) || $product_id === 0) {
                return 'failed';
            }
            
            // Ürün tipini basit ürün olarak ayarla
            wp_set_object_terms($product_id, 'simple', 'product_type');
            
            // Meta verileri ekle
            foreach ($product_meta as $key => $value) {
                update_post_meta($product_id, $key, $value);
            }
            
            // Kategorileri işle
            if (isset($product_data->categories)) {
                $categories = array();
                foreach ($product_data->categories->category as $category) {
                    $categories[] = (string)$category;
                }
                $this->process_product_categories($product_id, $categories);
            }
            
            return 'imported';
        }
    }

    private function process_product_categories($product_id, $categories) {
        $category_ids = array();
        
        foreach ($categories as $category_name) {
            $term = term_exists($category_name, 'product_cat');
            
            if (!$term) {
                $term = wp_insert_term($category_name, 'product_cat');
            }
            
            if (!is_wp_error($term)) {
                $category_ids[] = (int)$term['term_id'];
            }
        }
        
        if (!empty($category_ids)) {
            wp_set_object_terms($product_id, $category_ids, 'product_cat');
        }
    }

    public function process_scheduled_imports() {
        global $wpdb;
        
        $xmls_table = $wpdb->prefix . 'pazar_yeri_supplier_xmls';
        $xmls = $wpdb->get_results(
            "SELECT * FROM $xmls_table WHERE auto_update = 1"
        );
        
        foreach ($xmls as $xml) {
            $this->import_from_xml($xml->xml_url, $xml->profit_margin, $xml->supplier_id);
            
            // Son güncelleme zamanını kaydet
            $wpdb->update(
                $xmls_table,
                array('last_import' => current_time('mysql')),
                array('id' => $xml->id)
            );
        }
    }
}
<?php
if (!defined('ABSPATH')) {
    exit;
}

class PYE_Importer {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_pye_import_products', array($this, 'ajax_import_products'));
        add_action('pye_hourly_import', array($this, 'process_scheduled_imports'));
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'pazar-yeri-entegrasyon') === false) {
            return;
        }
        
        wp_enqueue_script(
            'pye-import',
            PYE_PLUGIN_URL . 'assets/js/import.js',
            array('jquery'),
            PYE_VERSION,
            true
        );
        
        wp_localize_script('pye-import', 'pye_import_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'import_nonce' => wp_create_nonce('pye_import_nonce'),
            'importing_text' => __('Importing products...', 'pazar-yeri-entegrasyon'),
            'error_text' => __('An error occurred:', 'pazar-yeri-entegrasyon'),
            'success_text' => __('Products imported successfully!', 'pazar-yeri-entegrasyon'),
        ));
    }

    public function render_import_page() {
        // Çoklu dil desteği için metinler
        $translations = array(
            'import_products' => __('Import Products', 'pazar-yeri-entegrasyon'),
            'select_supplier' => __('Select Supplier', 'pazar-yeri-entegrasyon'),
            'xml_url' => __('XML URL', 'pazar-yeri-entegrasyon'),
            'profit_margin' => __('Profit Margin (%)', 'pazar-yeri-entegrasyon'),
            'auto_update' => __('Auto Update Every 6 Hours', 'pazar-yeri-entegrasyon'),
            'start_import' => __('Start Import', 'pazar-yeri-entegrasyon'),
        );
        
        include PYE_PLUGIN_DIR . 'templates/admin/import.php';
    }

    // Diğer metodlar...
}
