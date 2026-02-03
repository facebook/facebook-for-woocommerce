<?php
/**
 * Facebook Menu Settings moved note.
 *
 * Adds a note to merchant's inbox about Facebook Menu being moved to Marketing menu.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin\Notes;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

/**
 * SettingsMoved class.
 */
class SettingsMoved {
	/**
	 * Note traits.
	 */
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'facebook-for-woocommerce-settings-moved-to-marketing';

	/**
	 * Checks if this note should be displayed.
	 *
	 * @return bool
	 */
	public static function should_display() {
		/**
		 * The Facebook menu was moved under Marketing menu in v2.2.0. Display this note
		 * only to users updating from a version prior to v2.2.0.
		 */
		$should_display = false;
		$last_event     = facebook_for_woocommerce()->get_last_event_from_history();

		if ( isset( $last_event['name'] ) && 'upgrade' === $last_event['name'] ) {
			$last_version = $last_event['data']['from_version'];
			if ( version_compare( $last_version, '2.2.0', '<' ) ) {
				$should_display = true;
			}
		}

		return $should_display;
	}

	/**
	 * Add or delete note depending on the conditions to display the note.
	 *
	 * @throws NotesUnavailableException Throws exception when notes are unavailable.
	 */
	public static function possibly_add_or_delete_note() {
		// Verify the conditions to display the note.
		if ( self::should_display() ) {
			self::possibly_add_note();
		} elseif ( self::note_exists() ) {
			self::possibly_delete_note();
		}
	}

	/**
	 * Get the note.
	 *
	 * @return Note
	 */
	public static function get_note() {
		$settings_url = facebook_for_woocommerce()->get_settings_url();
		$content      = esc_html__( 'Sync your products and reach customers across Facebook, Instagram, Messenger and WhatsApp through your Facebook plugin, which can be found at Marketing > Facebook.', 'facebook-for-woocommerce' );

		$note = new Note();
		$note->set_title( esc_html__( 'Facebook is now found under Marketing', 'facebook-for-woocommerce' ) );
		$note->set_content( $content );
		$note->set_content_data( (object) array() );
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'facebook-for-woocommerce' );
		$note->add_action( 'settings', esc_html__( 'Go to Facebook', 'facebook-for-woocommerce' ), $settings_url );
		return $note;
	}
}
