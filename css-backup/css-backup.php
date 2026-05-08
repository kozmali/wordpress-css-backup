<?php
/**
 * Plugin Name: WordPress CSS Backup
 * Description: Automatic and manual backups for WordPress Additional CSS with restore and download support.
 * Version: 1.0.0
 * Author: Dominik Kozmáli
 * License: GPL-2.0-or-later
 */

/**
 * Manuálne zálohy Dodatočného CSS
 */

// 1. Pridaj položku do admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'themes.php',
        'Zálohy Dodatočného CSS',
        'Zálohy CSS',
        'manage_options',
        'kozmali-css-backups',
        'kozmali_css_backups_page'
    );
});

// 2. Funkcia na vytvorenie zálohy
function kozmali_create_backup($is_auto = false) {
    $current_css = wp_get_custom_css();
    
    if (empty($current_css)) {
        return false;
    }
    
    $backups = get_option('kozmali_css_zalohy', []);
    
    // Kontrola duplicity
    if (!empty($backups) && $backups[0]['obsah'] === $current_css) {
        return false;
    }
    
    $label = $is_auto ? ' (automatická týždenná záloha)' : '';
    
    array_unshift($backups, [
        'datum' => current_time('mysql'),
        'obsah' => $current_css,
        'hash'  => md5($current_css),
        'label' => $label,
    ]);
    
    $backups = array_slice($backups, 0, 20);
    update_option('kozmali_css_zalohy', $backups);
    
    return true;
}

// 3. Funkcia na obnovenie zálohy
function kozmali_restore_backup($obsah) {
    if (empty($obsah)) {
        return false;
    }
    
    // Najprv vytvor zálohu aktuálneho CSS
    $current_css = wp_get_custom_css();
    if (!empty($current_css)) {
        $backups = get_option('kozmali_css_zalohy', []);
        array_unshift($backups, [
            'datum' => current_time('mysql'),
            'obsah' => $current_css,
            'hash'  => md5($current_css),
            'label' => ' (automaticky pred obnovou)',
        ]);
        $backups = array_slice($backups, 0, 20);
        update_option('kozmali_css_zalohy', $backups);
    }
    
    // Obnov CSS
    wp_update_custom_css_post($obsah);
    
    return true;
}

// 4. Funkcia na stiahnutie CSS súboru
function kozmali_download_css() {
    if (!isset($_GET['download_backup']) || !isset($_GET['backup_index']) || !wp_verify_nonce($_GET['_wpnonce'], 'download_css')) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Nemáte oprávnenie.');
    }
    
    $backups = get_option('kozmali_css_zalohy', []);
    $index = intval($_GET['backup_index']);
    
    if (!isset($backups[$index])) {
        wp_die('Záloha neexistuje.');
    }
    
    $backup = $backups[$index];
    $filename = 'custom-css-backup-' . sanitize_title($backup['datum']) . '.css';
    
    header('Content-Type: text/css');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($backup['obsah']));
    header('Cache-Control: no-cache');
    
    echo $backup['obsah'];
    exit;
}
add_action('admin_init', 'kozmali_download_css');

// Pomocná funkcia na formátovanie dátumu
function kozmali_format_date($mysql_date) {
    $timestamp = strtotime($mysql_date);
    return date('d. m. Y H:i:s', $timestamp);
}

