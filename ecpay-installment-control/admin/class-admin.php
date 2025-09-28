<?php
/**
 * 管理介面類別
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECPay_Installment_Admin {
    
    private $options;
    
    public function __construct() {
        $this->options = get_option('ecpay_installment_control_options', array());
        $this->init_hooks();
    }
    
    /**
     * 初始化鉤子
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX 處理
        add_action('wp_ajax_ecpay_bulk_enable_installment', array($this, 'ajax_bulk_enable_installment'));
        add_action('wp_ajax_ecpay_bulk_disable_installment', array($this, 'ajax_bulk_disable_installment'));
        add_action('wp_ajax_ecpay_test_system', array($this, 'ajax_test_system'));
    }
    
    /**
     * 添加管理選單
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('綠界分期付款控制', 'ecpay-installment-control'),
            __('分期付款控制', 'ecpay-installment-control'),
            'manage_woocommerce',
            'ecpay-installment-control',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 註冊設定
     */
    public function register_settings() {
        register_setting('ecpay_installment_control_settings', 'ecpay_installment_control_options');
    }
    
    /**
     * 顯示管理通知
     */
    public function show_admin_notices() {
        // 顯示批次操作結果
        if (isset($_GET['installment_enabled'])) {
            $count = intval($_GET['installment_enabled']);
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(__('已為 %d 個商品啟用分期付款。', 'ecpay-installment-control'), $count) . '</p>';
            echo '</div>';
        }
        
        if (isset($_GET['installment_disabled'])) {
            $count = intval($_GET['installment_disabled']);
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(__('已為 %d 個商品停用分期付款。', 'ecpay-installment-control'), $count) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * 載入管理腳本
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_ecpay-installment-control') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->get_admin_js());
        wp_add_inline_style('wp-admin', $this->get_admin_css());
    }
    
    /**
     * 管理頁面
     */
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'status';
        
        ?>
        <div class="wrap">
            <h1><?php _e('綠界分期付款控制', 'ecpay-installment-control'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=ecpay-installment-control&tab=status" class="nav-tab <?php echo $active_tab == 'status' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('系統狀態', 'ecpay-installment-control'); ?>
                </a>
                <a href="?page=ecpay-installment-control&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('設定', 'ecpay-installment-control'); ?>
                </a>
                <a href="?page=ecpay-installment-control&tab=products" class="nav-tab <?php echo $active_tab == 'products' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('商品管理', 'ecpay-installment-control'); ?>
                </a>
                <a href="?page=ecpay-installment-control&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('日誌', 'ecpay-installment-control'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'products':
                        $this->render_products_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    default:
                        $this->render_status_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 系統狀態頁面
     */
    private function render_status_tab() {
        $system_report = ECPay_System_Checker::generate_system_report();
        $product_stats = ECPay_Product_Manager::get_installment_products_stats();
        
        ?>
        <div class="ecpay-status-tab">
            <h2><?php _e('系統狀態概覽', 'ecpay-installment-control'); ?></h2>
            
            <!-- 系統檢查 -->
            <div class="postbox">
                <h3><span><?php _e('系統需求檢查', 'ecpay-installment-control'); ?></span></h3>
                <div class="inside">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('項目', 'ecpay-installment-control'); ?></th>
                                <th><?php _e('要求', 'ecpay-installment-control'); ?></th>
                                <th><?php _e('目前狀態', 'ecpay-installment-control'); ?></th>
                                <th><?php _e('結果', 'ecpay-installment-control'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($system_report['requirements'] as $key => $requirement): ?>
                            <tr>
                                <td><?php echo $this->get_requirement_label($key); ?></td>
                                <td><?php echo esc_html($requirement['required']); ?></td>
                                <td><?php echo esc_html($requirement['current']); ?></td>
                                <td>
                                    <?php if ($requirement['status']): ?>
                                        <span style="color: #46b450;">✓ 通過</span>
                                    <?php else: ?>
                                        <span style="color: #dc3232;">✗ 不符合</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 綠界外掛狀態 -->
            <div class="postbox">
                <h3><span><?php _e('綠界外掛狀態', 'ecpay-installment-control'); ?></span></h3>
                <div class="inside">
                    <?php if (!empty($system_report['plugin_info']['ecpay_plugins'])): ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('外掛名稱', 'ecpay-installment-control'); ?></th>
                                    <th><?php _e('版本', 'ecpay-installment-control'); ?></th>
                                    <th><?php _e('狀態', 'ecpay-installment-control'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($system_report['plugin_info']['ecpay_plugins'] as $plugin): ?>
                                <tr>
                                    <td><?php echo esc_html($plugin['name']); ?></td>
                                    <td><?php echo esc_html($plugin['version']); ?></td>
                                    <td>
                                        <?php if ($plugin['active']): ?>
                                            <span style="color: #46b450;">啟用</span>
                                        <?php else: ?>
                                            <span style="color: #dc3232;">停用</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #dc3232;"><?php _e('未偵測到綠界外掛', 'ecpay-installment-control'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 商品統計 -->
            <div class="postbox">
                <h3><span><?php _e('商品分期統計', 'ecpay-installment-control'); ?></span></h3>
                <div class="inside">
                    <div class="ecpay-stats-grid">
                        <div class="stat-box">
                            <h4><?php _e('總商品數', 'ecpay-installment-control'); ?></h4>
                            <span class="stat-number"><?php echo number_format($product_stats['total']); ?></span>
                        </div>
                        <div class="stat-box">
                            <h4><?php _e('支援分期', 'ecpay-installment-control'); ?></h4>
                            <span class="stat-number" style="color: #46b450;"><?php echo number_format($product_stats['installment_enabled']); ?></span>
                        </div>
                        <div class="stat-box">
                            <h4><?php _e('不支援分期', 'ecpay-installment-control'); ?></h4>
                            <span class="stat-number" style="color: #dc3232;"><?php echo number_format($product_stats['installment_disabled']); ?></span>
                        </div>
                        <div class="stat-box">
                            <h4><?php _e('分期比例', 'ecpay-installment-control'); ?></h4>
                            <span class="stat-number"><?php echo $product_stats['percentage']; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 快速操作 -->
            <div class="postbox">
                <h3><span><?php _e('快速操作', 'ecpay-installment-control'); ?></span></h3>
                <div class="inside">
                    <p>
                        <button type="button" class="button button-primary" onclick="testSystem()">
                            <?php _e('測試系統功能', 'ecpay-installment-control'); ?>
                        </button>
                        <button type="button" class="button" onclick="bulkEnableInstallment()">
                            <?php _e('為所有商品啟用分期', 'ecpay-installment-control'); ?>
                        </button>
                        <button type="button" class="button" onclick="bulkDisableInstallment()">
                            <?php _e('為所有商品停用分期', 'ecpay-installment-control'); ?>
                        </button>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 設定頁面
     */
    private function render_settings_tab() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('ecpay_installment_settings', 'ecpay_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('嚴格控制模式', 'ecpay-installment-control'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="strict_mode" value="1" <?php checked($this->get_option('strict_mode', true)); ?>>
                            <?php _e('只有當購物車中所有商品都允許分期時，才顯示分期付款選項', 'ecpay-installment-control'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('最低購物車金額', 'ecpay-installment-control'); ?></th>
                    <td>
                        <input type="number" name="min_cart_amount" value="<?php echo esc_attr($this->get_option('min_cart_amount', 0)); ?>" min="0" step="100">
                        <p class="description"><?php _e('設定使用分期付款的最低購物車金額（0 表示無限制）', 'ecpay-installment-control'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('商品標籤名稱', 'ecpay-installment-control'); ?></th>
                    <td>
                        <input type="text" name="tag_name" value="<?php echo esc_attr($this->get_option('tag_name', 'installment-allowed')); ?>">
                        <p class="description"><?php _e('用於標記支援分期付款商品的標籤名稱', 'ecpay-installment-control'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('允許的分期期數', 'ecpay-installment-control'); ?></th>
                    <td>
                        <?php
                        $allowed_periods = $this->get_option('allowed_installment_periods', array());
                        $available_periods = array('3', '6', '12', '18', '24');
                        ?>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e('選擇允許的分期期數', 'ecpay-installment-control'); ?></legend>
                            <?php foreach ($available_periods as $period): ?>
                            <label>
                                <input type="checkbox" name="allowed_installment_periods[]" value="<?php echo esc_attr($period); ?>" 
                                       <?php checked(in_array($period, $allowed_periods)); ?>>
                                <?php printf(__('%s 期', 'ecpay-installment-control'), $period); ?>
                            </label><br>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">
                            <?php _e('選擇允許的分期期數。如果不勾選任何選項，將隱藏所有分期付款選項但保留一般信用卡付款。', 'ecpay-installment-control'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('分期控制模式', 'ecpay-installment-control'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e('選擇分期控制模式', 'ecpay-installment-control'); ?></legend>
                            <label>
                                <input type="radio" name="installment_control_mode" value="hide_all" 
                                       <?php checked($this->get_option('installment_control_mode', 'hide_all'), 'hide_all'); ?>>
                                <?php _e('隱藏所有分期選項（保留一般信用卡）', 'ecpay-installment-control'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="installment_control_mode" value="selective" 
                                       <?php checked($this->get_option('installment_control_mode', 'hide_all'), 'selective'); ?>>
                                <?php _e('選擇性顯示分期期數', 'ecpay-installment-control'); ?>
                            </label><br>
                        </fieldset>
                        <p class="description">
                            <?php _e('選擇如何控制分期付款選項的顯示。', 'ecpay-installment-control'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('顯示訊息', 'ecpay-installment-control'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_cart_messages" value="1" <?php checked($this->get_option('show_cart_messages', true)); ?>>
                            <?php _e('在購物車頁面顯示分期可用性', 'ecpay-installment-control'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="show_checkout_messages" value="1" <?php checked($this->get_option('show_checkout_messages', true)); ?>>
                            <?php _e('在結帳頁面顯示分期可用性', 'ecpay-installment-control'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * 商品管理頁面
     */
    private function render_products_tab() {
        $products = ECPay_Product_Manager::get_installment_products(50);
        
        ?>
        <h2><?php _e('支援分期的商品', 'ecpay-installment-control'); ?></h2>
        
        <?php if (!empty($products)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('商品名稱', 'ecpay-installment-control'); ?></th>
                        <th><?php _e('價格', 'ecpay-installment-control'); ?></th>
                        <th><?php _e('狀態', 'ecpay-installment-control'); ?></th>
                        <th><?php _e('操作', 'ecpay-installment-control'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $product['id'] . '&action=edit'); ?>">
                                <?php echo esc_html($product['name']); ?>
                            </a>
                        </td>
                        <td><?php echo $product['formatted_price']; ?></td>
                        <td>
                            <span style="color: #46b450;">✓ 支援分期</span>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $product['id'] . '&action=edit'); ?>" class="button button-small">
                                <?php _e('編輯', 'ecpay-installment-control'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('目前沒有商品支援分期付款。', 'ecpay-installment-control'); ?></p>
        <?php endif; ?>
        
        <p>
            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-primary">
                <?php _e('管理所有商品', 'ecpay-installment-control'); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * 日誌頁面
     */
    private function render_logs_tab() {
        global $wpdb;
        
        $logs = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}ecpay_installment_log 
            ORDER BY time DESC 
            LIMIT 100
        ");
        
        ?>
        <h2><?php _e('系統日誌', 'ecpay-installment-control'); ?></h2>
        
        <?php if (!empty($logs)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('時間', 'ecpay-installment-control'); ?></th>
                        <th><?php _e('操作', 'ecpay-installment-control'); ?></th>
                        <th><?php _e('結果', 'ecpay-installment-control'); ?></th>
                        <th><?php _e('詳細', 'ecpay-installment-control'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->time); ?></td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td>
                            <?php if ($log->result === 'success'): ?>
                                <span style="color: #46b450;">成功</span>
                            <?php else: ?>
                                <span style="color: #dc3232;"><?php echo esc_html($log->result); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('目前沒有日誌記錄。', 'ecpay-installment-control'); ?></p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * 儲存設定
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['ecpay_nonce'], 'ecpay_installment_settings')) {
            return;
        }
        
        $allowed_periods = isset($_POST['allowed_installment_periods']) ? array_map('sanitize_text_field', $_POST['allowed_installment_periods']) : array();
        
        $options = array(
            'strict_mode' => isset($_POST['strict_mode']),
            'min_cart_amount' => (float)$_POST['min_cart_amount'],
            'tag_name' => sanitize_text_field($_POST['tag_name']),
            'allowed_installment_periods' => $allowed_periods,
            'installment_control_mode' => sanitize_text_field($_POST['installment_control_mode']),
            'show_cart_messages' => isset($_POST['show_cart_messages']),
            'show_checkout_messages' => isset($_POST['show_checkout_messages'])
        );
        
        // 保留現有的 gateway_ids
        $current_options = get_option('ecpay_installment_control_options', array());
        if (isset($current_options['gateway_ids'])) {
            $options['gateway_ids'] = $current_options['gateway_ids'];
        }
        
        update_option('ecpay_installment_control_options', $options);
        $this->options = $options;
        
        echo '<div class="notice notice-success is-dismissible"><p>' . __('設定已儲存。', 'ecpay-installment-control') . '</p></div>';
    }
    
    /**
     * 取得選項值
     */
    private function get_option($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    /**
     * 取得需求項目標籤
     */
    private function get_requirement_label($key) {
        $labels = array(
            'php_version' => 'PHP 版本',
            'wp_version' => 'WordPress 版本', 
            'wc_version' => 'WooCommerce 版本',
            'ecpay_plugin' => '綠界外掛'
        );
        
        return isset($labels[$key]) ? $labels[$key] : $key;
    }
    
    /**
     * AJAX 批次啟用分期
     */
    public function ajax_bulk_enable_installment() {
        check_ajax_referer('ecpay_bulk_action', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('權限不足');
        }
        
        global $wpdb;
        
        $products = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish'
        ");
        
        $count = 0;
        foreach ($products as $product_id) {
            update_post_meta($product_id, '_installment_enabled', 'yes');
            $count++;
        }
        
        wp_send_json_success(array(
            'message' => sprintf('已為 %d 個商品啟用分期付款', $count),
            'count' => $count
        ));
    }
    
    /**
     * AJAX 批次停用分期
     */
    public function ajax_bulk_disable_installment() {
        check_ajax_referer('ecpay_bulk_action', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('權限不足');
        }
        
        global $wpdb;
        
        $products = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish'
        ");
        
        $count = 0;
        foreach ($products as $product_id) {
            update_post_meta($product_id, '_installment_enabled', 'no');
            $count++;
        }
        
        wp_send_json_success(array(
            'message' => sprintf('已為 %d 個商品停用分期付款', $count),
            'count' => $count
        ));
    }
    
    /**
     * AJAX 測試系統
     */
    public function ajax_test_system() {
        check_ajax_referer('ecpay_test_system', 'nonce');
        
        $tests = array();
        
        // 測試 WooCommerce
        $tests['woocommerce'] = class_exists('WooCommerce');
        
        // 測試綠界外掛
        $ecpay_plugins = ECPay_System_Checker::get_ecpay_plugins();
        $tests['ecpay_plugin'] = !empty($ecpay_plugins);
        
        // 測試付款方式
        $gateways = ECPay_System_Checker::detect_ecpay_gateway_ids();
        $tests['payment_gateways'] = !empty($gateways);
        
        $all_passed = $tests['woocommerce'] && $tests['ecpay_plugin'] && $tests['payment_gateways'];
        
        wp_send_json_success(array(
            'tests' => $tests,
            'all_passed' => $all_passed,
            'message' => $all_passed ? '所有測試通過' : '部分測試失敗'
        ));
    }
    
    /**
     * 管理介面 JavaScript
     */
    private function get_admin_js() {
        return "
        function testSystem() {
            jQuery.post(ajaxurl, {
                action: 'ecpay_test_system',
                nonce: '" . wp_create_nonce('ecpay_test_system') . "'
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert('測試失敗：' + response.data);
                }
            });
        }
        
        function bulkEnableInstallment() {
            if (confirm('確定要為所有商品啟用分期付款嗎？')) {
                jQuery.post(ajaxurl, {
                    action: 'ecpay_bulk_enable_installment',
                    nonce: '" . wp_create_nonce('ecpay_bulk_action') . "'
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('操作失敗：' + response.data);
                    }
                });
            }
        }
        
        function bulkDisableInstallment() {
            if (confirm('確定要為所有商品停用分期付款嗎？')) {
                jQuery.post(ajaxurl, {
                    action: 'ecpay_bulk_disable_installment',
                    nonce: '" . wp_create_nonce('ecpay_bulk_action') . "'
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('操作失敗：' + response.data);
                    }
                });
            }
        }
        ";
    }
    
    /**
     * 管理介面 CSS
     */
    private function get_admin_css() {
        return "
        .ecpay-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-box {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #ddd;
        }
        
        .stat-box h4 {
            margin: 0 0 10px 0;
            color: #666;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .tab-content {
            margin-top: 20px;
        }
        ";
    }
}
