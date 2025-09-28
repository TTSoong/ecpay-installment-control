<?php
/**
 * 商品管理類別
 * 處理商品分期標籤設定
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECPay_Product_Manager {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * 初始化鉤子
     */
    private function init_hooks() {
        // 商品編輯頁面
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_installment_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_installment_field'));
        
        // 商品列表頁面
        add_filter('manage_product_posts_columns', array($this, 'add_installment_column'));
        add_action('manage_product_posts_custom_column', array($this, 'display_installment_column'), 10, 2);
        
        // 快速編輯
        add_action('woocommerce_product_quick_edit_end', array($this, 'add_quick_edit_fields'));
        add_action('woocommerce_product_quick_edit_save', array($this, 'save_quick_edit_fields'));
        
        // 批次操作
        add_filter('bulk_actions-edit-product', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-product', array($this, 'handle_bulk_actions'), 10, 3);
        
        // AJAX 處理
        add_action('wp_ajax_ecpay_toggle_product_installment', array($this, 'ajax_toggle_installment'));
    }
    
    /**
     * 在商品編輯頁面添加分期欄位
     */
    public function add_installment_field() {
        global $post;
        
        $installment_enabled = get_post_meta($post->ID, '_installment_enabled', true);
        
        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox(array(
            'id' => '_installment_enabled',
            'label' => __('允許分期付款', 'ecpay-installment-control'),
            'description' => __('勾選此選項允許此商品使用分期付款', 'ecpay-installment-control'),
            'desc_tip' => true,
            'value' => $installment_enabled
        ));
        
        // 顯示額外資訊
        echo '<p class="form-field">';
        echo '<label>&nbsp;</label>';
        echo '<span class="description">';
        
        if ($installment_enabled === 'yes') {
            echo '<span style="color: #46b450;">✓ 此商品支援分期付款</span>';
        } else {
            echo '<span style="color: #dc3232;">✗ 此商品不支援分期付款</span>';
        }
        
        echo '<br><small>此設定會影響購物車中包含此商品時是否顯示分期付款選項。</small>';
        echo '</span>';
        echo '</p>';
        
        echo '</div>';
    }
    
    /**
     * 儲存分期欄位
     */
    public function save_installment_field($post_id) {
        $installment_enabled = isset($_POST['_installment_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_installment_enabled', $installment_enabled);
        
        // 同時更新商品標籤
        $options = get_option('ecpay_installment_control_options', array());
        $tag_name = isset($options['tag_name']) ? $options['tag_name'] : 'installment-allowed';
        
        if ($installment_enabled === 'yes') {
            wp_set_post_terms($post_id, $tag_name, 'product_tag', true);
        } else {
            wp_remove_object_terms($post_id, $tag_name, 'product_tag');
        }
        
        // 記錄操作
        ECPay_Installment_Control_Plugin::log('product_installment_updated', 'success', array(
            'product_id' => $post_id,
            'enabled' => $installment_enabled
        ));
    }
    
    /**
     * 在商品列表添加分期欄位
     */
    public function add_installment_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // 在價格欄位後面插入分期欄位
            if ($key === 'price') {
                $new_columns['installment'] = __('分期付款', 'ecpay-installment-control');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * 顯示分期欄位內容
     */
    public function display_installment_column($column, $post_id) {
        if ($column === 'installment') {
            $installment_enabled = get_post_meta($post_id, '_installment_enabled', true);
            
            if ($installment_enabled === 'yes') {
                echo '<span style="color: #46b450; font-weight: bold;" title="支援分期付款">✓</span>';
            } else {
                echo '<span style="color: #dc3232;" title="不支援分期付款">✗</span>';
            }
            
            // 添加快速切換按鈕
            echo '<br><a href="#" class="ecpay-toggle-installment" data-product-id="' . $post_id . '" data-current="' . $installment_enabled . '">';
            echo ($installment_enabled === 'yes') ? '停用' : '啟用';
            echo '</a>';
        }
    }
    
    /**
     * 添加快速編輯欄位
     */
    public function add_quick_edit_fields() {
        ?>
        <br class="clear" />
        <div class="inline-edit-group">
            <label class="alignleft">
                <span class="title"><?php _e('分期付款', 'ecpay-installment-control'); ?></span>
                <span class="input-text-wrap">
                    <select name="_installment_enabled">
                        <option value=""><?php _e('— 不變更 —', 'ecpay-installment-control'); ?></option>
                        <option value="yes"><?php _e('允許分期', 'ecpay-installment-control'); ?></option>
                        <option value="no"><?php _e('不允許分期', 'ecpay-installment-control'); ?></option>
                    </select>
                </span>
            </label>
        </div>
        <?php
    }
    
    /**
     * 儲存快速編輯
     */
    public function save_quick_edit_fields($product) {
        if (isset($_REQUEST['_installment_enabled']) && $_REQUEST['_installment_enabled'] !== '') {
            $installment_enabled = sanitize_text_field($_REQUEST['_installment_enabled']);
            $this->update_product_installment($product->get_id(), $installment_enabled);
        }
    }
    
    /**
     * 添加批次操作
     */
    public function add_bulk_actions($actions) {
        $actions['enable_installment'] = __('啟用分期付款', 'ecpay-installment-control');
        $actions['disable_installment'] = __('停用分期付款', 'ecpay-installment-control');
        return $actions;
    }
    
    /**
     * 處理批次操作
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action === 'enable_installment') {
            foreach ($post_ids as $post_id) {
                $this->update_product_installment($post_id, 'yes');
            }
            
            $redirect_to = add_query_arg('installment_enabled', count($post_ids), $redirect_to);
            
        } elseif ($action === 'disable_installment') {
            foreach ($post_ids as $post_id) {
                $this->update_product_installment($post_id, 'no');
            }
            
            $redirect_to = add_query_arg('installment_disabled', count($post_ids), $redirect_to);
        }
        
        return $redirect_to;
    }
    
    /**
     * AJAX 切換商品分期狀態
     */
    public function ajax_toggle_installment() {
        check_ajax_referer('ecpay_toggle_installment', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('權限不足');
        }
        
        $product_id = intval($_POST['product_id']);
        $current_status = sanitize_text_field($_POST['current_status']);
        
        $new_status = ($current_status === 'yes') ? 'no' : 'yes';
        
        $this->update_product_installment($product_id, $new_status);
        
        wp_send_json_success(array(
            'new_status' => $new_status,
            'message' => ($new_status === 'yes') ? '已啟用分期付款' : '已停用分期付款'
        ));
    }
    
    /**
     * 更新商品分期設定
     */
    private function update_product_installment($product_id, $enabled) {
        update_post_meta($product_id, '_installment_enabled', $enabled);
        
        // 更新商品標籤
        $options = get_option('ecpay_installment_control_options', array());
        $tag_name = isset($options['tag_name']) ? $options['tag_name'] : 'installment-allowed';
        
        if ($enabled === 'yes') {
            wp_set_post_terms($product_id, $tag_name, 'product_tag', true);
        } else {
            wp_remove_object_terms($product_id, $tag_name, 'product_tag');
        }
        
        // 記錄操作
        ECPay_Installment_Control_Plugin::log('product_installment_bulk_updated', 'success', array(
            'product_id' => $product_id,
            'enabled' => $enabled
        ));
    }
    
    /**
     * 取得支援分期的商品統計
     */
    public static function get_installment_products_stats() {
        global $wpdb;
        
        $total_products = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish'
        ");
        
        $installment_products = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_installment_enabled'
            AND pm.meta_value = 'yes'
        ");
        
        return array(
            'total' => intval($total_products),
            'installment_enabled' => intval($installment_products),
            'installment_disabled' => intval($total_products) - intval($installment_products),
            'percentage' => $total_products > 0 ? round(($installment_products / $total_products) * 100, 1) : 0
        );
    }
    
    /**
     * 取得分期商品列表
     */
    public static function get_installment_products($limit = 20, $offset = 0) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_installment_enabled',
                    'value' => 'yes'
                )
            ),
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $products = get_posts($args);
        $result = array();
        
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            
            $result[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'formatted_price' => wc_price($product->get_price()),
                'status' => $product->get_status(),
                'date_created' => $product->get_date_created()->date('Y-m-d H:i:s')
            );
        }
        
        return $result;
    }
}
