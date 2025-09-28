<?php
/**
 * Plugin Name: WooCommerce 綠界分期付款控制
 * Plugin URI: https://github.com/TTSoong/ecpay-installment-control
 * Description: 嚴格控制 WooCommerce 綠界金流分期付款選項，確保只有當購物車中所有商品都允許分期時才顯示分期選項。精細控制只隱藏分期選項而保留一般信用卡付款。
 * Version: 1.0.0
 * Author: TTSoong
 * Author URI: https://github.com/TTSoong
 * License: MIT
 * Text Domain: ecpay-installment-control
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.0
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// 定義常數
define('ECPAY_INSTALLMENT_CONTROL_VERSION', '1.0.0');
define('ECPAY_INSTALLMENT_CONTROL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ECPAY_INSTALLMENT_CONTROL_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 主要外掛類別
 */
class ECPay_Installment_Control_Plugin {
    
    /**
     * 單例實例
     */
    private static $instance = null;
    
    /**
     * 外掛設定選項
     */
    private $options;
    
    /**
     * 取得單例實例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建構函數
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 添加外掛設定連結
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
    }
    
    /**
     * 初始化外掛
     */
    public function init() {
        // 檢查相依性
        if (!$this->check_dependencies()) {
            return;
        }
        
        // 初始化設定
        $this->options = get_option('ecpay_installment_control_options', array());
        
        // 載入核心功能
        $this->load_includes();
        
        // 初始化管理介面
        if (is_admin()) {
            $this->init_admin();
        }
        
        // 初始化前台功能
        $this->init_frontend();
        
        // 載入語言檔案 (移到最後避免過早載入)
        add_action('init', array($this, 'load_textdomain'), 20);
    }
    
