<?php
/**
 * Plugin Name: Additional CSS Backup
 * Description: Automatic and manual backups for WordPress Additional CSS with restore and download support.
 * Version: 1.0.1
 * Author: Dominik Kozmáli
 * Author URI: https://kozmali.sk
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: additional-css-backup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Pridaj položku do admin menu
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

// 2. Funkcia na vytvorenie zálohy
function acssb_create_backup( $is_auto = false ) {
	$current_css = wp_get_custom_css();
	
	if ( empty( $current_css ) ) {
		return false;
	}
	
	$backups = get_option( 'acssb_css_zalohy', array() );
	
	if ( ! empty( $backups ) && $backups[0]['obsah'] === $current_css ) {
		return false;
	}
	
	$label = $is_auto ? ' (' . esc_html__( 'automatická týždenná záloha', 'additional-css-backup' ) . ')' : '';
	
	array_unshift( $backups, array(
		'datum' => current_time( 'mysql' ),
		'obsah' => $current_css,
		'hash'  => md5( $current_css ),
		'label' => $label,
	) );
	
	$backups = array_slice( $backups, 0, 20 );
	update_option( 'acssb_css_zalohy', $backups );
	
	return true;
}

// 3. Funkcia na obnovenie zálohy
function acssb_restore_backup( $obsah ) {
	if ( empty( $obsah ) ) {
		return false;
	}
	
	$current_css = wp_get_custom_css();
	if ( ! empty( $current_css ) ) {
		$backups = get_option( 'acssb_css_zalohy', array() );
		array_unshift( $backups, array(
			'datum' => current_time( 'mysql' ),
			'obsah' => $current_css,
			'hash'  => md5( $current_css ),
			'label' => ' (' . esc_html__( 'automaticky pred obnovou', 'additional-css-backup' ) . ')',
		) );
		$backups = array_slice( $backups, 0, 20 );
		update_option( 'acssb_css_zalohy', $backups );
	}
	
	wp_update_custom_css_post( $obsah );
	return true;
}

// 4. Funkcia na stiahnutie CSS súboru
add_action( 'admin_init', function() {
	if ( ! isset( $_GET['download_backup'] ) || ! isset( $_GET['backup_index'] ) || ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}
	
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'download_css' ) ) {
		wp_die( esc_html__( 'Bezpečnostná chyba.', 'additional-css-backup' ) );
	}
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Nemáte oprávnenie.', 'additional-css-backup' ) );
	}
	
	$backups = get_option( 'acssb_css_zalohy', array() );
	$index   = intval( $_GET['backup_index'] );
	
	if ( ! isset( $backups[ $index ] ) ) {
		wp_die( esc_html__( 'Záloha neexistuje.', 'additional-css-backup' ) );
	}
	
	$backup   = $backups[ $index ];
	$filename = 'custom-css-backup-' . sanitize_title( $backup['datum'] ) . '.css';
	
	header( 'Content-Type: text/css' );
	header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
	header( 'Content-Length: ' . strlen( $backup['obsah'] ) );
	header( 'Cache-Control: no-cache' );
	
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $backup['obsah']; 
	exit;
} );

function acssb_format_date( $mysql_date ) {
	$timestamp = strtotime( $mysql_date );
	return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
}

// 5. Admin stránka
function acssb_css_backups_page() {
	if ( isset( $_POST['action'] ) && isset( $_POST['acssb_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['acssb_nonce'] ) ), 'acssb_action' ) ) {
			wp_die( esc_html__( 'Bezpečnostná chyba pri odosielaní.', 'additional-css-backup' ) );
		}

		if ( 'create_backup' === $_POST['action'] ) {
			if ( acssb_create_backup( false ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '✅ Záloha bola vytvorená!', 'additional-css-backup' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( '⚠️ Žiadne zmeny, záloha nebola vytvorená.', 'additional-css-backup' ) . '</p></div>';
			}
		}
		
		if ( 'restore_backup' === $_POST['action'] && isset( $_POST['backup_index'] ) ) {
			$backups = get_option( 'acssb_css_zalohy', array() );
			$index   = intval( $_POST['backup_index'] );
			if ( isset( $backups[ $index ] ) && acssb_restore_backup( $backups[ $index ]['obsah'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '✅ CSS bolo obnovené!', 'additional-css-backup' ) . '</p></div>';
			}
		}
	}
	
	$backups     = get_option( 'acssb_css_zalohy', array() );
	$current_css = wp_get_custom_css();
	?>
	<div class="wrap">
		<h1>📦 <?php echo esc_html__( 'Zálohy Dodatočného CSS', 'additional-css-backup' ); ?></h1>
		
		<div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
			<h2>📝 <?php echo esc_html__( 'Aktuálne Dodatočné CSS', 'additional-css-backup' ); ?></h2>
			<pre style="background: #f5f5f5; padding: 15px; max-height: 200px; overflow: auto; border-left: 4px solid #2271b1;"><?php echo esc_html( $current_css ? $current_css : esc_html__( 'Zatiaľ prázdne.', 'additional-css-backup' ) ); ?></pre>
			
			<form method="post">
				<?php wp_nonce_field( 'acssb_action', 'acssb_nonce' ); ?>
				<input type="hidden" name="action" value="create_backup">
				<?php submit_button( esc_html__( '💾 Vytvoriť zálohu teraz', 'additional-css-backup' ) ); ?>
			</form>
		</div>

		<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
			<thead>
				<tr>
					<th width="20%"><?php echo esc_html__( 'Dátum', 'additional-css-backup' ); ?></th>
					<th width="60%"><?php echo esc_html__( 'Náhľad', 'additional-css-backup' ); ?></th>
					<th width="20%"><?php echo esc_html__( 'Akcie', 'additional-css-backup' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $backups ) ) : ?>
					<tr><td colspan="3"><?php echo esc_html__( 'Žiadne zálohy.', 'additional-css-backup' ); ?></td></tr>
				<?php else : foreach ( $backups as $index => $backup ) : ?>
					<tr>
						<td><?php echo esc_html( acssb_format_date( $backup['datum'] ) . ( isset( $backup['label'] ) ? $backup['label'] : '' ) ); ?></td>
						<td><code><?php echo esc_html( wp_trim_words( $backup['obsah'], 10 ) ); ?></code></td>
						<td>
							<form method="post" style="display:inline;">
								<?php wp_nonce_field( 'acssb_action', 'acssb_nonce' ); ?>
								<input type="hidden" name="action" value="restore_backup">
								<input type="hidden" name="backup_index" value="<?php echo intval( $index ); ?>">
								<button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_attr__( 'Naozaj obnoviť?', 'additional-css-backup' ); ?>')">↩</button>
							</form>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'themes.php?page=acssb-backups&download_backup=1&backup_index=' . $index ), 'download_css' ) ); ?>" class="button button-small">⬇</a>
						</td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

// 6. Rýchla záloha
add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$wp_admin_bar->add_node( array(
		'id'    => 'acssb-quick-backup',
		'title' => '💾 ' . esc_html__( 'Zálohovať CSS', 'additional-css-backup' ),
		'href'  => wp_nonce_url( admin_url( 'themes.php?page=acssb-backups&acssb_quick_backup=1' ), 'acssb_quick_nonce' ),
	) );
}, 500 );

// 7. Spracovanie
add_action( 'admin_init', function() {
	if ( isset( $_GET['acssb_quick_backup'] ) && isset( $_GET['_wpnonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'acssb_quick_nonce' ) ) {
			return;
		}
		acssb_create_backup( false );
		wp_safe_redirect( admin_url( 'themes.php?page=acssb-backups' ) );
		exit;
	}
} );

// 8. Týždenná záloha
add_action( 'wp_scheduled_delete', function() {
	acssb_create_backup( true );
} );
