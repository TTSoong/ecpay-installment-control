<?php
/**
 * 綠界分期付款控制 - 除錯測試頁面
 * 使用方式：訪問 http://yoursite.com/wp-content/plugins/ecpay-installment-control/test-debug.php
 */

// 載入 WordPress
require_once('../../../wp-load.php');

// 檢查權限
if (!current_user_can('manage_woocommerce')) {
    die('權限不足');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>綠界分期付款控制 - 除錯測試</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; }
        .debug-box { margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa; }
    </style>
</head>
<body>
    <h1>綠界分期付款控制 - 除錯測試</h1>
    
    <div class="test-section">
        <h2>1. 外掛狀態檢查</h2>
        <?php
        $plugin_active = is_plugin_active('ecpay-installment-control/ecpay-installment-control.php');
        echo '<p class="' . ($plugin_active ? 'success' : 'error') . '">';
        echo '外掛狀態：' . ($plugin_active ? '已啟用' : '未啟用');
        echo '</p>';
        
        $options = get_option('ecpay_installment_control_options', array());
        echo '<div class="debug-box">';
        echo '<strong>目前設定：</strong><br>';
        echo '<pre>' . print_r($options, true) . '</pre>';
        echo '</div>';
        ?>
    </div>
    
    <div class="test-section">
        <h2>2. 綠界外掛檢查</h2>
        <?php
        if (class_exists('ECPay_System_Checker')) {
            $ecpay_plugins = ECPay_System_Checker::get_ecpay_plugins();
            $gateways = ECPay_System_Checker::detect_ecpay_gateway_ids();
            
            echo '<div class="debug-box">';
            echo '<strong>綠界外掛：</strong><br>';
            if (!empty($ecpay_plugins)) {
                foreach ($ecpay_plugins as $plugin) {
                    echo '- ' . $plugin['name'] . ' (v' . $plugin['version'] . ') - ' . ($plugin['active'] ? '啟用' : '停用') . '<br>';
                }
            } else {
                echo '<span class="error">未找到綠界外掛</span>';
            }
            echo '</div>';
            
            echo '<div class="debug-box">';
            echo '<strong>偵測到的付款方式：</strong><br>';
            if (!empty($gateways)) {
                foreach ($gateways as $gateway) {
                    echo '- ' . $gateway['id'] . ': ' . $gateway['title'] . ' (' . ($gateway['is_installment'] ? '分期' : '一般') . ')<br>';
                }
            } else {
                echo '<span class="error">未找到綠界付款方式</span>';
            }
            echo '</div>';
        } else {
            echo '<p class="error">ECPay_System_Checker 類別不存在</p>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>3. 購物車測試</h2>
        <?php
        if (WC()->cart && !WC()->cart->is_empty()) {
            echo '<p class="success">購物車中有商品</p>';
            
            if (class_exists('ECPay_Installment_Controller')) {
                $controller = new ECPay_Installment_Controller();
                $reflection = new ReflectionClass($controller);
                
                // 使用反射來測試私有方法
                try {
                    $method = $reflection->getMethod('should_show_installment_payment');
                    $method->setAccessible(true);
                    $should_show = $method->invoke($controller);
                    
                    echo '<div class="debug-box">';
                    echo '<strong>分期付款檢查結果：</strong><br>';
                    echo '應該顯示分期付款：' . ($should_show ? '是' : '否') . '<br>';
                    echo '</div>';
                    
                    // 檢查購物車中的商品
                    echo '<div class="debug-box">';
                    echo '<strong>購物車商品分期狀態：</strong><br>';
                    foreach (WC()->cart->get_cart() as $cart_item) {
                        $product_id = $cart_item['product_id'];
                        $product = wc_get_product($product_id);
                        $installment_enabled = get_post_meta($product_id, '_installment_enabled', true);
                        
                        echo '- ' . $product->get_name() . ' (ID: ' . $product_id . '): ';
                        echo ($installment_enabled === 'yes' ? '<span class="success">支援分期</span>' : '<span class="error">不支援分期</span>');
                        echo '<br>';
                    }
                    echo '</div>';
                    
                } catch (Exception $e) {
                    echo '<p class="error">測試失敗：' . $e->getMessage() . '</p>';
                }
            } else {
                echo '<p class="error">ECPay_Installment_Controller 類別不存在</p>';
            }
        } else {
            echo '<p class="info">購物車為空，請先添加商品到購物車</p>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>4. JavaScript 測試</h2>
        <p>打開瀏覽器開發者工具的控制台（F12），查看是否有以下日誌訊息：</p>
        <ul>
            <li><code>ECPay Head: 定期檢查找到分期選項</code></li>
            <li><code>ECPay Head: 隱藏分期選項 Credit_X</code></li>
            <li><code>ECPay: updated_checkout 觸發</code></li>
            <li><code>ECPay: 開始過濾分期選項</code></li>
        </ul>
        
        <div class="debug-box">
            <strong>手動測試步驟：</strong><br>
            1. 確保購物車中有不支援分期的商品<br>
            2. 前往結帳頁面<br>
            3. 選擇綠界支付<br>
            4. 檢查付款方式下拉選單是否隱藏了分期選項<br>
            5. 檢查控制台是否有除錯訊息
        </div>
    </div>
    
    <div class="test-section">
        <h2>5. 快速操作</h2>
        <p>
            <a href="<?php echo admin_url('admin.php?page=ecpay-installment-control'); ?>" class="button">前往外掛設定</a>
            <a href="<?php echo wc_get_checkout_url(); ?>" class="button">前往結帳頁面</a>
            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button">管理商品</a>
        </p>
    </div>
    
    <script>
    // 即時檢查分期選項
    function checkInstallmentOptions() {
        var select = document.querySelector('select[name="ecpay_choose_payment"]');
        if (select) {
            console.log('找到綠界付款選項:', select);
            var options = select.querySelectorAll('option');
            options.forEach(function(option) {
                console.log('選項:', option.value, option.textContent, '顯示:', option.style.display !== 'none');
            });
        } else {
            console.log('未找到綠界付款選項');
        }
    }
    
    // 每 2 秒檢查一次
    setInterval(checkInstallmentOptions, 2000);
    
    console.log('除錯測試頁面已載入，開始監控分期選項...');
    </script>
</body>
</html>
