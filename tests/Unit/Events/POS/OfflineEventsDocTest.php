<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events\POS;

use WooCommerce\Facebook\Events\POS\POS_Integration_Interface;
use WooCommerce\Facebook\Events\POS\POS_Integration_Registry;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Keeps docs/offline-events.md in step with the bundled point-of-sale integrations.
 *
 * Adding an integration to POS_Integration_Registry::INTEGRATIONS without naming it
 * in the doc's "Supported point-of-sale systems" section fails this test, so the
 * list merchants read cannot fall behind what the plugin supports.
 */
class OfflineEventsDocTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var string the doc section that lists supported systems */
	private const SUPPORTED_SECTION = 'Supported point-of-sale systems';

	/**
	 * Reads the body of the doc's supported-systems section.
	 *
	 * @return string the section's text, from its heading to the next `## ` heading.
	 */
	private function supported_section(): string {
		$doc_path = facebook_for_woocommerce()->get_plugin_path() . '/docs/offline-events.md';
		$this->assertFileExists( $doc_path );

		$doc = (string) file_get_contents( $doc_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.

		$this->assertSame(
			1,
			preg_match( '/^## ' . preg_quote( self::SUPPORTED_SECTION, '/' ) . '\s*$(.*?)(?=^## |\z)/ms', $doc, $matches ),
			'docs/offline-events.md must keep a "## ' . self::SUPPORTED_SECTION . '" section; this test reads the supported systems from it.'
		);

		return $matches[1];
	}

	public function test_every_bundled_integration_is_named_in_the_supported_section() {
		$section = $this->supported_section();

		foreach ( POS_Integration_Registry::INTEGRATIONS as $class_name ) {
			$integration = new $class_name();
			$this->assertInstanceOf( POS_Integration_Interface::class, $integration );

			$this->assertStringContainsString(
				$integration->get_name(),
				$section,
				sprintf(
					'%s (%s) is bundled in POS_Integration_Registry::INTEGRATIONS but not named in the "%s" section of docs/offline-events.md. Add it there.',
					$integration->get_name(),
					$class_name,
					self::SUPPORTED_SECTION
				)
			);
		}
	}

	public function test_a_name_mentioned_only_elsewhere_in_the_doc_does_not_count() {
		// Guards the check itself. The doc's example class appears only under "Adding a
		// point-of-sale integration"; if the section were not cut off at the next
		// heading, a name mentioned anywhere in the doc would satisfy the check above.
		$section = $this->supported_section();

		$this->assertStringNotContainsString( 'My_POS_Integration', $section );
		$this->assertStringNotContainsString( '## ', $section, 'The section must stop at the next heading.' );
	}
}
