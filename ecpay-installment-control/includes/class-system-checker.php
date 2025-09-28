<?php
/**
 * 系統檢測類別
 * 自動檢測綠界外掛和付款方式 ID
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECPay_System_Checker {
    
    /**
     * 檢測綠界付款方式 ID
     */
    public static function detect_ecpay_gateway_ids() {
        $gateway_ids = array();
        
        if (!class_exists('WC_Payment_Gateways')) {
            return $gateway_ids;
        }
        
        $payment_gateways = WC_Payment_Gateways::instance();
        $available_gateways = $payment_gateways->get_available_payment_gateways();
        
        foreach ($available_gateways as $gateway_id => $gateway) {
            // 檢查是否為綠界相關的付款方式
            if (self::is_ecpay_gateway($gateway_id, $gateway)) {
                $gateway_info = array(
                    'id' => $gateway_id,
                    'title' => $gateway->get_title(),
                    'description' => $gateway->get_description(),
                    'is_installment' => self::is_installment_gateway($gateway_id, $gateway)
                );
                
                $gateway_ids[] = $gateway_info;
            }
        }
        
        return $gateway_ids;
    }
    
    /**
     * 檢查是否為綠界付款方式
     */
    private static function is_ecpay_gateway($gateway_id, $gateway) {
        // 檢查 ID 是否包含 ecpay 關鍵字
        if (stripos($gateway_id, 'ecpay') !== false) {
            return true;
        }
        
        // 檢查類別名稱
        $class_name = strtolower(get_class($gateway));
        if (stripos($class_name, 'ecpay') !== false) {
            return true;
        }
        
        // 檢查標題是否包含綠界相關字詞
        $title = strtolower($gateway->get_title());
        $ecpay_keywords = array('ecpay', '綠界', '綠界科技', 'green world');
        
        foreach ($ecpay_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 檢查是否為分期付款方式
     */
    private static function is_installment_gateway($gateway_id, $gateway) {
        // 檢查 ID 是否包含分期關鍵字
        $installment_keywords = array('installment', 'install', '分期');
        
        foreach ($installment_keywords as $keyword) {
            if (stripos($gateway_id, $keyword) !== false) {
                return true;
            }
        }
        
        // 檢查標題是否包含分期關鍵字
        $title = strtolower($gateway->get_title());
        foreach ($installment_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return true;
            }
        }
        
        // 檢查描述是否包含分期關鍵字
        $description = strtolower($gateway->get_description());
        foreach ($installment_keywords as $keyword) {
            if (stripos($description, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 檢查系統環境
     */
    public static function check_system_requirements() {
        $requirements = array();
        
        // PHP 版本檢查
        $requirements['php_version'] = array(
            'required' => '7.0',
            'current' => phpversion(),
            'status' => version_compare(phpversion(), '7.0', '>=')
        );
        
        // WordPress 版本檢查
        global $wp_version;
        $requirements['wp_version'] = array(
            'required' => '5.0',
            'current' => $wp_version,
            'status' => version_compare($wp_version, '5.0', '>=')
        );
        
        // WooCommerce 版本檢查
        if (class_exists('WooCommerce')) {
            $wc_version = WC()->version;
            $requirements['wc_version'] = array(
                'required' => '3.0',
                'current' => $wc_version,
                'status' => version_compare($wc_version, '3.0', '>=')
            );
        } else {
            $requirements['wc_version'] = array(
                'required' => '3.0',
                'current' => '未安裝',
                'status' => false
            );
        }
        
        // 綠界外掛檢查
        $ecpay_plugins = self::get_ecpay_plugins();
        $requirements['ecpay_plugin'] = array(
            'required' => '任一綠界外掛',
            'current' => !empty($ecpay_plugins) ? implode(', ', array_column($ecpay_plugins, 'name')) : '未安裝',
            'status' => !empty($ecpay_plugins)
        );
        
        return $requirements;
    }
    
    /**
     * 取得已安裝的綠界外掛
     */
    public static function get_ecpay_plugins() {
        $active_plugins = get_option('active_plugins', array());
        $all_plugins = get_plugins();
        $ecpay_plugins = array();
        
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_name = strtolower($plugin_data['Name']);
            $plugin_description = strtolower($plugin_data['Description']);
            
            // 檢查是否為綠界相關外掛
            $ecpay_keywords = array('ecpay', '綠界', 'green world');
            $is_ecpay = false;
            
            foreach ($ecpay_keywords as $keyword) {
                if (stripos($plugin_name, $keyword) !== false || 
                    stripos($plugin_description, $keyword) !== false ||
                    stripos($plugin_file, $keyword) !== false) {
                    $is_ecpay = true;
                    break;
                }
            }
            
            if ($is_ecpay) {
                $ecpay_plugins[] = array(
                    'file' => $plugin_file,
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'active' => in_array($plugin_file, $active_plugins),
                    'description' => $plugin_data['Description']
                );
            }
        }
        
        return $ecpay_plugins;
    }
    
    /**
     * 檢測購物車分期設定衝突
     */
    public static function detect_conflicts() {
        $conflicts = array();
        
        // 檢查是否有其他分期控制外掛
        $active_plugins = get_option('active_plugins', array());
        foreach ($active_plugins as $plugin) {
            if (stripos($plugin, 'installment') !== false && 
                stripos($plugin, 'ecpay-installment-control') === false) {
                $conflicts[] = array(
                    'type' => 'plugin_conflict',
                    'message' => "發現其他分期控制外掛：{$plugin}，可能會產生衝突"
                );
            }
        }
        
        // 檢查主題是否有自定義分期控制
        $theme_functions = get_template_directory() . '/functions.php';
        if (file_exists($theme_functions)) {
            $functions_content = file_get_contents($theme_functions);
            if (stripos($functions_content, 'woocommerce_available_payment_gateways') !== false) {
                $conflicts[] = array(
                    'type' => 'theme_conflict',
                    'message' => '主題的 functions.php 中可能包含付款方式控制代碼，可能會產生衝突'
                );
            }
        }
        
        return $conflicts;
    }
    
    /**
     * 生成系統報告
     */
    public static function generate_system_report() {
        $report = array();
        
        // 基本資訊
        $report['basic_info'] = array(
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'timezone' => wp_timezone_string()
        );
        
        // 外掛資訊
        $report['plugin_info'] = array(
            'ecpay_plugins' => self::get_ecpay_plugins(),
            'detected_gateways' => self::detect_ecpay_gateway_ids(),
            'conflicts' => self::detect_conflicts()
        );
        
        // 系統需求
        $report['requirements'] = self::check_system_requirements();
        
        // WooCommerce 設定
        if (class_exists('WooCommerce')) {
            $report['woocommerce'] = array(
                'version' => WC()->version,
                'currency' => get_woocommerce_currency(),
                'base_country' => WC()->countries->get_base_country(),
                'payment_gateways_count' => count(WC_Payment_Gateways::instance()->get_available_payment_gateways())
            );
        }
        
        return $report;
    }
    
    /**
     * 檢查外掛更新
     */
    public static function check_plugin_updates() {
        $updates = array();
        
        // 檢查綠界外掛是否有更新
        $ecpay_plugins = self::get_ecpay_plugins();
        $update_plugins = get_site_transient('update_plugins');
        
        if ($update_plugins && isset($update_plugins->response)) {
            foreach ($ecpay_plugins as $plugin) {
                if (isset($update_plugins->response[$plugin['file']])) {
                    $updates[] = array(
                        'plugin' => $plugin['name'],
                        'current_version' => $plugin['version'],
                        'new_version' => $update_plugins->response[$plugin['file']]->new_version
                    );
                }
            }
        }
        
        return $updates;
    }
}
