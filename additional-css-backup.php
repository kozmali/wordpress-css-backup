<?php
/**
 * Plugin Name: Additional CSS Backup (Zálohovanie Dodatočného CSS)
 * Description: Automatické a manuálne zálohy pre Dodatočné CSS s podporou obnovy aj stiahnutia.
 * Version: 1.0.31
 * Author: Dominik Kozmáli
 * Author URI: https://kozmali.sk
 * License: GPLv2 or later
 * Text Domain: additional-css-backup
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Admin menu
add_action( 'admin_menu', function() {
	add_submenu_page(
		'themes.php',
		esc_html__( 'Zálohy Dodatočného CSS', 'additional-css-backup' ),
		esc_html__( 'Zálohy CSS', 'additional-css-backup' ),
		'manage_options',
		'acssb-backups',
		'acssb_css_backups_page'
	);
} );

// 2. Vytvorenie zálohy
function acssb_create_backup( $is_auto = false ) {
	$current_css = wp_get_custom_css();
	if ( empty( $current_css ) ) return false;
	
	$backups = get_option( 'acssb_css_zalohy', array() );
	if ( ! empty( $backups ) && isset($backups[0]) && $backups[0]['obsah'] === $current_css ) return false;
	
	// Prekladáme hneď pri vzniku, aby Plugin Check nehlásil chybu pri zobrazovaní premennej
	$label = $is_auto ? esc_html__( 'automatická týždenná záloha', 'additional-css-backup' ) : '';
	
	array_unshift( $backups, array(
		'datum' => wp_date( 'Y-m-d H:i:s' ),
		'obsah' => $current_css,
		'hash'  => md5( $current_css ),
		'label' => $label,
	) );
	
	$backups = array_slice( $backups, 0, 20 );
	update_option( 'acssb_css_zalohy', $backups );
	return true;
}

// 3. Obnovenie zálohy
function acssb_restore_backup( $obsah ) {
	if ( empty( $obsah ) ) return false;
	$current_css = wp_get_custom_css();
	if ( ! empty( $current_css ) ) {
		$backups = get_option( 'acssb_css_zalohy', array() );
		array_unshift( $backups, array(
			'datum' => wp_date( 'Y-m-d H:i:s' ),
			'obsah' => $current_css,
			'hash'  => md5( $current_css ),
			'label' => esc_html__( 'automaticky pred obnovou', 'additional-css-backup' ),
		) );
		update_option( 'acssb_css_zalohy', array_slice( $backups, 0, 20 ) );
	}
	return wp_update_custom_css_post( $obsah );
}

// 4. Formátovanie dátumu (vynútené sekundy)
function acssb_format_date( $mysql_date ) {
    $timestamp = strtotime( $mysql_date );
    // Pridané :s na koniec pre zobrazenie sekúnd
    return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ':s', $timestamp );
}

// 5. Sťahovanie a spracovanie (admin_init)
add_action( 'admin_init', function() {
	if ( isset( $_GET['download_backup'], $_GET['backup_index'], $_GET['_wpnonce'] ) ) {
		if ( wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'download_css' ) && current_user_can( 'manage_options' ) ) {
			$backups = get_option( 'acssb_css_zalohy', array() );
			$index = intval( $_GET['backup_index'] );
			if ( isset( $backups[ $index ] ) ) {
				$backup = $backups[ $index ];
				$filename = 'custom-css-backup-' . sanitize_title( $backup['datum'] ) . '.css';
				header( 'Content-Type: text/css' );
				header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
				echo $backup['obsah']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}
		}
	}
	if ( isset( $_GET['acssb_quick_backup'], $_GET['_wpnonce'] ) ) {
		if ( wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'acssb_quick_nonce' ) ) {
			acssb_create_backup( false );
			wp_safe_redirect( admin_url( 'themes.php?page=acssb-backups' ) );
			exit;
		}
	}
} );

// 6. Admin stránka
function acssb_css_backups_page() {
	if ( isset( $_POST['action'], $_POST['acssb_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['acssb_nonce'] ) ), 'acssb_action' ) ) {
			wp_die( esc_html__( 'Bezpečnostná chyba.', 'additional-css-backup' ) );
		}

		$index = isset( $_POST['backup_index'] ) ? intval( $_POST['backup_index'] ) : -1;
		$backups = get_option( 'acssb_css_zalohy', array() );

		if ( 'create_backup' === $_POST['action'] ) {
			if ( acssb_create_backup( false ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>✅ ' . esc_html__( 'Záloha bola vytvorená!', 'additional-css-backup' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( '⚠️ Žiadne zmeny, záloha nebola vytvorená.', 'additional-css-backup' ) . '</p></div>';
			}
		} elseif ( 'restore_backup' === $_POST['action'] && isset( $backups[ $index ] ) ) {
			if ( acssb_restore_backup( $backups[ $index ]['obsah'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>✅ ' . esc_html__( 'CSS bolo obnovené zo zálohy!', 'additional-css-backup' ) . '</p></div>';
			}
		} elseif ( 'delete_backup' === $_POST['action'] && isset( $backups[ $index ] ) ) {
			array_splice( $backups, $index, 1 );
			update_option( 'acssb_css_zalohy', $backups );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '🗑️ Záloha bola odstránená.', 'additional-css-backup' ) . '</p></div>';
		}
	}

	$backups = get_option( 'acssb_css_zalohy', array() );
	$current_css = wp_get_custom_css();
	$total_backups = count( $backups );
	?>
	<div class="wrap">
		<h1>📦 <?php echo esc_html__( 'Zálohy Dodatočného CSS', 'additional-css-backup' ); ?></h1>

		<div class="card acssb-card">
			<h2>📝 <?php echo esc_html__( 'Aktuálne Dodatočné CSS', 'additional-css-backup' ); ?></h2>
			<pre class="acssb-code-view"><?php 
				echo $current_css ? esc_html( $current_css ) : '<span style="color: #999;">' . esc_html__( 'Zatiaľ žiadne Dodatočné CSS.', 'additional-css-backup' ) . '</span>'; 
			?></pre>
			<form method="post" style="margin-top: 15px;">
				<?php wp_nonce_field( 'acssb_action', 'acssb_nonce' ); ?>
				<input type="hidden" name="action" value="create_backup">
				<button type="submit" class="button button-primary">💾 <?php echo esc_html__( 'Vytvoriť zálohu teraz', 'additional-css-backup' ); ?></button>
			</form>
		</div>

		<div class="card acssb-card">
			<h2>🕐 <?php echo esc_html__( 'Posledné zálohy (max. 20)', 'additional-css-backup' ); ?> 
				<span class="acssb-counter">
					(<?php echo esc_html__( 'celkom', 'additional-css-backup' ) . ': ' . intval( $total_backups ) . ' / 20'; ?>)
				</span>
			</h2>
			<div style="overflow-x: auto;">
				<table class="wp-list-table widefat striped acssb-table">
					<thead>
						<tr>
							<th class="col-id">#</th>
							<th class="col-date"><?php echo esc_html__( 'Dátum', 'additional-css-backup' ); ?></th>
							<th class="col-preview"><?php echo esc_html__( 'Náhľad CSS', 'additional-css-backup' ); ?></th>
							<th class="col-actions"><?php echo esc_html__( 'Akcie', 'additional-css-backup' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $backups ) ) : ?>
							<tr><td colspan="4" style="text-align:center;padding:20px;"><?php echo esc_html__( 'Zatiaľ žiadne zálohy.', 'additional-css-backup' ); ?></td></tr>
						<?php else : foreach ( $backups as $index => $backup ) : ?>
							<tr>
								<td style="vertical-align: middle; padding: 12px 8px;">
									<strong><?php echo intval( $index ) + 1; ?></strong>
								</td>
								<td style="vertical-align: middle; padding: 12px 8px;">
                                    <div class="acssb-date-wrapper">
                                        <?php 
                                        echo esc_html( acssb_format_date( $backup['datum'] ) ); 
                                        
                                        // Štítok už prekladáme pri ukladaní, tu ho len bezpečne vypíšeme
                                        if ( ! empty( $backup['label'] ) ) {
                                            echo '<br><span style="font-size: 11px; font-style: italic; color: #666;">(' . esc_html( $backup['label'] ) . ')</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
								<td style="vertical-align: middle; padding: 12px 8px;">
									<div class="acssb-table-preview">
										<code><?php echo esc_html( $backup['obsah'] ); ?></code>
									</div>
								</td>
								<td style="vertical-align: middle; padding: 12px 8px;">
									<div class="acssb-action-buttons">
										<form method="post" style="display:inline-block; margin:0;">
											<?php wp_nonce_field( 'acssb_action', 'acssb_nonce' ); ?>
											<input type="hidden" name="action" value="restore_backup">
											<input type="hidden" name="backup_index" value="<?php echo intval( $index ); ?>">
											<button type="submit" class="button button-small acssb-btn-restore" onclick="return confirm('<?php echo esc_attr__( 'Naozaj obnoviť túto zálohu?', 'additional-css-backup' ); ?>')">↩ <?php echo esc_html__( 'Obnoviť', 'additional-css-backup' ); ?></button>
										</form>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'themes.php?page=acssb-backups&download_backup=1&backup_index=' . intval( $index ) ), 'download_css' ) ); ?>" class="button button-small acssb-btn-download">⬇ <?php echo esc_html__( 'Stiahnuť', 'additional-css-backup' ); ?></a>
										<form method="post" style="display:inline-block; margin:0;">
											<?php wp_nonce_field( 'acssb_action', 'acssb_nonce' ); ?>
											<input type="hidden" name="action" value="delete_backup">
											<input type="hidden" name="backup_index" value="<?php echo intval( $index ); ?>">
											<button type="submit" class="button button-small acssb-btn-delete" onclick="return confirm('<?php echo esc_attr__( 'Odstrániť túto zálohu?', 'additional-css-backup' ); ?>')">🗑 <?php echo esc_html__( 'Zmazať', 'additional-css-backup' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<style>
		.acssb-card { max-width: 100%; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
		.acssb-counter { font-size: 12px; color: #666; font-weight: 500; margin-left: 2px; }
		.acssb-code-view { background: #f5f5f5; padding: 15px; margin: 0; overflow: auto; max-height: 200px; font-size: 12px; font-family: monospace; white-space: pre-wrap; word-break: break-all; border-left: 3px solid #2271b1; border-radius: 0; line-height: 1.5; }
		.acssb-table { table-layout: auto !important; width: 100% !important; }
		.acssb-table th.col-id { width: 14px !important; }
		.acssb-table th.col-date { width: 130px !important; }
		.acssb-table th.col-actions { width: 120px !important; } 
		.acssb-date-wrapper { display: block !important; white-space: normal !important; line-height: 1.3; min-width: 110px; }
		.acssb-table-preview { background: #f9f9f9; border-left: 3px solid #2271b1; padding: 8px; border-radius: 4px; }
		.acssb-table-preview code { font-size: 11px; font-family: monospace; white-space: pre-wrap; display: block; max-height: 120px; overflow-y: auto; word-break: break-all; line-height: 1.4; }
		.acssb-action-buttons { display: flex !important; flex-direction: column !important; gap: 5px !important; align-items: stretch !important; max-width: 110px; }
		.acssb-action-buttons form, .acssb-action-buttons a { display: block !important; margin: 0 !important; width: 100%; }
		.acssb-action-buttons .button { width: 100%; text-align: center; }
		.acssb-btn-restore { background: #2271b1 !important; color: white !important; border-color: #2271b1 !important; }
		.acssb-btn-download { background: #46b450 !important; color: white !important; border-color: #46b450 !important; text-decoration: none; }
		.acssb-btn-delete { background: #dc3232 !important; color: white !important; border-color: #dc3232 !important; }
		.acssb-btn-restore:hover { background: #135e96 !important; border-color: #135e96 !important; }
		.acssb-btn-download:hover { background: #349a3d !important; border-color: #349a3d !important; }
		.acssb-btn-delete:hover { background: #b32d2d !important; border-color: #b32d2d !important; }
		@media screen and (max-width: 782px) {
		    .acssb-table, .acssb-table thead, .acssb-table tbody, .acssb-table tr, .acssb-table td { display: block; width: 100% !important; }
		    .acssb-table thead { display: none; }
		    .acssb-table tr { margin-bottom: 20px; border: 1px solid #ccd0d4; padding: 12px; }
		    .acssb-table td { border: none !important; padding: 6px 0 !important; }
		    .acssb-action-buttons { margin-top: 10px; }
		}
		@media screen and (min-width: 1101px) {
		    .acssb-table th.col-actions, .acssb-table td.col-actions { width: 200px !important; padding-right: 37px !important; }
		    .acssb-action-buttons { flex-direction: row !important; max-width: none !important; }
		    .acssb-action-buttons .button { width: auto !important; }
		}
	</style>
	<?php
}

// 7. Admin Bar Quick Backup
add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
	if ( current_user_can( 'manage_options' ) ) {
		$wp_admin_bar->add_node( array(
			'id'    => 'acssb-quick-backup',
			'title' => '💾 ' . esc_html__( 'Zálohovať CSS', 'additional-css-backup' ),
			'href'  => wp_nonce_url( admin_url( 'themes.php?page=acssb-backups&acssb_quick_backup=1' ), 'acssb_quick_nonce' ),
		) );
	}
}, 500 );

// 8. Týždenná záloha
add_action( 'wp_scheduled_delete', function() {
	acssb_create_backup( true );
} );