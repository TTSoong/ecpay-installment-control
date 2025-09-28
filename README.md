# WooCommerce 綠界分期付款控制

[![GitHub](https://img.shields.io/badge/GitHub-TTSoong-blue?logo=github)](https://github.com/TTSoong/ecpay-installment-control)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-3.0%2B-purple.svg)](https://woocommerce.com/)

> 專為 WordPress + WooCommerce 設計的綠界金流分期付款精細控制系統

## 🎯 專案概述

這是一個專為 WooCommerce 綠界金流設計的分期付款控制外掛。**核心功能**是實現精細控制：只隱藏分期選項而保留一般信用卡付款，確保只有當購物車中所有商品都允許分期時才顯示分期付款選項。

### 💡 解決的問題

- ❌ **舊方式**：粗暴隱藏整個綠界支付
- ✅ **新方式**：精確隱藏分期選項，保留一般信用卡

## ✨ 核心特色

- **🎯 精細控制**：只隱藏分期選項，保留一般信用卡付款
- **🔒 嚴格控制**：購物車中所有商品都必須有分期標籤才顯示分期選項
- **🎛️ 智能設定精靈**：首次啟用自動引導設定
- **⚙️ 雙重控制模式**：隱藏所有分期 / 選擇性顯示分期期數
- **📦 批次操作**：可批次為商品添加或移除分期標籤
- **🚀 高效能**：優化的 JavaScript，減少客戶端資源消耗
- **🔍 完整除錯**：內建除錯工具和日誌系統

## 📋 系統需求

- WordPress 5.0+
- WooCommerce 3.0+
- 綠界金流外掛（ECPay）
- PHP 7.0+

## 🚀 快速開始

### 1. 下載安裝

```bash
# 方法一：克隆整個專案
git clone https://github.com/TTSoong/ecpay-installment-control.git
cd ecpay-installment-control
cp -r ecpay-installment-control/ /path/to/wordpress/wp-content/plugins/

# 方法二：直接下載外掛資料夾
# 下載 ZIP 後，將 ecpay-installment-control/ 資料夾上傳到：
/wp-content/plugins/ecpay-installment-control/
```

### 2. 啟用外掛

1. 前往 WordPress 後台「外掛」頁面
2. 找到「WooCommerce 綠界分期付款控制」
3. 點擊「啟用」
4. 跟隨自動顯示的設定精靈

### 3. 使用設定精靈

首次啟用後，系統會自動顯示設定精靈：

1. **系統檢查**：檢查 WordPress、WooCommerce、綠界外掛
2. **偵測付款方式**：自動識別綠界分期付款選項
3. **功能設定**：選擇控制模式和分期期數
4. **完成設定**：開始使用分期控制功能

## 🎛️ 控制模式

### 模式 1：隱藏所有分期選項（預設）

```
綠界支付選項：
✅ 信用卡(一次付清)
❌ 信用卡(三期)     ← 隱藏
❌ 信用卡(六期)     ← 隱藏
❌ 信用卡(十二期)   ← 隱藏
✅ WebATM
✅ ATM
```

### 模式 2：選擇性顯示分期期數

```
假設只允許 3 期和 6 期：
✅ 信用卡(一次付清)
✅ 信用卡(三期)     ← 允許
✅ 信用卡(六期)     ← 允許
❌ 信用卡(十二期)   ← 隱藏
❌ 信用卡(十八期)   ← 隱藏
```

## 📁 檔案結構

```
.
├── README.md                                  # 專案說明文件
└── ecpay-installment-control/                 # WordPress 外掛資料夾
    ├── ecpay-installment-control.php          # 主外掛檔案
    ├── includes/
    │   ├── class-installment-controller.php   # 核心控制邏輯
    │   ├── class-product-manager.php          # 商品管理
    │   └── class-system-checker.php           # 系統檢測
    ├── admin/
    │   ├── class-admin.php                    # 管理介面
    │   └── class-setup-wizard.php             # 設定精靈
    ├── public/
    │   └── class-frontend.php                 # 前台功能
    ├── test-debug.php                         # 系統除錯工具
    └── backup-essential-only.php              # 安裝前備份腳本
```

## 🔧 使用方式

### 設定商品分期權限

**方法 1：商品編輯頁面**
1. 前往「商品」→「編輯商品」
2. 在「一般」標籤頁勾選「允許分期付款」
3. 儲存商品

**方法 2：批次操作**
1. 前往 WooCommerce → 分期付款控制
2. 使用批次啟用/停用功能

### 管理外掛設定

在外掛頁面可以看到兩個按鈕：
- **設定**：進入管理介面
- **設定精靈**：重新執行初始設定

## 🔄 外掛更新

### 更新方式
1. **直接覆蓋**：下載新版本後直接覆蓋舊檔案即可
2. **保留設定**：所有設定和商品分期標籤都會自動保留
3. **無需重新設定**：更新後會自動保留您的所有配置

### 重新執行設定精靈
如果需要重新設定外掛：
1. 前往「外掛」頁面
2. 找到「WooCommerce 綠界分期付款控制」
3. 點擊「設定精靈」按鈕
4. 重新跟隨設定流程

## 🛠️ 內建工具

### 📋 系統除錯工具 (`test-debug.php`)

**用途**：診斷系統狀態和配置問題

**使用方法**：
```
http://yoursite.com/wp-content/plugins/ecpay-installment-control/test-debug.php
```

**功能**：
- ✅ 檢查外掛啟用狀態
- ✅ 偵測綠界外掛和付款方式
- ✅ 驗證購物車商品分期設定
- ✅ 顯示 JavaScript 日誌狀態
- ✅ 提供快速操作連結

**注意**：需要管理員權限才能訪問，使用完畢可刪除此檔案。

### 💾 安裝前備份腳本 (`backup-essential-only.php`)

**用途**：安裝外掛前創建輕量化網站備份

**使用方法**：
```bash
# 從外掛資料夾複製到 WordPress 根目錄後執行
cp ecpay-installment-control/backup-essential-only.php ./
php backup-essential-only.php
```

**備份範圍**：
- ✅ 核心設定檔案 (wp-config.php, .htaccess)
- ✅ 主題 PHP/CSS/JS 檔案 (排除圖片)
- ✅ 重要外掛檔案 (WooCommerce, ECPay 相關)
- ✅ 完整資料庫 (排除大型日誌表)

**排除內容**：
- ❌ 圖片檔案 (jpg, png, gif, webp 等)
- ❌ 影片檔案 (mp4, mov, avi 等)
- ❌ 開發檔案 (node_modules, .git 等)

**備份大小**：相比完整備份可減少 70-90% 的檔案大小

### 🔍 除錯功能

**詳細日誌模式**：
在結帳頁面網址後加上 `?ecpay_debug=1` 啟用詳細日誌。

**JavaScript 控制台**：
按 F12 查看控制台日誌：
```
ECPay: 已隱藏 X 個分期選項
```

## 🚨 疑難排解

### Q: 分期選項仍然顯示？
**A:** 
1. 檢查商品是否有分期標籤
2. 確認控制模式設定
3. 啟用除錯模式查看日誌

### Q: 商品標籤設定無效？
**A:** 清除所有快取，確認商品正確儲存。

### Q: 外掛更新後設定不見了？
**A:** 外掛有自動保留設定功能，如有問題請檢查資料庫中的 `ecpay_installment_control_options` 選項。

## 🛠️ 開發者資訊

### 自定義分期規則範例

```php
// 價格門檻控制
if ($product->get_price() < 5000) {
    return false; // 價格低於 5000 不允許分期
}

// 商品分類控制
$categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
if (in_array('electronics', $categories)) {
    return true; // 電子產品允許分期
}

// 會員等級控制
if (user_can(get_current_user_id(), 'vip_member')) {
    return true; // VIP 會員允許分期
}
```

### 鉤子和過濾器

```php
// 自定義分期檢查邏輯
add_filter('ecpay_installment_product_check', function($allowed, $product_id) {
    // 您的自定義邏輯
    return $allowed;
}, 10, 2);
```

## 📈 版本歷史

- **v1.0.0** (2025-09-28)
  - 初始版本發布
  - 精細控制分期選項
  - 智能設定精靈
  - 效能優化

## 🤝 貢獻

歡迎提交 Issue 和 Pull Request！

1. Fork 專案
2. 創建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交變更 (`git commit -m 'Add some AmazingFeature'`)
4. Push 到分支 (`git push origin feature/AmazingFeature`)
5. 開啟 Pull Request

## 📄 授權

此專案採用 [MIT 授權](LICENSE)。

## 👨‍💻 作者

**TTSoong** - [GitHub Profile](https://github.com/TTSoong)

## 🔗 相關連結

- [專案首頁](https://github.com/TTSoong/ecpay-installment-control)
- [Issue 回報](https://github.com/TTSoong/ecpay-installment-control/issues)
- [作者 GitHub](https://github.com/TTSoong)

---

**讓分期付款控制變得簡單而精確！** 🎉
