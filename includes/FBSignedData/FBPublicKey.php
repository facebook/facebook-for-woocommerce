<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\FBSignedData;

defined( 'ABSPATH' ) || exit;

/**
 * Class to represent a public key retrieved from FB.
 */
class FBPublicKey {

	const ENCODING_FORMAT_PEM = 'PEM';
	const ENCODING_FORMAT_HEX = 'HEX';

	const ALGORITHM_ES256 = 'ES256';
	const ALGORITHM_EDDSA = 'EdDSA';

	/**
	 * The public key string to be used to verify data signed by Meta.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * The encoding format for the key string. Either hex encoded or in pem file format.
	 *
	 * @var string
	 */
	private string $encoding_format;

	/**
	 * The project containing the rotating key. A current key will rotate to the next key in a project.
	 *
	 * @var string
	 */
	private string $project;


	/**
	 * The signing algorithm that the key is used for.
	 *
	 * @var string
	 */
	private string $algorithm;

	public function __construct( string $key_string, string $algorithm, string $encoding_format, string $project ) {
		$this->key             = $key_string;
		$this->algorithm       = $algorithm;
		$this->encoding_format = $encoding_format;
		$this->project         = $project;
	}

	public function get_key(): string {
		return $this->key;
	}

	public function get_algorithm(): string {
		return $this->algorithm;
	}

	public function get_encoding_format(): string {
		return $this->encoding_format;
	}

	public function get_project(): string {
		return $this->project;
	}
}
