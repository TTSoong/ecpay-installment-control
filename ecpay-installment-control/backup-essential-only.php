<?php
/**
 * WordPress 網站精簡備份腳本
 * 只備份必要檔案，排除圖片和媒體檔案
 * 專為 WooCommerce 綠界分期付款控制系統安裝前使用
 */

date_default_timezone_set('Asia/Taipei');

$backup_date = date('Y-m-d_H-i-s');
$site_name = get_site_name();
$backup_dir = "backup_essential_{$site_name}_{$backup_date}";

echo "=== WordPress 精簡備份腳本 ===\n";
echo "開始時間：" . date('Y-m-d H:i:s') . "\n";
echo "備份範圍：僅必要檔案（排除媒體檔案）\n\n";

if (!file_exists('wp-config.php')) {
    die("錯誤：此腳本必須在 WordPress 根目錄執行\n");
}

if (!mkdir($backup_dir, 0755, true)) {
    die("錯誤：無法創建備份目錄\n");
}

// 1. 備份核心設定檔案
echo "1. 備份核心設定檔案...\n";
backup_essential_files($backup_dir);

// 2. 備份主題檔案（排除圖片）
echo "2. 備份主題檔案...\n";
backup_themes($backup_dir);

// 3. 備份外掛檔案
echo "3. 備份外掛檔案...\n";
backup_plugins($backup_dir);

// 4. 備份 WooCommerce 相關設定
echo "4. 備份 WooCommerce 設定...\n";
backup_woocommerce_config($backup_dir);

// 5. 備份資料庫
echo "5. 備份資料庫...\n";
backup_database($backup_dir);

// 6. 創建備份資訊
echo "6. 創建備份資訊...\n";
create_backup_info($backup_dir);

// 7. 壓縮備份
echo "7. 壓縮備份檔案...\n";
compress_backup($backup_dir);

echo "\n=== 精簡備份完成 ===\n";
echo "備份檔案：{$backup_dir}.zip\n";
echo "備份大小：" . format_bytes(filesize("{$backup_dir}.zip")) . "\n";
echo "完成時間：" . date('Y-m-d H:i:s') . "\n";

remove_directory($backup_dir);

/**
 * 備份核心設定檔案
 */
function backup_essential_files($backup_dir) {
    $essential_files = [
        'wp-config.php',
        '.htaccess',
        'robots.txt',
        'wp-content/debug.log'
    ];
    
    $config_dir = $backup_dir . '/config';
    mkdir($config_dir, 0755, true);
    
    foreach ($essential_files as $file) {
        if (file_exists($file)) {
            echo "  備份：{$file}\n";
            copy($file, $config_dir . '/' . basename($file));
        }
    }
}

/**
 * 備份主題檔案（排除圖片）
 */
function backup_themes($backup_dir) {
    $themes_dir = 'wp-content/themes';
    $backup_themes_dir = $backup_dir . '/themes';
    
    if (!is_dir($themes_dir)) return;
    
    mkdir($backup_themes_dir, 0755, true);
    
    $themes = scandir($themes_dir);
    foreach ($themes as $theme) {
        if ($theme != '.' && $theme != '..') {
            $theme_path = $themes_dir . '/' . $theme;
            if (is_dir($theme_path)) {
                echo "  備份主題：{$theme}\n";
                copy_directory_selective($theme_path, $backup_themes_dir . '/' . $theme, [
                    'exclude_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'mov', 'avi'],
                    'exclude_dirs' => ['node_modules', '.git', '.sass-cache']
                ]);
            }
        }
    }
}

/**
 * 備份外掛檔案
 */
