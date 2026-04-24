<?php
namespace WooCommerce\Facebook\Tests\Admin;

use WooCommerce\Facebook\Admin\WhatsApp_Integration_Settings;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class WhatsApp_Integration_SettingsTest
 *
 * Covers the rendered inline postMessage handler script. Regression coverage
 * for SEV S653961 — the handler previously dispatched on event.data without
 * validating event.origin, which let any page the logged-in admin visited
 * overwrite stored WhatsApp integration credentials (access_token, waba_id,
 * phone_number_id, etc.) via a targeted postMessage.
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin
 */
class WhatsApp_Integration_SettingsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * Build an instance without running the constructor (which registers
     * admin hooks we don't want in a unit test). The method under test does
     * not read any instance state.
     */
    private function build_settings(): WhatsApp_Integration_Settings {
        return $this->getMockBuilder( WhatsApp_Integration_Settings::class )
            ->disableOriginalConstructor()
            ->onlyMethods( [] )
            ->getMock();
    }

    /**
     * The emitted script must still register the expected WA_* event handlers
     * and use the plugin API client.
     */
    public function test_render_message_handler_emits_expected_handlers() {
        $output = $this->build_settings()->generate_inline_enhanced_onboarding_script();

        $this->assertStringContainsString( "window.addEventListener('message'", $output );
        $this->assertStringContainsString( 'CommerceExtension::WA_INSTALL', $output );
        $this->assertStringContainsString( 'CommerceExtension::WA_RESIZE', $output );
        $this->assertStringContainsString( 'CommerceExtension::WA_UNINSTALL', $output );
        $this->assertStringContainsString( 'GeneratePluginAPIClient', $output );
        $this->assertStringContainsString( 'whatsAppAPI.updateWhatsAppSettings', $output );
        $this->assertStringContainsString( 'whatsAppAPI.uninstallWhatsAppSettings', $output );
    }

    /**
     * SEV S653961: event.origin MUST be checked against an explicit allowlist
     * before event.data is dereferenced. Any page the admin has open can fire
     * a message event at this window; without this guard the attacker controls
     * the payload.
     */
    public function test_render_message_handler_enforces_origin_allowlist() {
        $output = $this->build_settings()->generate_inline_enhanced_onboarding_script();

        $this->assertStringContainsString( "'https://www.commercepartnerhub.com'", $output );
        $this->assertStringContainsString( "'https://www.facebook.com'", $output );
        $this->assertStringContainsString( "'https://business.facebook.com'", $output );
        $this->assertStringContainsString( 'ALLOWED_ORIGINS.indexOf(event.origin) === -1', $output );

        $origin_guard_pos = strpos( $output, 'ALLOWED_ORIGINS.indexOf(event.origin)' );
        $data_access_pos  = strpos( $output, 'const message = event.data' );
        $this->assertNotFalse( $origin_guard_pos );
        $this->assertNotFalse( $data_access_pos );
        $this->assertLessThan(
            $data_access_pos,
            $origin_guard_pos,
            'Origin allowlist must be checked before event.data is read.'
        );
    }

    /**
     * Defense-in-depth: if an allowed origin posts a non-object payload (or
     * just does unrelated postMessage traffic) we must not throw trying to
     * read message.event.
     */
    public function test_render_message_handler_guards_non_object_event_data() {
        $output = $this->build_settings()->generate_inline_enhanced_onboarding_script();

        $this->assertStringContainsString( "typeof message !== 'object'", $output );
    }
}
