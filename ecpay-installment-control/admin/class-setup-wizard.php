<?php
/**
 * 設定精靈類別
 * 初次安裝時的引導設定
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECPay_Installment_Setup_Wizard {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_setup_page'));
        add_action('admin_init', array($this, 'handle_setup_steps'));
        add_action('admin_notices', array($this, 'show_setup_notice'));
    }
    
    /**
     * 添加設定精靈頁面
     */
    public function add_setup_page() {
        add_dashboard_page(
            '綠界分期付款控制 - 設定精靈',
            '設定精靈',
            'manage_options',
            'ecpay-installment-setup',
            array($this, 'setup_wizard_page')
        );
    }
    
    /**
     * 顯示設定提醒
     */
    public function show_setup_notice() {
        if (get_option('ecpay_installment_control_first_run', false)) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>綠界分期付款控制外掛已啟用！</strong> ';
            echo '<a href="' . admin_url('index.php?page=ecpay-installment-setup') . '">點擊這裡開始設定</a>';
            echo '</p></div>';
        }
    }
    
    /**
     * 處理設定步驟
     */
    public function handle_setup_steps() {
        if (isset($_POST['ecpay_setup_step'])) {
            $step = sanitize_text_field($_POST['ecpay_setup_step']);
            
            switch ($step) {
                case 'detect_gateways':
                    $this->handle_gateway_detection();
                    break;
                case 'configure_settings':
                    $this->handle_settings_configuration();
                    break;
                case 'complete_setup':
                    $this->complete_setup();
                    break;
            }
        }
    }
    
    /**
     * 設定精靈頁面
     */
    public function setup_wizard_page() {
        $current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
        
        echo '<div class="wrap">';
        echo '<h1>綠界分期付款控制 - 設定精靈</h1>';
        
        // 進度條
        $this->render_progress_bar($current_step);
        
        switch ($current_step) {
            case 1:
                $this->render_step_1_system_check();
                break;
            case 2:
                $this->render_step_2_gateway_detection();
                break;
            case 3:
                $this->render_step_3_configuration();
                break;
            case 4:
                $this->render_step_4_completion();
                break;
            default:
                $this->render_step_1_system_check();
        }
        
        echo '</div>';
    }
    
    /**
     * 渲染進度條
     */
    private function render_progress_bar($current_step) {
        $steps = array(
            1 => '系統檢查',
            2 => '偵測付款方式',
            3 => '功能設定',
            4 => '完成設定'
        );
        
        echo '<div class="ecpay-setup-progress">';
        echo '<style>
        .ecpay-setup-progress {
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .setup-step {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            margin-right: 10px;
            position: relative;
        }
        .setup-step.active {
            background: #0073aa;
            color: white;
        }
        .setup-step.completed {
            background: #46b450;
            color: white;
        }
        .setup-step:last-child {
            margin-right: 0;
        }
        </style>';
        
        foreach ($steps as $step_num => $step_name) {
            $class = 'setup-step';
            if ($step_num < $current_step) {
                $class .= ' completed';
            } elseif ($step_num == $current_step) {
                $class .= ' active';
            }
            
            echo "<div class='{$class}'>";
            echo "<strong>步驟 {$step_num}</strong><br>{$step_name}";
            echo "</div>";
        }
        
        echo '</div>';
    }
    
    /**
     * 步驟 1：系統檢查
     */
    private function render_step_1_system_check() {
        echo '<div class="ecpay-setup-step">';
        echo '<h2>步驟 1：系統檢查</h2>';
        echo '<p>我們正在檢查您的系統是否滿足運行要求...</p>';
        
        $requirements = ECPay_System_Checker::check_system_requirements();
        
        echo '<table class="widefat">';
        echo '<thead><tr><th>項目</th><th>要求</th><th>目前狀態</th><th>結果</th></tr></thead>';
        echo '<tbody>';
        
        $all_passed = true;
        foreach ($requirements as $key => $requirement) {
            $status_icon = $requirement['status'] ? '✅' : '❌';
            $status_text = $requirement['status'] ? '通過' : '不符合';
            
            if (!$requirement['status']) {
                $all_passed = false;
            }
            
            echo '<tr>';
            echo '<td>' . $this->get_requirement_label($key) . '</td>';
            echo '<td>' . $requirement['required'] . '</td>';
            echo '<td>' . $requirement['current'] . '</td>';
            echo '<td>' . $status_icon . ' ' . $status_text . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        if ($all_passed) {
            echo '<div class="notice notice-success"><p><strong>太好了！</strong>您的系統滿足所有要求。</p></div>';
            echo '<p><a href="' . admin_url('index.php?page=ecpay-installment-setup&step=2') . '" class="button button-primary">繼續下一步</a></p>';
        } else {
            echo '<div class="notice notice-error"><p><strong>注意：</strong>您的系統不滿足部分要求，請先解決這些問題再繼續。</p></div>';
        }
        
        echo '</div>';
    }
    
    /**
     * 步驟 2：付款方式偵測
     */
    private function render_step_2_gateway_detection() {
        echo '<div class="ecpay-setup-step">';
        echo '<h2>步驟 2：偵測綠界付款方式</h2>';
        echo '<p>我們正在自動偵測您網站上的綠界付款方式...</p>';
        
        $gateways = ECPay_System_Checker::detect_ecpay_gateway_ids();
        $ecpay_plugins = ECPay_System_Checker::get_ecpay_plugins();
        
        // 顯示已安裝的綠界外掛
        echo '<h3>已安裝的綠界外掛</h3>';
        if (!empty($ecpay_plugins)) {
            echo '<table class="widefat">';
            echo '<thead><tr><th>外掛名稱</th><th>版本</th><th>狀態</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($ecpay_plugins as $plugin) {
                $status = $plugin['active'] ? '<span style="color: green;">啟用</span>' : '<span style="color: red;">停用</span>';
                echo '<tr>';
                echo '<td>' . $plugin['name'] . '</td>';
                echo '<td>' . $plugin['version'] . '</td>';
                echo '<td>' . $status . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<div class="notice notice-warning"><p>未偵測到綠界外掛，請確認已安裝並啟用綠界金流外掛。</p></div>';
        }
        
        // 顯示偵測到的付款方式
        echo '<h3>偵測到的付款方式</h3>';
        if (!empty($gateways)) {
            echo '<form method="post">';
            echo '<input type="hidden" name="ecpay_setup_step" value="detect_gateways">';
            
            echo '<table class="widefat">';
            echo '<thead><tr><th>選擇</th><th>付款方式 ID</th><th>名稱</th><th>類型</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($gateways as $gateway) {
                $type = $gateway['is_installment'] ? '<strong style="color: orange;">分期付款</strong>' : '一般付款';
                $checked = $gateway['is_installment'] ? 'checked' : '';
                
                echo '<tr>';
                echo '<td><input type="checkbox" name="selected_gateways[]" value="' . $gateway['id'] . '" ' . $checked . '></td>';
                echo '<td><code>' . $gateway['id'] . '</code></td>';
                echo '<td>' . $gateway['title'] . '</td>';
                echo '<td>' . $type . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            
            echo '<p class="description">請選擇需要控制的付款方式。系統已自動選擇偵測到的分期付款方式。</p>';
            echo '<p><input type="submit" class="button button-primary" value="確認選擇並繼續"></p>';
            echo '</form>';
        } else {
            echo '<div class="notice notice-warning"><p>未偵測到任何綠界付款方式，請檢查綠界外掛設定。</p></div>';
        }
        
        echo '<p><a href="' . admin_url('index.php?page=ecpay-installment-setup&step=1') . '" class="button">上一步</a></p>';
        echo '</div>';
    }
    
    /**
     * 步驟 3：功能設定
     */
    private function render_step_3_configuration() {
        echo '<div class="ecpay-setup-step">';
        echo '<h2>步驟 3：功能設定</h2>';
        echo '<p>請設定分期付款控制的相關選項...</p>';
        
        echo '<form method="post">';
        echo '<input type="hidden" name="ecpay_setup_step" value="configure_settings">';
        
        echo '<table class="form-table">';
        
        // 嚴格控制模式
        echo '<tr>';
        echo '<th scope="row">嚴格控制模式</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="strict_mode" value="1" checked> 只有當購物車中所有商品都允許分期時，才顯示分期付款選項</label>';
        echo '<p class="description">建議保持開啟，這是本外掛的核心功能。</p>';
        echo '</td>';
        echo '</tr>';
        
        // 最低購物車金額
        echo '<tr>';
        echo '<th scope="row">最低購物車金額</th>';
        echo '<td>';
        echo '<input type="number" name="min_cart_amount" value="0" min="0" step="100">';
        echo '<p class="description">設定使用分期付款的最低購物車金額（0 表示無限制）</p>';
        echo '</td>';
        echo '</tr>';
        
        // 分期控制模式
        echo '<tr>';
        echo '<th scope="row">分期控制模式</th>';
        echo '<td>';
        echo '<label><input type="radio" name="installment_control_mode" value="hide_all" checked> 隱藏所有分期選項（保留一般信用卡）</label><br>';
        echo '<label><input type="radio" name="installment_control_mode" value="selective"> 選擇性顯示分期期數</label>';
        echo '<p class="description">建議新手選擇「隱藏所有分期選項」，較為安全。</p>';
        echo '</td>';
        echo '</tr>';
        
        // 允許的分期期數
        echo '<tr>';
        echo '<th scope="row">允許的分期期數</th>';
        echo '<td>';
        $periods = array('3', '6', '12', '18', '24');
        foreach ($periods as $period) {
            echo '<label><input type="checkbox" name="allowed_installment_periods[]" value="' . $period . '"> ' . $period . ' 期</label><br>';
        }
        echo '<p class="description">只有在選擇「選擇性顯示分期期數」時才生效。</p>';
        echo '</td>';
        echo '</tr>';
        
        // 顯示提示訊息
        echo '<tr>';
        echo '<th scope="row">顯示提示訊息</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="show_cart_messages" value="1" checked> 在購物車頁面顯示分期可用性</label><br>';
        echo '<label><input type="checkbox" name="show_checkout_messages" value="1" checked> 在結帳頁面顯示分期可用性</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p><input type="submit" class="button button-primary" value="儲存設定並繼續"></p>';
        echo '</form>';
        
        echo '<p><a href="' . admin_url('index.php?page=ecpay-installment-setup&step=2') . '" class="button">上一步</a></p>';
        echo '</div>';
    }
    
    /**
     * 步驟 4：完成設定
     */
    private function render_step_4_completion() {
        echo '<div class="ecpay-setup-step">';
        echo '<h2>🎉 設定完成！</h2>';
        echo '<p>恭喜！綠界分期付款控制系統已成功設定完成。</p>';
        
        echo '<div class="notice notice-success">';
        echo '<h3>✅ 設定摘要</h3>';
        
        $options = get_option('ecpay_installment_control_options', array());
        
        echo '<ul>';
        echo '<li><strong>嚴格控制模式：</strong>' . ($options['strict_mode'] ? '啟用' : '停用') . '</li>';
        echo '<li><strong>控制的付款方式：</strong>' . count($options['gateway_ids']) . ' 個</li>';
        echo '<li><strong>最低購物車金額：</strong>' . ($options['min_cart_amount'] > 0 ? 'NT$ ' . number_format($options['min_cart_amount']) : '無限制') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<h3>🚀 接下來要做什麼？</h3>';
        echo '<ol>';
        echo '<li><strong>設定商品：</strong>前往商品編輯頁面，為需要支援分期的商品勾選「允許分期付款」</li>';
        echo '<li><strong>測試功能：</strong>在前台測試購物車和結帳流程，確認分期選項按預期顯示</li>';
        echo '<li><strong>監控狀態：</strong>定期查看外掛管理頁面，監控分期付款的使用狀況</li>';
        echo '</ol>';
        
        echo '<div class="ecpay-setup-actions">';
        echo '<p>';
        echo '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '" class="button button-primary">前往付款設定</a> ';
        echo '<a href="' . admin_url('edit.php?post_type=product') . '" class="button">管理商品</a> ';
        echo '<a href="' . admin_url('admin.php?page=ecpay-installment-control') . '" class="button">外掛設定</a>';
        echo '</p>';
        echo '</div>';
        
        echo '<form method="post">';
        echo '<input type="hidden" name="ecpay_setup_step" value="complete_setup">';
        echo '<p><input type="submit" class="button button-secondary" value="完成設定精靈"></p>';
        echo '</form>';
        
        echo '</div>';
    }
    
    /**
     * 處理付款方式偵測
     */
    private function handle_gateway_detection() {
        $selected_gateways = isset($_POST['selected_gateways']) ? array_map('sanitize_text_field', $_POST['selected_gateways']) : array();
        
        $options = get_option('ecpay_installment_control_options', array());
        $options['gateway_ids'] = $selected_gateways;
        $options['auto_detect_gateways'] = true;
        
        update_option('ecpay_installment_control_options', $options);
        
        wp_redirect(admin_url('index.php?page=ecpay-installment-setup&step=3'));
        exit;
    }
    
    /**
     * 處理設定配置
     */
    private function handle_settings_configuration() {
        $options = get_option('ecpay_installment_control_options', array());
        
        $options['strict_mode'] = isset($_POST['strict_mode']);
        $options['min_cart_amount'] = (float)$_POST['min_cart_amount'];
        $options['installment_control_mode'] = sanitize_text_field($_POST['installment_control_mode']);
        $options['allowed_installment_periods'] = isset($_POST['allowed_installment_periods']) ? array_map('sanitize_text_field', $_POST['allowed_installment_periods']) : array();
        $options['show_cart_messages'] = isset($_POST['show_cart_messages']);
        $options['show_checkout_messages'] = isset($_POST['show_checkout_messages']);
        
        update_option('ecpay_installment_control_options', $options);
        
        wp_redirect(admin_url('index.php?page=ecpay-installment-setup&step=4'));
        exit;
    }
    
    /**
     * 完成設定
     */
    private function complete_setup() {
        update_option('ecpay_installment_control_first_run', false);
        
        wp_redirect(admin_url('admin.php?page=ecpay-installment-control'));
        exit;
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
}
