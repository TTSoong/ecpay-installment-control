<?php
/**
 * 前台功能類別
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECPay_Installment_Frontend {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * 初始化鉤子
     */
    private function init_hooks() {
        // 添加前台 JavaScript
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // 購物車更新時檢查分期可用性
        add_action('woocommerce_cart_updated', array($this, 'check_installment_on_cart_update'));
    }
    
    /**
     * 載入前台腳本
     */
    public function enqueue_frontend_scripts() {
        if (is_cart() || is_checkout()) {
            wp_enqueue_script('jquery');
            wp_add_inline_script('jquery', $this->get_frontend_js());
        }
    }
    
    /**
     * 購物車更新時檢查
     */
    public function check_installment_on_cart_update() {
        // 這裡可以添加購物車更新時的額外邏輯
    }
    
    /**
     * 前台 JavaScript
     */
    private function get_frontend_js() {
        return "
        jQuery(document).ready(function($) {
            // 監聽購物車變化
            $(document.body).on('updated_cart_totals updated_checkout', function() {
                // 可以在這裡添加動態更新分期資訊的邏輯
            });
        });
        ";
    }
}
