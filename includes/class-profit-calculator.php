<?php
if (!defined('ABSPATH')) {
    exit;
}

class PY_KZ_Profit_Calculator {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_product_options_pricing', array($this, 'add_cost_price_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_cost_price_field'));
        add_filter('woocommerce_get_price_html', array($this, 'display_profit_margin'), 10, 2);
        add_action('woocommerce_admin_order_totals_after_total', array($this, 'display_order_profit'));
    }

    public function render_profit_page() {
        include PY_KZ_PLUGIN_DIR . 'templates/admin/profit-calculator.php';
    }

    public function add_cost_price_field() {
        woocommerce_wp_text_input(array(
            'id' => '_py_kz_cost_price',
            'label' => __('Maliyet Fiyatı', 'pazar-yeri-kar-zarar') . ' (' . get_woocommerce_currency_symbol() . ')',
            'description' => __('Ürünün tedarikçiden alınan fiyatı', 'pazar-yeri-kar-zarar'),
            'data_type' => 'price',
            'desc_tip' => true,
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_py_kz_packaging_cost',
            'label' => __('Paketleme Maliyeti', 'pazar-yeri-kar-zarar') . ' (' . get_woocommerce_currency_symbol() . ')',
            'data_type' => 'price',
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_py_kz_shipping_cost',
            'label' => __('Kargo Maliyeti', 'pazar-yeri-kar-zarar') . ' (' . get_woocommerce_currency_symbol() . ')',
            'data_type' => 'price',
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_py_kz_marketplace_fee',
            'label' => __('Pazar Yeri Komisyonu (%)', 'pazar-yeri-kar-zarar'),
            'description' => __('Pazar yerinin aldığı komisyon yüzdesi', 'pazar-yeri-kar-zarar'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0',
                'max' => '100'
            ),
        ));
    }

    public function save_cost_price_field($product_id) {
        $cost_price = isset($_POST['_py_kz_cost_price']) ? wc_format_decimal($_POST['_py_kz_cost_price']) : '';
        update_post_meta($product_id, '_py_kz_cost_price', $cost_price);
        
        $packaging_cost = isset($_POST['_py_kz_packaging_cost']) ? wc_format_decimal($_POST['_py_kz_packaging_cost']) : 0;
        update_post_meta($product_id, '_py_kz_packaging_cost', $packaging_cost);
        
        $shipping_cost = isset($_POST['_py_kz_shipping_cost']) ? wc_format_decimal($_POST['_py_kz_shipping_cost']) : 0;
        update_post_meta($product_id, '_py_kz_shipping_cost', $shipping_cost);
        
        $marketplace_fee = isset($_POST['_py_kz_marketplace_fee']) ? floatval($_POST['_py_kz_marketplace_fee']) : 0;
        update_post_meta($product_id, '_py_kz_marketplace_fee', $marketplace_fee);
    }

    public function display_profit_margin($price, $product) {
        if (!is_admin()) {
            return $price;
        }
        
        $cost_price = (float)get_post_meta($product->get_id(), '_py_kz_cost_price', true);
        
        if ($cost_price > 0 && $product->get_price() > 0) {
            $profit = $product->get_price() - $cost_price;
            $margin = ($profit / $cost_price) * 100;
            
            $price .= '<span class="profit-margin" style="display:block;color:' . ($margin >= 0 ? '#008000' : '#ff0000') . '">';
            $price .= sprintf(__('Kar: %s (%s%%)', 'pazar-yeri-kar-zarar'), wc_price($profit), number_format($margin, 2));
            $price .= '</span>';
        }
        
        return $price;
    }

    public function calculate_order_profit($order_id) {
        $order = wc_get_order($order_id);
        $total_profit = 0;
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if (!$product) {
                continue;
            }
            
            $cost_price = (float)get_post_meta($product->get_id(), '_py_kz_cost_price', true);
            $packaging_cost = (float)get_post_meta($product->get_id(), '_py_kz_packaging_cost', true);
            $shipping_cost = (float)get_post_meta($product->get_id(), '_py_kz_shipping_cost', true);
            $marketplace_fee = (float)get_post_meta($product->get_id(), '_py_kz_marketplace_fee', true);
            
            if ($cost_price > 0) {
                $item_total = $item->get_total();
                $item_qty = $item->get_quantity();
                
                // Komisyon hesapla
                $commission = ($item_total * $marketplace_fee) / 100;
                
                // Kar hesapla
                $item_profit = ($item_total - $commission) - (($cost_price + $packaging_cost + $shipping_cost) * $item_qty);
                $total_profit += $item_profit;
            }
        }
        
        return $total_profit;
    }

    public function display_order_profit($order_id) {
        $profit = $this->calculate_order_profit($order_id);
        $color = $profit >= 0 ? '#008000' : '#ff0000';
        ?>
        <tr>
            <td class="label"><?php _e('Net Kar', 'pazar-yeri-kar-zarar'); ?>:</td>
            <td width="1%"></td>
            <td class="total" style="color:<?php echo $color; ?>">
                <?php echo wc_price($profit); ?>
            </td>
        </tr>
        <?php
    }
}