function backup_plugins($backup_dir) {
    $plugins_dir = 'wp-content/plugins';
    $backup_plugins_dir = $backup_dir . '/plugins';
    
    if (!is_dir($plugins_dir)) return;
    
    mkdir($backup_plugins_dir, 0755, true);
    
    // 只備份重要外掛
    $important_plugins = [
        'woocommerce',
        'ecpay-payment-for-woocommerce',  // 常見綠界外掛名稱
        'ecpay-ecommerce-for-woocommerce',
        'ry-woocommerce-ecpay-invoice',
        'woocommerce-gateway-ecpay'
    ];
    
    $plugins = scandir($plugins_dir);
    foreach ($plugins as $plugin) {
        if ($plugin != '.' && $plugin != '..') {
            $plugin_path = $plugins_dir . '/' . $plugin;
            
            // 檢查是否為重要外掛或包含 ecpay 關鍵字
            $is_important = false;
            foreach ($important_plugins as $important) {
                if (strpos(strtolower($plugin), strtolower($important)) !== false) {
                    $is_important = true;
                    break;
                }
            }
            
            if ($is_important && is_dir($plugin_path)) {
                echo "  備份外掛：{$plugin}\n";
                copy_directory_selective($plugin_path, $backup_plugins_dir . '/' . $plugin, [
                    'exclude_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                    'exclude_dirs' => ['node_modules', '.git']
                ]);
            }
        }
    }
}

/**
 * 備份 WooCommerce 相關設定
 */
function backup_woocommerce_config($backup_dir) {
    $woo_config_dir = $backup_dir . '/woocommerce-config';
    mkdir($woo_config_dir, 0755, true);
    
    // 備份 WooCommerce 上傳目錄的設定檔案
    $woo_uploads = 'wp-content/uploads/woocommerce_uploads';
    if (is_dir($woo_uploads)) {
        copy_directory_selective($woo_uploads, $woo_config_dir . '/uploads', [
            'include_extensions' => ['txt', 'log', 'json', 'xml'],
            'exclude_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf']
        ]);
    }
    
    // 備份 WooCommerce 日誌
    $woo_logs = 'wp-content/uploads/wc-logs';
    if (is_dir($woo_logs)) {
        copy_directory($woo_logs, $woo_config_dir . '/logs');
    }
}

/**
 * 選擇性複製目錄
 */
function copy_directory_selective($src, $dst, $options = []) {
    $exclude_extensions = isset($options['exclude_extensions']) ? $options['exclude_extensions'] : [];
    $include_extensions = isset($options['include_extensions']) ? $options['include_extensions'] : [];
    $exclude_dirs = isset($options['exclude_dirs']) ? $options['exclude_dirs'] : [];
    
    if (!file_exists($dst)) {
        mkdir($dst, 0755, true);
    }
    
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            
            if (is_dir($src_file)) {
                // 檢查是否為排除目錄
                if (!in_array($file, $exclude_dirs)) {
                    copy_directory_selective($src_file, $dst_file, $options);
                }
            } else {
                $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                
                $should_copy = true;
                
                // 如果有指定包含的副檔名
                if (!empty($include_extensions)) {
                    $should_copy = in_array($file_extension, $include_extensions);
                }
                
                // 檢查排除的副檔名
                if ($should_copy && !empty($exclude_extensions)) {
                    $should_copy = !in_array($file_extension, $exclude_extensions);
                }
                
                if ($should_copy) {
                    copy($src_file, $dst_file);
                }
            }
        }
    }
    closedir($dir);
}

/**
 * 備份資料庫
 */
function backup_database($backup_dir) {
    require_once('wp-config.php');
    
    $db_backup_file = $backup_dir . '/database.sql';
    
    $command = sprintf(
        'mysqldump --single-transaction --routines --triggers --host=%s --user=%s --password=%s %s > %s',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASSWORD),
        escapeshellarg(DB_NAME),
        escapeshellarg($db_backup_file)
    );
    
    $output = array();
    $return_var = 0;
    exec($command . ' 2>&1', $output, $return_var);
    
    if ($return_var !== 0) {
        echo "  警告：mysqldump 失敗，嘗試使用 PHP 方法備份\n";
        backup_database_php($backup_dir);
    } else {
        echo "  資料庫備份完成\n";
    }
}

/**
 * PHP 資料庫備份
 */
