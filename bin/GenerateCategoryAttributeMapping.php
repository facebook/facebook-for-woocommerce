<?php

/**
 * Class GenerateCategoryAttributeMapping
 *
 * Optimizes the 18 MB `fb_google_category_to_attribute_mapping.json` file by extracting duplicate attribute field data
 * into two separate JSON files.
 *
 * `google_category_to_attribute_mapping.json` Is the same as the original file but attribute arrays have been replaced with hashes
 * `google_category_to_attribute_mapping_fields.json` Is a look-up file containing the attribute field array with hashes as keys.
 */
class GenerateCategoryAttributeMapping {

	const SOURCE_FILE_NAME = '/bin/fb_google_category_to_attribute_mapping.json';
	const EXPORT_CATEGORIES_FILE_NAME = '/data/google_category_to_attribute_mapping.json';
	const EXPORT_FIELDS_FILE_NAME = '/data/google_category_to_attribute_mapping_fields.json';

	/** @var array */
	protected $category_export = [];

	/** @var array */
	protected $field_export = [];

	/**
	 * Generate the category and field export json files.
	 */
	public function generate() {
		$plugin_root = dirname( __DIR__ );

		$source_data = json_decode( file_get_contents( $plugin_root . self::SOURCE_FILE_NAME ), true );

		foreach ( $source_data as $category_id => $category ) {
			foreach ( $category['attributes'] as &$attr ) {

				// hash the attribute array to determine unique entries
				$hash = md5( wp_json_encode( $attr ) );

				if ( ! isset( $unique_fields[ $hash ] ) ) {
					$this->field_export[ $hash ] = $attr;
				}

				$attr = $hash;
			}

			$this->category_export[ $category_id ] = $category;
		}

		file_put_contents( $plugin_root . $this::EXPORT_CATEGORIES_FILE_NAME, wp_json_encode( $this->category_export ) );
		file_put_contents( $plugin_root . $this::EXPORT_FIELDS_FILE_NAME, wp_json_encode( $this->field_export ) );

		echo sprintf(
			"Files successfully generated: \n %s \n %s \n",
			$plugin_root . $this::EXPORT_CATEGORIES_FILE_NAME,
			$plugin_root . $this::EXPORT_FIELDS_FILE_NAME
		);
	}


}

( new GenerateCategoryAttributeMapping() )->generate();
