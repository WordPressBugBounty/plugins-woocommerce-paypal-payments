<?php
/**
 * REST endpoint to manage the common module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Settings\Data\CommonSettings;

/**
 * REST controller for "common" settings, which are used and modified by
 * multiple components. Those settings mainly define connection details.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model.
 */
class CommonRestEndpoint extends RestEndpoint {
	/**
	 * The base path for this REST controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'common';

	/**
	 * The settings instance.
	 *
	 * @var CommonSettings
	 */
	protected CommonSettings $settings;

	/**
	 * Field mapping for request to profile transformation.
	 *
	 * @var array
	 */
	private array $field_map = array(
		'use_sandbox'           => array(
			'js_name'  => 'useSandbox',
			'sanitize' => 'to_boolean',
		),
		'use_manual_connection' => array(
			'js_name'  => 'useManualConnection',
			'sanitize' => 'to_boolean',
		),
		'client_id'             => array(
			'js_name'  => 'clientId',
			'sanitize' => 'sanitize_text_field',
		),
		'client_secret'         => array(
			'js_name'  => 'clientSecret',
			'sanitize' => 'sanitize_text_field',
		),
		'webhooks'              => array(
			'js_name' => 'webhooks',
		),
	);

	/**
	 * Map merchant details to JS names.
	 *
	 * @var array
	 */
	private array $merchant_info_map = array(
		'merchant_connected' => array(
			'js_name' => 'isConnected',
		),
		'sandbox_merchant'   => array(
			'js_name' => 'isSandbox',
		),
		'merchant_id'        => array(
			'js_name' => 'id',
		),
		'merchant_email'     => array(
			'js_name' => 'email',
		),
	);

	/**
	 * Map woo-settings to JS names.
	 *
	 * @var array
	 */
	private array $woo_settings_map = array(
		'country'  => array(
			'js_name' => 'storeCountry',
		),
		'currency' => array(
			'js_name' => 'storeCurrency',
		),
	);

	/**
	 * Constructor.
	 *
	 * @param CommonSettings $settings The settings instance.
	 */
	public function __construct( CommonSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_details' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_details' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			"/$this->rest_base/merchant",
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_merchant_details' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Returns all common details from the DB.
	 *
	 * @return WP_REST_Response The common settings.
	 */
	public function get_details() : WP_REST_Response {
		$js_data = $this->sanitize_for_javascript(
			$this->settings->to_array(),
			$this->field_map
		);

		$extra_data = $this->add_woo_settings( array() );
		$extra_data = $this->add_merchant_info( $extra_data );

		return $this->return_success( $js_data, $extra_data );
	}

	/**
	 * Updates common details based on the request.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response The new common settings.
	 */
	public function update_details( WP_REST_Request $request ) : WP_REST_Response {
		$wp_data = $this->sanitize_for_wordpress(
			$request->get_params(),
			$this->field_map
		);

		$this->settings->from_array( $wp_data );
		$this->settings->save();

		return $this->get_details();
	}

	/**
	 * Returns only the (read-only) merchant details from the DB.
	 *
	 * @return WP_REST_Response Merchant details.
	 */
	public function get_merchant_details() : WP_REST_Response {
		$js_data    = array(); // No persistent data.
		$extra_data = $this->add_merchant_info( array() );

		return $this->return_success( $js_data, $extra_data );
	}

	/**
	 * Appends the "merchant" attribute to the extra_data collection, which
	 * contains details about the merchant's PayPal account, like the merchant ID.
	 *
	 * @param array $extra_data Initial extra_data collection.
	 *
	 * @return array Updated extra_data collection.
	 */
	protected function add_merchant_info( array $extra_data ) : array {
		$extra_data['merchant'] = $this->sanitize_for_javascript(
			$this->settings->to_array(),
			$this->merchant_info_map
		);

		$extra_data['merchant'] = apply_filters(
			'woocommerce_paypal_payments_rest_common_merchant_data',
			$extra_data['merchant'],
		);

		return $extra_data;
	}

	/**
	 * Appends the "wooSettings" attribute to the extra_data collection to
	 * provide WooCommerce store details, like the store country and currency.
	 *
	 * @param array $extra_data Initial extra_data collection.
	 *
	 * @return array Updated extra_data collection.
	 */
	protected function add_woo_settings( array $extra_data ) : array {
		$extra_data['wooSettings'] = $this->sanitize_for_javascript(
			$this->settings->get_woo_settings(),
			$this->woo_settings_map
		);

		return $extra_data;
	}
}