    /**
     * 載入語言檔案
     */
    public function load_textdomain() {
        load_plugin_textdomain('ecpay-installment-control', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * 檢查相依性
     */
    private function check_dependencies() {
        $missing_dependencies = array();
        
        // 檢查 WooCommerce
        if (!class_exists('WooCommerce')) {
            $missing_dependencies[] = 'WooCommerce';
        }
        
        // 檢查綠界外掛
        $ecpay_plugins = $this->detect_ecpay_plugins();
        if (empty($ecpay_plugins)) {
            $missing_dependencies[] = '綠界金流外掛 (ECPay)';
        }
        
        if (!empty($missing_dependencies)) {
            add_action('admin_notices', function() use ($missing_dependencies) {
                echo '<div class="error"><p>';
                echo '<strong>綠界分期付款控制外掛</strong> 需要以下外掛才能正常運作：<br>';
                echo '• ' . implode('<br>• ', $missing_dependencies);
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * 偵測綠界外掛
     */
    private function detect_ecpay_plugins() {
        $active_plugins = get_option('active_plugins', array());
        $ecpay_plugins = array();
        
        // 常見的綠界外掛檔案名稱
        $known_ecpay_plugins = array(
            'ecpay-payment-for-woocommerce/ecpay-payment-for-woocommerce.php',
            'ecpay-ecommerce-for-woocommerce/woocommerce-ecpay.php',
            'ry-woocommerce-ecpay-invoice/ry-woocommerce-ecpay-invoice.php',
            'woocommerce-gateway-ecpay/woocommerce-gateway-ecpay.php',
            'ecpay-logistics-for-woocommerce/ecpay-logistics-for-woocommerce.php'
        );
        
        foreach ($active_plugins as $plugin) {
            // 檢查是否包含 ecpay 關鍵字
            if (stripos($plugin, 'ecpay') !== false) {
                $ecpay_plugins[] = $plugin;
            }
            
            // 檢查已知的綠界外掛
            if (in_array($plugin, $known_ecpay_plugins)) {
                $ecpay_plugins[] = $plugin;
            }
        }
        
        return array_unique($ecpay_plugins);
    }
    
    /**
     * 載入核心檔案
     */
    private function load_includes() {
        require_once ECPAY_INSTALLMENT_CONTROL_PLUGIN_DIR . 'includes/class-installment-controller.php';
        require_once ECPAY_INSTALLMENT_CONTROL_PLUGIN_DIR . 'includes/class-product-manager.php';
        require_once ECPAY_INSTALLMENT_CONTROL_PLUGIN_DIR . 'includes/class-system-checker.php';
        
        // 初始化核心功能
        new ECPay_Installment_Controller();
        new ECPay_Product_Manager();
    }
    
    /**
     * 初始化管理介面
     */
    private function init_admin() {
        require_once ECPAY_INSTALLMENT_CONTROL_PLUGIN_DIR . 'admin/class-admin.php';
        require_once ECPAY_INSTALLMENT_CONTROL_PLUGIN_DIR . 'admin/class-setup-wizard.php';
        
        new ECPay_Installment_Admin();
        
        // 如果是第一次啟用，顯示設定精靈
        if (get_option('ecpay_installment_control_first_run', true)) {
            new ECPay_Installment_Setup_Wizard();
        }
    }
    
    /**
     * 初始化前台功能
     */
    private function init_frontend() {
        require_once ECPAY_INSTALLMENT_CONTROL_PLUGIN_DIR . 'public/class-frontend.php';
        new ECPay_Installment_Frontend();
    }
    
    /**
     * 外掛啟用
     */
    public function activate() {
        // 檢查是否為首次安裝
        $existing_options = get_option('ecpay_installment_control_options', false);
        
        if ($existing_options === false) {
            // 首次安裝：創建預設設定
            $default_options = array(
                'strict_mode' => true,
                'tag_name' => 'installment-allowed',
                'auto_detect_gateways' => true,
                'gateway_ids' => array(),
                'min_cart_amount' => 0,
                'show_cart_messages' => true,
                'show_checkout_messages' => true,
                'installment_control_mode' => 'hide_all',
                'allowed_installment_periods' => array()
            );
            
            add_option('ecpay_installment_control_options', $default_options);
            add_option('ecpay_installment_control_first_run', true);
        } else {
            // 更新安裝：保留現有設定，只添加新選項
            $updated_options = wp_parse_args($existing_options, array(
                'installment_control_mode' => 'hide_all',
                'allowed_installment_periods' => array()
            ));
            
            update_option('ecpay_installment_control_options', $updated_options);
            
            // 更新時不重新顯示設定精靈
            update_option('ecpay_installment_control_first_run', false);
        }
        
        // 創建或更新資料表
        $this->create_tables();
        
        // 排程檢查綠界外掛
        if (!wp_next_scheduled('ecpay_installment_check_gateways')) {
            wp_schedule_event(time(), 'daily', 'ecpay_installment_check_gateways');
        }
    }
    
    /**
     * 外掛停用
     */
    public function deactivate() {
        // 清理排程任務
        wp_clear_scheduled_hook('ecpay_installment_check_gateways');
    }
    
    /**
     * 創建資料表
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ecpay_installment_log';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            action varchar(100) NOT NULL,
            product_id bigint(20),
            cart_total decimal(10,2),
            gateway_id varchar(100),
            result varchar(20) NOT NULL,
            message text,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 取得設定選項
     */
    public function get_option($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    /**
     * 更新設定選項
     */
    public function update_option($key, $value) {
        $this->options[$key] = $value;
        update_option('ecpay_installment_control_options', $this->options);
    }
    
    /**
     * 添加外掛操作連結
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=ecpay-installment-control'),
            __('設定', 'ecpay-installment-control')
        );
        
        $wizard_link = sprintf(
            '<a href="%s" style="color: #0073aa; font-weight: bold;">%s</a>',
            admin_url('index.php?page=ecpay-installment-setup&step=1'),
            __('設定精靈', 'ecpay-installment-control')
        );
        
        // 將設定連結插入到最前面
        array_unshift($links, $settings_link, $wizard_link);
        
        return $links;
    }
    
    /**
     * 記錄日誌
     */
    public static function log($action, $result, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ecpay_installment_log',
            array(
                'action' => $action,
                'product_id' => isset($data['product_id']) ? $data['product_id'] : null,
                'cart_total' => isset($data['cart_total']) ? $data['cart_total'] : null,
                'gateway_id' => isset($data['gateway_id']) ? $data['gateway_id'] : null,
                'result' => $result,
                'message' => isset($data['message']) ? $data['message'] : null
            ),
            array('%s', '%d', '%f', '%s', '%s', '%s')
        );
    }
}

// 初始化外掛
ECPay_Installment_Control_Plugin::get_instance();