// 5. Admin stránka pre zálohy
function kozmali_css_backups_page() {
    // Spracovanie akcií
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_backup' && wp_verify_nonce($_POST['nonce'], 'css_backup')) {
            if (kozmali_create_backup(false)) {
                echo '<div class="notice notice-success is-dismissible"><p>✅ Záloha bola vytvorená!</p></div>';
            } else {
                echo '<div class="notice notice-warning is-dismissible"><p>⚠️ Žiadne zmeny, záloha nebola vytvorená.</p></div>';
            }
        }
        
        if ($_POST['action'] === 'restore_backup' && isset($_POST['backup_index']) && wp_verify_nonce($_POST['nonce'], 'css_backup')) {
            $backups = get_option('kozmali_css_zalohy', []);
            $index = intval($_POST['backup_index']);
            
            if (isset($backups[$index])) {
                if (kozmali_restore_backup($backups[$index]['obsah'])) {
                    echo '<div class="notice notice-success is-dismissible"><p>✅ CSS bolo obnovené zo zálohy!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>❌ Chyba pri obnove.</p></div>';
                }
            }
        }
        
        if ($_POST['action'] === 'delete_backup' && isset($_POST['backup_index']) && wp_verify_nonce($_POST['nonce'], 'css_backup')) {
            $backups = get_option('kozmali_css_zalohy', []);
            $index = intval($_POST['backup_index']);
            
            if (isset($backups[$index])) {
                array_splice($backups, $index, 1);
                update_option('kozmali_css_zalohy', $backups);
                echo '<div class="notice notice-success is-dismissible"><p>🗑️ Záloha bola odstránená.</p></div>';
            }
        }
    }
    
    $backups = get_option('kozmali_css_zalohy', []);
    $current_css = wp_get_custom_css();
    $total_backups = count($backups);
    ?>
    <div class="wrap">
        <h1>📦 Zálohy Dodatočného CSS</h1>
        
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>📝 Aktuálne Dodatočné CSS</h2>
            <div style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 300px; font-size: 12px; font-family: monospace; white-space: pre-wrap; word-break: break-all; border-left: 3px solid #2271b1;">
                <?php 
                if (empty($current_css)) {
                    echo '<span style="color: #999;">Zatiaľ žiadne Dodatočné CSS.</span>';
                } else {
                    echo esc_html($current_css);
                }
                ?>
            </div>
            
            <form method="post" style="margin-top: 15px;">
                <?php wp_nonce_field('css_backup', 'nonce'); ?>
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="button button-primary">💾 Vytvoriť zálohu teraz</button>
            </form>
        </div>
        
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>🕐 Posledné zálohy (max. 20) <span style="font-size: 12px; color: #666;">(celkom: <?php echo $total_backups; ?> / 20)</span></h2>
            
            <?php if (empty($backups)): ?>
                <p style="color: #999; padding: 20px; text-align: center; background: #fafafa;">
                    Zatiaľ žiadne zálohy.<br>
                    Kliknite na "Vytvoriť zálohu" pre uloženie aktuálneho CSS.
                </p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped" style="border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="20%">Dátum</th>
                                <th width="55%">Náhľad CSS</th>
                                <th width="20%">Akcie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $index => $backup): ?>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <td style="vertical-align: middle; padding: 12px 8px;">
                                        <strong><?php echo $index + 1; ?></strong>
                                    </td>
                                    <td style="vertical-align: middle; padding: 12px 8px;">
                                        <?php echo esc_html(kozmali_format_date($backup['datum']) . ($backup['label'] ?? '')); ?>
                                    </td>
                                    <td style="vertical-align: middle; padding: 12px 8px;">
                                        <div style="background: #f9f9f9; border-left: 3px solid #2271b1; padding: 8px; border-radius: 4px;">
                                            <code style="font-size: 11px; font-family: monospace; white-space: pre-wrap; word-break: break-all; display: block; max-height: 120px; overflow-y: auto;">
                                                <?php 
                                                echo esc_html(substr($backup['obsah'], 0, 300));
                                                echo strlen($backup['obsah']) > 300 ? '…' : '';
                                                ?>
                                            </code>
                                        </div>
                                    </td>
                                    <td style="vertical-align: middle; padding: 12px 8px;">
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            <form method="post" style="display: inline-block; margin: 0;">
                                                <?php wp_nonce_field('css_backup', 'nonce'); ?>
                                                <input type="hidden" name="action" value="restore_backup">
                                                <input type="hidden" name="backup_index" value="<?php echo $index; ?>">
                                                <button type="submit" class="button button-small" onclick="return confirm('Naozaj obnoviť túto zálohu?');" style="background: #2271b1; color: white; border-color: #2271b1;">
                                                    ↩ Obnoviť
                                                </button>
                                            </form>
                                            
                                            <a href="<?php echo wp_nonce_url(admin_url('themes.php?page=kozmali-css-backups&download_backup=1&backup_index=' . $index), 'download_css'); ?>" class="button button-small" style="background: #46b450; color: white; border-color: #46b450; text-decoration: none;">
                                                ⬇ Stiahnuť
                                            </a>
                                            
                                            <form method="post" style="display: inline-block; margin: 0;">
                                                <?php wp_nonce_field('css_backup', 'nonce'); ?>
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="backup_index" value="<?php echo $index; ?>">
                                                <button type="submit" class="button button-small" style="background: #dc3232; color: white; border-color: #dc3232;" onclick="return confirm('Odstrániť túto zálohu?');">
                                                    🗑 Zmazať
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .wp-list-table td {
            vertical-align: middle !important;
        }
        .button-small {
            line-height: 2;
            min-height: 28px;
            cursor: pointer;
        }
        .button-small:hover {
            opacity: 0.8;
        }
        .wp-list-table tbody tr:hover {
            background-color: #f5f5f5 !important;
        }
        code::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        code::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        code::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        code::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
    <?php
}

// 6. Pridaj tlačidlo do admin baru
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;
    
    $wp_admin_bar->add_node([
        'id'    => 'kozmali-css-backup',
        'title' => '💾 Zálohovať CSS',
        'href'  => admin_url('themes.php?page=kozmali-css-backups&backup=now'),
    ]);
}, 500);

// 7. Spracovanie rýchlej zálohy
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'kozmali-css-backups' && isset($_GET['backup']) && $_GET['backup'] === 'now') {
        if (kozmali_create_backup(false)) {

    set_transient('kozmali_css_notice', 'success', 5);

} else {

    set_transient('kozmali_css_notice', 'warning', 5);

}

wp_redirect(admin_url('themes.php?page=kozmali-css-backups'));
exit;
    }
    
add_action('admin_notices', function() {

    $notice = get_transient('kozmali_css_notice');

    if (!$notice) {
        return;
    }

    delete_transient('kozmali_css_notice');

    if ($notice === 'success') {
        echo '<div class="notice notice-success is-dismissible"><p>✅ Záloha bola vytvorená!</p></div>';
    }

    if ($notice === 'warning') {
        echo '<div class="notice notice-warning is-dismissible"><p>⚠️ Žiadne zmeny, záloha nebola vytvorená.</p></div>';
    }

});
});


// 8. AUTOMATICKÁ TÝŽDENNÁ ZÁLOHA (TEST - spustí sa hneď)

add_action('shutdown', function() {

    // nespúšťaj v admine ani ajaxe
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    $last_backup = get_option('kozmali_last_week_backup_time', 0);
    $now = time();

    // prvá záloha alebo po 7 dňoch
    if ($last_backup == 0 || ($now - $last_backup) >= 7 * 24 * 60 * 60) {

        $created = kozmali_create_backup(true);

        // timestamp len ak sa fakt vytvorila záloha
        if ($created) {
            update_option('kozmali_last_week_backup_time', $now);
        }

    }

});
