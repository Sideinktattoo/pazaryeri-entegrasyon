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
<?php
if (!defined('ABSPATH')) {
    exit;
}

class PY_KZ_Supplier_Manager {
    private static $instance = null;
    private $table_name;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'pazar_yeri_suppliers';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('py_kz_hourly_import', array($this, 'process_scheduled_imports'));
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $suppliers_table = $wpdb->prefix . 'pazar_yeri_suppliers';
        $supplier_xmls_table = $wpdb->prefix . 'pazar_yeri_supplier_xmls';
        
        $sql = "CREATE TABLE $suppliers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            contact_person varchar(100),
            email varchar(100),
            phone varchar(20),
            address text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;
        
        CREATE TABLE $supplier_xmls_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            supplier_id mediumint(9) NOT NULL,
            xml_name varchar(100) NOT NULL,
            xml_url varchar(255) NOT NULL,
            profit_margin decimal(5,2) DEFAULT 0,
            auto_update tinyint(1) DEFAULT 0,
            category_mapping text,
            last_import datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            FOREIGN KEY (supplier_id) REFERENCES $suppliers_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Pazar Yeri Entegrasyon', 'pazar-yeri-kar-zarar'),
            __('Pazar Yeri', 'pazar-yeri-kar-zarar'),
            'manage_woocommerce',
            'pazar-yeri',
            array($this, 'render_main_page'),
            'dashicons-store',
            56
        );
        
        add_submenu_page(
            'pazar-yeri',
            __('Tedarikçiler', 'pazar-yeri-kar-zarar'),
            __('Tedarikçiler', 'pazar-yeri-kar-zarar'),
            'manage_woocommerce',
            'pazar-yeri-suppliers',
            array($this, 'render_suppliers_page')
        );
        
        add_submenu_page(
            'pazar-yeri',
            __('Ürün İçe Aktar', 'pazar-yeri-kar-zarar'),
            __('Ürün İçe Aktar', 'pazar-yeri-kar-zarar'),
            'manage_woocommerce',
            'pazar-yeri-import',
            array(PY_KZ_Importer::instance(), 'render_import_page')
        );
        
        add_submenu_page(
            'pazar-yeri',
            __('Ürün Dışa Aktar', 'pazar-yeri-kar-zarar'),
            __('Ürün Dışa Aktar', 'pazar-yeri-kar-zarar'),
            'manage_woocommerce',
            'pazar-yeri-export',
            array(PY_KZ_Exporter::instance(), 'render_export_page')
        );
        
        add_submenu_page(
            'pazar-yeri',
            __('Pazar Yeri Ayarları', 'pazar-yeri-kar-zarar'),
            __('Pazar Yeri Ayarları', 'pazar-yeri-kar-zarar'),
            'manage_woocommerce',
            'pazar-yeri-marketplaces',
            array(PY_KZ_Marketplace_Integration::instance(), 'render_marketplace_page')
        );
        
        add_submenu_page(
            'pazar-yeri',
            __('Kar/Zarar Hesaplama', 'pazar-yeri-kar-zarar'),
            __('Kar/Zarar Hesaplama', 'pazar-yeri-kar-zarar'),
            'manage_woocommerce',
            'pazar-yeri-profit',
            array(PY_KZ_Profit_Calculator::instance(), 'render_profit_page')
        );
    }

    public function render_main_page() {
        include PY_KZ_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    public function render_suppliers_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_supplier_form();
                break;
            case 'view':
                $this->render_supplier_detail();
                break;
            case 'list':
            default:
                $this->render_suppliers_list();
                break;
        }
    }

    // Diğer yardımcı metodlar...
}
<?php
if (!defined('ABSPATH')) {
    exit;
}

class PYE_Supplier_Manager {
    private static $instance = null;
    private $table_name;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'pye_suppliers';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('pye_hourly_import', array($this, 'process_scheduled_imports'));
        
        // Çoklu dil desteği için metinleri yükle
        add_action('init', array($this, 'load_textdomain'));
    }

    public function load_textdomain() {
        load_plugin_textdomain('pazar-yeri-entegrasyon', false, dirname(plugin_basename(__FILE__)) . '/languages/';
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $suppliers_table = $wpdb->prefix . 'pye_suppliers';
        $supplier_xmls_table = $wpdb->prefix . 'pye_supplier_xmls';
        
        $sql = "CREATE TABLE $suppliers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            contact_person varchar(100),
            email varchar(100),
            phone varchar(20),
            address text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;
        
        CREATE TABLE $supplier_xmls_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            supplier_id mediumint(9) NOT NULL,
            xml_name varchar(100) NOT NULL,
            xml_url varchar(255) NOT NULL,
            profit_margin decimal(5,2) DEFAULT 0,
            auto_update tinyint(1) DEFAULT 0,
            category_mapping text,
            last_import datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            FOREIGN KEY (supplier_id) REFERENCES $suppliers_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Marketplace Integration', 'pazar-yeri-entegrasyon'),
            __('Marketplace', 'pazar-yeri-entegrasyon'),
            'manage_woocommerce',
            'pazar-yeri-entegrasyon',
            array($this, 'render_main_page'),
            'dashicons-store',
            56
        );
        
        add_submenu_page(
            'pazar-yeri-entegrasyon',
            __('Suppliers', 'pazar-yeri-entegrasyon'),
            __('Suppliers', 'pazar-yeri-entegrasyon'),
            'manage_woocommerce',
            'pazar-yeri-suppliers',
            array($this, 'render_suppliers_page')
        );
        
        // Diğer menü öğeleri...
    }

    // Diğer metodlar...
}
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