function backup_database_php($backup_dir) {
    $db_backup_file = $backup_dir . '/database.sql';
    
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASSWORD,
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
        );
        
        $fp = fopen($db_backup_file, 'w');
        fwrite($fp, "-- WordPress Database Backup (Essential)\n");
        fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
        
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // 跳過大型資料表（如統計、日誌等）
            $skip_tables = ['wp_actionscheduler_logs', 'wp_wc_admin_notes', 'wp_statistics_'];
            $should_skip = false;
            
            foreach ($skip_tables as $skip) {
                if (strpos($table, $skip) !== false) {
                    $should_skip = true;
                    break;
                }
            }
            
            if (!$should_skip) {
                echo "  備份資料表：{$table}\n";
                
                $create_table = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
                fwrite($fp, "\nDROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fp, $create_table[1] . ";\n\n");
                
                $rows = $pdo->query("SELECT * FROM `{$table}`");
                if ($rows->rowCount() > 0) {
                    fwrite($fp, "INSERT INTO `{$table}` VALUES\n");
                    
                    $first = true;
                    while ($row = $rows->fetch(PDO::FETCH_NUM)) {
                        if (!$first) fwrite($fp, ",\n");
                        fwrite($fp, "(");
                        for ($i = 0; $i < count($row); $i++) {
                            if ($i > 0) fwrite($fp, ", ");
                            if ($row[$i] === null) {
                                fwrite($fp, "NULL");
                            } else {
                                fwrite($fp, "'" . addslashes($row[$i]) . "'");
                            }
                        }
                        fwrite($fp, ")");
                        $first = false;
                    }
                    fwrite($fp, ";\n\n");
                }
            }
        }
        
        fclose($fp);
        echo "  精簡資料庫備份完成\n";
        
    } catch (Exception $e) {
        echo "  錯誤：資料庫備份失敗 - " . $e->getMessage() . "\n";
    }
}

/**
 * 創建備份資訊
 */
function create_backup_info($backup_dir) {
    $info_file = $backup_dir . '/backup-info.txt';
    $info = array();
    
    $info[] = "WordPress 精簡備份資訊";
    $info[] = "======================";
    $info[] = "備份時間：" . date('Y-m-d H:i:s');
    $info[] = "備份類型：精簡備份（排除媒體檔案）";
    $info[] = "備份原因：WooCommerce 綠界分期付款控制系統安裝前備份";
    $info[] = "";
    $info[] = "排除內容：";
    $info[] = "- 圖片檔案 (jpg, png, gif, webp 等)";
    $info[] = "- 影片檔案 (mp4, mov, avi 等)";
    $info[] = "- 開發檔案 (node_modules, .git 等)";
    $info[] = "- 大型日誌檔案";
    $info[] = "";
    $info[] = "包含內容：";
    $info[] = "- 核心設定檔案";
    $info[] = "- 主題 PHP/CSS/JS 檔案";
    $info[] = "- 重要外掛檔案";
    $info[] = "- WooCommerce 設定";
    $info[] = "- 完整資料庫（排除統計表）";
    
    file_put_contents($info_file, implode("\n", $info));
}

/**
 * 壓縮備份
 */
function compress_backup($backup_dir) {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $zip_file = $backup_dir . '.zip';
        
        if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
            add_dir_to_zip($backup_dir, $zip, $backup_dir);
            $zip->close();
        }
    }
}

function add_dir_to_zip($dir, $zip, $base_dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $file_path = $dir . '/' . $file;
            $relative_path = str_replace($base_dir . '/', '', $file_path);
            
            if (is_dir($file_path)) {
                $zip->addEmptyDir($relative_path);
                add_dir_to_zip($file_path, $zip, $base_dir);
            } else {
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
}

function remove_directory($dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $file_path = $dir . '/' . $file;
                if (is_dir($file_path)) {
                    remove_directory($file_path);
                } else {
                    unlink($file_path);
                }
            }
        }
        rmdir($dir);
    }
}

function copy_directory($src, $dst) {
    if (!file_exists($dst)) {
        mkdir($dst, 0755, true);
    }
    
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            
            if (is_dir($src_file)) {
                copy_directory($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }
    }
    closedir($dir);
}

function get_site_name() {
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    return preg_replace('/[^a-zA-Z0-9-]/', '_', $host);
}

function format_bytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
?>
