<?php
/**
 * 分期付款控制核心類別
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECPay_Installment_Controller {
    
    private $options;
    
    public function __construct() {
        $this->options = get_option('ecpay_installment_control_options', array());
        $this->init_hooks();
    }
    
    /**
     * 初始化鉤子
     */
    private function init_hooks() {
        // 主要功能：控制付款方式顯示
        add_filter('woocommerce_available_payment_gateways', array($this, 'control_payment_gateways'), 10, 1);
        
        // 購物車和結帳頁面顯示訊息
        if ($this->get_option('show_cart_messages', true)) {
            add_action('woocommerce_cart_totals_after_order_total', array($this, 'display_cart_message'));
        }
        
        if ($this->get_option('show_checkout_messages', true)) {
            add_action('woocommerce_review_order_after_order_total', array($this, 'display_checkout_message'));
        }
        
        // 強制添加 CSS 和 JavaScript 控制（不管有沒有分期控制都加上）
        if (is_checkout()) {
            add_action('wp_head', array($this, 'add_installment_control_css'), 999);
            add_action('wp_footer', array($this, 'add_installment_filter_script'), 999);
        }
        
        // AJAX 處理
        add_action('wp_ajax_ecpay_check_installment_availability', array($this, 'ajax_check_installment_availability'));
        add_action('wp_ajax_nopriv_ecpay_check_installment_availability', array($this, 'ajax_check_installment_availability'));
    }
    
    /**
     * 控制付款方式顯示
     */
    public function control_payment_gateways($available_gateways) {
        // 只在結帳相關頁面執行
        if (!is_checkout() && !is_wc_endpoint_url('order-pay') && !wp_doing_ajax()) {
            return $available_gateways;
        }
        
        // 檢查購物車是否為空
        if (!WC()->cart || WC()->cart->is_empty()) {
            return $available_gateways;
        }
        
        $plugin = ECPay_Installment_Control_Plugin::get_instance();
        
        // 記錄檢查開始
        $plugin::log('gateway_check_start', 'info', array(
            'cart_total' => WC()->cart->get_total('edit'),
            'cart_items_count' => WC()->cart->get_cart_contents_count()
        ));
        
        // 檢查是否應該顯示分期付款
        $should_show_installment = $this->should_show_installment_payment();
        
        if (!$should_show_installment) {
            // 使用更精細的控制：只移除分期選項，保留一般信用卡
            $this->filter_installment_options($available_gateways);
            
            $plugin::log('installment_filtered', 'success', array(
                'cart_total' => WC()->cart->get_total('edit'),
                'reason' => 'installment_not_allowed'
            ));
        } else {
            $plugin::log('installment_allowed', 'success', array(
                'cart_total' => WC()->cart->get_total('edit'),
                'reason' => 'all_products_allow_installment'
            ));
        }
        
        return $available_gateways;
    }
    
    /**
     * 檢查是否應該顯示分期付款
     */
    private function should_show_installment_payment() {
        // 檢查最低金額要求
        $min_cart_amount = $this->get_option('min_cart_amount', 0);
        if ($min_cart_amount > 0 && WC()->cart->get_total('edit') < $min_cart_amount) {
            return false;
        }
        
        // 嚴格模式：所有商品都必須允許分期
        if ($this->get_option('strict_mode', true)) {
            return $this->check_all_products_allow_installment();
        }
        
        // 寬鬆模式：至少一個商品允許分期
        return $this->check_any_product_allows_installment();
    }
    
    /**
     * 檢查所有商品是否都允許分期
     */
    private function check_all_products_allow_installment() {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            
            if (!$this->product_allows_installment($product_id)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * 檢查是否有任何商品允許分期
     */
    private function check_any_product_allows_installment() {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            
            if ($this->product_allows_installment($product_id)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 檢查商品是否允許分期
     */
    private function product_allows_installment($product_id) {
        // 檢查自定義欄位
        $installment_enabled = get_post_meta($product_id, '_installment_enabled', true);
        if ($installment_enabled === 'yes') {
            return true;
        }
        
        // 檢查商品標籤
        $tag_name = $this->get_option('tag_name', 'installment-allowed');
        $product_tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
        
        if (in_array($tag_name, $product_tags)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 取得不支援分期的商品
     */
    private function get_non_installment_products() {
        $non_installment_products = array();
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            
            if (!$this->product_allows_installment($product_id)) {
                $product = wc_get_product($product_id);
                $non_installment_products[] = $product->get_name();
            }
        }
        
        return $non_installment_products;
    }
    
    /**
     * 在購物車頁面顯示訊息
     */
    public function display_cart_message() {
        $this->display_installment_message('cart');
    }
    
    /**
     * 在結帳頁面顯示訊息
     */
    public function display_checkout_message() {
        $this->display_installment_message('checkout');
    }
    
    /**
     * 顯示分期付款訊息
     */
    private function display_installment_message($context = 'cart') {
        $should_show = $this->should_show_installment_payment();
        $cart_total = WC()->cart->get_total('edit');
        $min_amount = $this->get_option('min_cart_amount', 0);
        
        echo '<tr class="ecpay-installment-info">';
        echo '<th>' . __('分期付款', 'ecpay-installment-control') . '</th>';
        echo '<td>';
        
        if ($should_show) {
            echo '<span style="color: #46b450; font-weight: bold;">✓ 此訂單支援分期付款</span>';
            
            // 顯示可用的分期期數或額外資訊
            $this->display_installment_options();
            
        } else {
            echo '<span style="color: #dc3232; font-weight: bold;">✗ 此訂單不支援分期付款</span><br>';
            
            // 顯示不支援的原因
            if ($min_amount > 0 && $cart_total < $min_amount) {
                echo '<small>原因：購物車金額未達最低要求 (NT$ ' . number_format($min_amount) . ')</small>';
            } else {
                $non_installment_products = $this->get_non_installment_products();
                if (!empty($non_installment_products)) {
                    echo '<small>原因：以下商品不支援分期付款<br>';
                    echo '• ' . implode('<br>• ', $non_installment_products) . '</small>';
                }
            }
        }
        
        echo '</td>';
        echo '</tr>';
        
        // 添加 CSS 樣式
        $this->add_message_styles();
    }
    
    /**
     * 顯示分期選項資訊
     */
    private function display_installment_options() {
        $cart_total = WC()->cart->get_total('edit');
        
        // 根據金額顯示可能的分期期數
        $installment_periods = $this->get_available_installment_periods($cart_total);
        
        if (!empty($installment_periods)) {
            echo '<br><small style="color: #666;">可選期數：' . implode('、', $installment_periods) . ' 期</small>';
        }
    }
    
    /**
     * 取得可用的分期期數
     */
    private function get_available_installment_periods($amount) {
        // 這裡可以根據實際的綠界分期規則來設定
        if ($amount >= 50000) {
            return array(3, 6, 12, 24);
        } elseif ($amount >= 20000) {
            return array(3, 6, 12);
        } elseif ($amount >= 5000) {
            return array(3, 6);
        }
        
        return array();
    }
    
    /**
     * 添加訊息樣式
     */
    private function add_message_styles() {
        static $styles_added = false;
        
        if (!$styles_added) {
            echo '<style>
            .ecpay-installment-info th {
                font-weight: bold;
                vertical-align: top;
                padding-top: 12px;
            }
            .ecpay-installment-info td {
                vertical-align: top;
                padding-top: 12px;
            }
            .ecpay-installment-info small {
                display: block;
                margin-top: 5px;
                line-height: 1.4;
            }
            </style>';
            $styles_added = true;
        }
    }
    
    /**
     * AJAX 檢查分期可用性
     */
    public function ajax_check_installment_availability() {
        check_ajax_referer('ecpay_installment_check', 'nonce');
        
        if (!WC()->cart || WC()->cart->is_empty()) {
            wp_send_json_error('購物車為空');
        }
        
        $is_available = $this->should_show_installment_payment();
        $message = '';
        
        if ($is_available) {
            $message = '此訂單支援分期付款';
        } else {
            $non_installment = $this->get_non_installment_products();
            if (!empty($non_installment)) {
                $message = '以下商品不支援分期：' . implode('、', $non_installment);
            } else {
                $message = '此訂單不符合分期付款條件';
            }
        }
        
        wp_send_json_success(array(
            'available' => $is_available,
            'message' => $message,
            'cart_total' => WC()->cart->get_total('edit')
        ));
    }
    
    /**
     * 精細控制分期付款選項
     * 只移除分期選項，保留一般信用卡
     */
    private function filter_installment_options($available_gateways) {
        // 直接添加 JavaScript 控制，不依賴 gateway title
        add_action('wp_footer', array($this, 'add_installment_filter_script'));
        
        // 也在 wp_head 添加一份，確保載入
        add_action('wp_head', array($this, 'add_installment_filter_script_head'));
    }
    
    /**
     * 檢查是否為綠界付款方式
     */
    private function is_ecpay_gateway($gateway_id, $gateway) {
        // 檢查 ID 是否包含 ecpay
        if (stripos($gateway_id, 'ecpay') !== false) {
            return true;
        }
        
        // 檢查類別名稱
        $class_name = strtolower(get_class($gateway));
        if (stripos($class_name, 'ecpay') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 修改付款方式標題（添加隱藏分期的標記）
     */
    public function modify_gateway_title($title, $gateway) {
        if ($this->is_ecpay_gateway($gateway->id, $gateway)) {
            // 添加隱藏分期的資料屬性
            $title .= '<span class="ecpay-installment-control" data-hide-installment="true" style="display:none;"></span>';
        }
        return $title;
    }
    
    /**
     * 添加前台 JavaScript 來隱藏分期選項
     */
    public function add_installment_filter_script() {
        if (!is_checkout()) return;
        
        $allowed_installments = $this->get_allowed_installment_periods();
        $installment_keywords = $this->get_installment_keywords();
        
        ?>
        <script type="text/javascript">
        var ecpayInstallmentControl = {
            allowedPeriods: <?php echo json_encode($allowed_installments); ?>,
            installmentKeywords: <?php echo json_encode($installment_keywords); ?>,
            isProcessing: false,
            lastProcessTime: 0,
            
            init: function() {
                var self = this;
                jQuery(document).ready(function($) {
                    // 簡化的事件綁定
                    self.bindEvents($);
                    
                    // 立即執行一次
                    self.filterInstallmentOptions($);
                });
            },
            
            bindEvents: function($) {
                var self = this;
                
                // WooCommerce 結帳更新事件（節流處理）
                $(document.body).on('updated_checkout', function() {
                    self.throttledFilter($, 'updated_checkout');
                });
                
                // 付款方式選擇變更
                $(document).on('change', 'input[name="payment_method"]', function() {
                    if ($(this).val() === 'ecpay') {
                        self.throttledFilter($, 'payment_method_change');
                    }
                });
            },
            
            // 節流函數，避免過度執行
            throttledFilter: function($, trigger) {
                var self = this;
                var now = Date.now();
                
                // 如果距離上次執行不到 1 秒，則忽略
                if (now - self.lastProcessTime < 1000) {
                    return;
                }
                
                if (self.isProcessing) return;
                
                self.isProcessing = true;
                self.lastProcessTime = now;
                
                setTimeout(function() {
                    self.filterInstallmentOptions($);
                    self.isProcessing = false;
                }, 100);
            },
            
            filterInstallmentOptions: function($) {
                var self = this;
                
                var $select = $('select[name="ecpay_choose_payment"]');
                if ($select.length === 0) {
                    return;
                }
                
                var filteredCount = 0;
                var debugMode = window.location.search.indexOf('ecpay_debug=1') !== -1;
                
                $select.find('option').each(function() {
                    var $option = $(this);
                    var optionText = $option.text();
                    var optionValue = $option.val();
                    var shouldHide = false;
                    
                    // 重置顯示狀態
                    $option.show();
                    
                    // 檢查是否為分期付款方式 (Credit_X 格式)
                    if (optionValue.match(/^Credit_\d+$/)) {
                        var period = optionValue.replace('Credit_', '');
                        
                        // 如果允許的分期期數為空，隱藏所有分期
                        if (self.allowedPeriods.length === 0) {
                            shouldHide = true;
                        } else if (self.allowedPeriods.indexOf(period) === -1) {
                            // 如果不在允許的分期期數中，則隱藏
                            shouldHide = true;
                        }
                    }
                    
                    // 檢查是否包含分期關鍵字（作為備用檢查）
                    if (!shouldHide && optionValue !== 'Credit') {
                        for (var i = 0; i < self.installmentKeywords.length; i++) {
                            if (optionText.indexOf(self.installmentKeywords[i]) !== -1) {
                                shouldHide = true;
                                break;
                            }
                        }
                    }
                    
                    if (shouldHide) {
                        $option.hide().prop('disabled', true);
                        filteredCount++;
                        
                        // 只在除錯模式下顯示詳細日誌
                        if (debugMode) {
                            console.log('ECPay: 隱藏分期選項', optionValue);
                        }
                    } else {
                        $option.prop('disabled', false);
                    }
                });
                
                // 確保一般信用卡選項可見
                $select.find('option[value="Credit"]').show().prop('disabled', false);
                
                // 如果當前選中的是被隱藏的選項，自動選擇一般信用卡
                var $selectedOption = $select.find('option:selected');
                if ($selectedOption.is(':hidden') || $selectedOption.prop('disabled')) {
                    $select.val('Credit');
                    if (debugMode) {
                        console.log('ECPay: 自動切換到一般信用卡');
                    }
                }
                
                // 只顯示摘要日誌
                if (filteredCount > 0) {
                    console.log('ECPay: 已隱藏 ' + filteredCount + ' 個分期選項');
                }
            }
        };
        
        // 啟動分期控制
        ecpayInstallmentControl.init();
        </script>
        
        <style type="text/css">
        /* 強制隱藏分期選項 */
        select[name="ecpay_choose_payment"] option[style*="display: none"] {
            display: none !important;
        }
        select[name="ecpay_choose_payment"] option[disabled] {
            display: none !important;
        }
        select[name="ecpay_choose_payment"] option[value^="Credit_"] {
            display: none !important;
        }
        /* 確保一般信用卡可見 */
        select[name="ecpay_choose_payment"] option[value="Credit"] {
            display: block !important;
        }
        </style>
        <?php
    }
    
    /**
     * 在 head 中添加分期控制腳本
     */
    public function add_installment_filter_script_head() {
        if (!is_checkout()) return;
        
        $allowed_installments = $this->get_allowed_installment_periods();
        $installment_keywords = $this->get_installment_keywords();
        
        ?>
        <script type="text/javascript">
        // 早期載入的分期控制
        window.ecpayInstallmentSettings = {
            allowedPeriods: <?php echo json_encode($allowed_installments); ?>,
            keywords: <?php echo json_encode($installment_keywords); ?>,
            shouldHideInstallments: <?php echo json_encode(!$this->should_show_installment_payment()); ?>
        };
        
        // 立即執行的過濾函數
        function ecpayFilterInstallments() {
            var selects = document.querySelectorAll('select[name="ecpay_choose_payment"]');
            if (selects.length === 0) {
                return;
            }
            
            console.log('ECPay Head: 開始過濾，設定:', window.ecpayInstallmentSettings);
            
            selects.forEach(function(select) {
                var options = select.querySelectorAll('option');
                var hiddenCount = 0;
                
                options.forEach(function(option) {
                    var value = option.value;
                    var text = option.textContent || option.innerText;
                    var shouldHide = false;
                    
                    console.log('ECPay Head: 檢查選項', value, text);
                    
                    // 重置顯示
                    option.style.display = '';
                    option.disabled = false;
                    
                    // 檢查是否應該隱藏分期
                    if (window.ecpayInstallmentSettings.shouldHideInstallments) {
                        // 檢查是否為分期選項
                        if (value.match(/^Credit_\d+$/)) {
                            var period = value.replace('Credit_', '');
                            
                            // 如果允許的分期期數為空，隱藏所有分期
                            if (window.ecpayInstallmentSettings.allowedPeriods.length === 0) {
                                shouldHide = true;
                                console.log('ECPay Head: 隱藏所有分期，allowedPeriods 為空');
                            } else if (window.ecpayInstallmentSettings.allowedPeriods.indexOf(period) === -1) {
                                shouldHide = true;
                                console.log('ECPay Head: 隱藏分期，不在允許清單', period);
                            }
                        }
                        
                        // 檢查是否包含分期關鍵字（但保留一般信用卡）
                        if (!shouldHide && value !== 'Credit') {
                            window.ecpayInstallmentSettings.keywords.forEach(function(keyword) {
                                if (text.indexOf(keyword) !== -1) {
                                    shouldHide = true;
                                    console.log('ECPay Head: 隱藏關鍵字匹配選項', value, keyword);
                                }
                            });
                        }
                    }
                    
                    if (shouldHide) {
                        option.style.display = 'none';
                        option.disabled = true;
                        hiddenCount++;
                        console.log('ECPay Head: ✓ 已隱藏', value, text);
                    }
                });
                
                // 確保一般信用卡可見
                var creditOption = select.querySelector('option[value="Credit"]');
                if (creditOption) {
                    creditOption.style.display = '';
                    creditOption.disabled = false;
                }
                
                console.log('ECPay Head: 過濾完成，隱藏了', hiddenCount, '個選項');
            });
        }
        
        // DOM 變更觀察器
        if (window.MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) {
                                if (node.querySelector && node.querySelector('select[name="ecpay_choose_payment"]')) {
                                    console.log('ECPay Head: DOM Observer 偵測到分期選項');
                                    setTimeout(ecpayFilterInstallments, 10);
                                }
                            }
                        });
                    }
                });
            });
            
            document.addEventListener('DOMContentLoaded', function() {
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });
        }
        
        // 定期檢查
        var checkInterval = setInterval(function() {
            if (document.querySelector('select[name="ecpay_choose_payment"]')) {
                console.log('ECPay Head: 定期檢查找到分期選項');
                ecpayFilterInstallments();
            }
        }, 1000);
        
        // 30 秒後停止定期檢查
        setTimeout(function() {
            clearInterval(checkInterval);
        }, 30000);
        </script>
        <?php
    }
    
    /**
     * 取得允許的分期期數
     */
    private function get_allowed_installment_periods() {
        $control_mode = $this->get_option('installment_control_mode', 'hide_all');
        
        // 如果商品不允許分期，直接返回空陣列
        if (!$this->should_show_installment_payment()) {
            return array();
        }
        
        // 根據控制模式決定分期期數
        switch ($control_mode) {
            case 'selective':
                // 選擇性模式：返回設定中允許的期數
                return $this->get_option('allowed_installment_periods', array());
                
            case 'hide_all':
            default:
                // 隱藏所有分期模式：返回空陣列
                return array();
        }
    }
    
    /**
     * 取得分期關鍵字（用於文字匹配）
     */
    private function get_installment_keywords() {
        return array(
            '分期', 
            '期', 
            'Installment', 
            'installment',
            '3期', '6期', '12期', '18期', '24期',
            '三期', '六期'
        );
    }
    
    /**
     * 添加分期控制 CSS
     */
    public function add_installment_control_css() {
        if (!is_checkout()) return;
        
        $should_hide = !$this->should_show_installment_payment();
        
        ?>
        <style type="text/css" id="ecpay-installment-control-css">
        <?php if ($should_hide): ?>
        /* 強制隱藏所有分期選項 */
        select[name="ecpay_choose_payment"] option[value^="Credit_"] {
            display: none !important;
        }
        
        /* 確保一般信用卡可見 */
        select[name="ecpay_choose_payment"] option[value="Credit"] {
            display: block !important;
        }
        <?php endif; ?>
        
        /* 通用隱藏規則 */
        select[name="ecpay_choose_payment"] option[style*="display: none"] {
            display: none !important;
        }
        select[name="ecpay_choose_payment"] option[disabled] {
            display: none !important;
        }
        </style>
        
        <script type="text/javascript">
        // 優化的分期控制 - 只執行必要的操作
        window.ecpayInstallmentOptimized = {
            hasProcessed: false,
            styleAdded: false,
            
            init: function() {
                <?php if ($should_hide): ?>
                this.addGlobalStyle();
                this.processExistingSelects();
                this.setupObserver();
                <?php endif; ?>
            },
            
            addGlobalStyle: function() {
                if (this.styleAdded) return;
                
                var style = document.createElement('style');
                style.id = 'ecpay-installment-hide';
                style.textContent = 'select[name="ecpay_choose_payment"] option[value^="Credit_"] { display: none !important; }';
                document.head.appendChild(style);
                this.styleAdded = true;
            },
            
            processExistingSelects: function() {
                var selects = document.querySelectorAll('select[name="ecpay_choose_payment"]');
                if (selects.length === 0) return;
                
                var hiddenCount = 0;
                selects.forEach(function(select) {
                    var options = select.querySelectorAll('option[value^="Credit_"]');
                    options.forEach(function(option) {
                        option.style.display = 'none';
                        option.disabled = true;
                        hiddenCount++;
                    });
                });
                
                if (hiddenCount > 0) {
                    console.log('ECPay: 已隱藏 ' + hiddenCount + ' 個分期選項');
                }
            },
            
            setupObserver: function() {
                var self = this;
                
                // 只在需要時使用 MutationObserver
                if (window.MutationObserver) {
                    var observer = new MutationObserver(function(mutations) {
                        var shouldProcess = false;
                        
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'childList') {
                                mutation.addedNodes.forEach(function(node) {
                                    if (node.nodeType === 1) {
                                        if (node.querySelector && node.querySelector('select[name="ecpay_choose_payment"]')) {
                                            shouldProcess = true;
                                        }
                                    }
                                });
                            }
                        });
                        
                        if (shouldProcess) {
                            self.processExistingSelects();
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
                
                // 輕量級的定期檢查（減少頻率）
                var checkCount = 0;
                var maxChecks = 10; // 最多檢查 10 次
                
                var lightCheck = setInterval(function() {
                    checkCount++;
                    
                    var selects = document.querySelectorAll('select[name="ecpay_choose_payment"]');
                    if (selects.length > 0) {
                        self.processExistingSelects();
                    }
                    
                    // 達到最大檢查次數後停止
                    if (checkCount >= maxChecks) {
                        clearInterval(lightCheck);
                    }
                }, 2000); // 改為每 2 秒檢查一次
            }
        };
        
        // 初始化
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                window.ecpayInstallmentOptimized.init();
            });
        } else {
            window.ecpayInstallmentOptimized.init();
        }
        </script>
        <?php
    }
    
    /**
     * 取得選項值
     */
    private function get_option($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
}
