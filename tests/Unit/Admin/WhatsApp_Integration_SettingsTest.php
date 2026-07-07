<?php
namespace WooCommerce\Facebook\Tests\Admin;

use WooCommerce\Facebook\Admin\WhatsApp_Integration_Settings;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * @package WooCommerce\Facebook\Tests\Unit\Admin
 */
class WhatsApp_Integration_SettingsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    private function build_settings(): WhatsApp_Integration_Settings {
        return $this->getMockBuilder( WhatsApp_Integration_Settings::class )
            ->disableOriginalConstructor()
            ->onlyMethods( [] )
            ->getMock();
    }

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

    public function test_render_message_handler_handles_wa_connect_onboarding_complete() {
        $output = $this->build_settings()->generate_inline_enhanced_onboarding_script();

        $this->assertStringContainsString( 'CommerceExtension::WA_CONNECT', $output );
        $this->assertStringContainsString( 'whatsAppAPI.notifyWhatsAppOnboardingComplete', $output );
    }

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

    public function test_render_message_handler_guards_non_object_event_data() {
        $output = $this->build_settings()->generate_inline_enhanced_onboarding_script();

        $this->assertStringContainsString( "typeof message !== 'object'", $output );
    }
}
