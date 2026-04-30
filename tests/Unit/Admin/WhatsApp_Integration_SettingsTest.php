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

    public function test_render_message_handler_enforces_origin_allowlist() {
        $output = $this->build_settings()->generate_inline_enhanced_onboarding_script();

        $this->assertStringContainsString( '"https:\/\/www.commercepartnerhub.com"', $output );
        // `www.facebook.com` / `business.facebook.com` were trusted by PR #3913
        // but are no longer in the defaults — they must NOT leak into the emitted JS.
        $this->assertStringNotContainsString( '"https:\/\/www.facebook.com"', $output );
        $this->assertStringNotContainsString( '"https:\/\/business.facebook.com"', $output );
        $this->assertStringContainsString( 'STATIC_ALLOWED_ORIGINS', $output );
        $this->assertStringContainsString( 'OD_ALLOWED_BASE_LABELS', $output );
        $this->assertStringContainsString( 'function isAllowedOrigin(', $output );
        $this->assertStringContainsString( 'isAllowedOrigin(event.origin)', $output );

        $origin_guard_pos = strpos( $output, 'isAllowedOrigin(event.origin)' );
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

    /**
     * The validator must use parsed-URL label-by-label matching, not endsWith
     * or substring matching against the raw origin. See SEV S649287.
     */
    public function test_render_message_handler_avoids_endswith_origin_validation() {
        $output = $this->build_settings()->generate_inline_enhanced_onboarding_script();

        $this->assertStringNotContainsString( '.endsWith(', $output );
        $this->assertStringNotContainsString( "indexOf('.commercepartnerhub", $output );
        $this->assertStringNotContainsString( "indexOf('.od.", $output );
        $this->assertStringContainsString( 'new URL(origin)', $output );
    }

    /**
     * Facebook on-demand instances (`www.<id>.od.<base>`) must be supported
     * out of the box for `commercepartnerhub.com`.
     */
    public function test_render_message_handler_supports_on_demand_origins() {
        $output = $this->build_settings()->generate_inline_enhanced_onboarding_script();

        $this->assertStringContainsString( '[["commercepartnerhub","com"]]', $output );
    }
}
