<?php
class PYE_Supplier_Manager {
    private static $instance;
    private $table_name;

    public static function instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'pye_suppliers';
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('pye_hourly_import', [$this, 'process_scheduled_imports']);
    }

    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $suppliers_table = $wpdb->prefix . 'pye_suppliers';
        $xml_table = $wpdb->prefix . 'pye_supplier_xmls';
        
        $sql = "CREATE TABLE $suppliers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;
        
        CREATE TABLE $xml_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            supplier_id mediumint(9) NOT NULL,
            xml_url varchar(255) NOT NULL,
            profit_margin decimal(5,2) DEFAULT 0,
            PRIMARY KEY (id))
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Marketplace', 'pazar-yeri-entegrasyon'),
            __('Marketplace', 'pazar-yeri-entegrasyon'),
            'manage_woocommerce',
            'pazar-yeri-entegrasyon',
            [$this, 'render_dashboard'],
            'dashicons-store',
            56
        );
        
        add_submenu_page(
            'pazar-yeri-entegrasyon',
            __('Suppliers', 'pazar-yeri-entegrasyon'),
            __('Suppliers', 'pazar-yeri-entegrasyon'),
            'manage_woocommerce',
            'pazar-yeri-suppliers',
            [$this, 'render_suppliers']
        );
    }

    public function render_dashboard() {
        include PYE_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    public function render_suppliers() {
        include PYE_PLUGIN_DIR . 'templates/admin/suppliers.php';
    }

    public function process_scheduled_imports() {
        global $wpdb;
        $xmls = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pye_supplier_xmls");
        
        foreach ($xmls as $xml) {
            PYE_Importer::instance()->import_from_xml($xml->xml_url, $xml->profit_margin, $xml->supplier_id);
        }
    }
}
