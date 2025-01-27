<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Netflie\WhatsAppCloudApi\Message\Template\Component;
use BrazilPOANorth\ReferralManager\ReferralManager;
use BrazilPOANorth\ReferralManager\ReferenceType;

class Referral_Manager_Actions_After_Submit extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    /**
	 * Get action name.
	 *
	 * Retrieve Referral Manager Actions name
	 *
	 * @since 0.1.0
	 * @access public
	 * @return string
	 */
	public function get_name() {
		return 'referral_manager_actions';
	}

    /**
	 * Get action label.
	 *
	 * Retrieve Referral Manager Actions action label.
	 *
	 * @since 0.1.0
	 * @access public
	 * @return string
	 */
	public function get_label() {
		return esc_html__( 'Referral Manager Actions', 'referral_manager' );
	}

    /**
	 * Run action.
	 *
	 * Submit form data to Referral Manager, send whatsapp messages, and send information to an external webhook if enabled
	 *
	 * @since 0.1.0
	 * @access public
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run( $record, $ajax_handler ) {
		
		$settings = $record->get( 'form_settings' );

		// Get submitted form data.
		$raw_fields = $record->get( 'fields' );

		// Normalize form data.
		$fields = [];
		foreach ( $raw_fields as $id => $field ) {
			$fields[ $id ] = $field['value'];
		}

		$name = $fields[ $settings['referral_manager_name_field'] ];
		$phone = $fields[ $settings['referral_manager_phone_field'] ];
		$email = $fields[ $settings['referral_manager_email_field'] ];
		$zip = $fields[ $settings['referral_manager_zip_field'] ];
		$road = $fields[ $settings['referral_manager_road_field'] ];
		$housenumber = $fields[ $settings['referral_manager_housenumber_field'] ];
		$city = $fields[ $settings['referral_manager_city_field'] ];
		$utm = $fields[ $settings['referral_manager_utm_field'] ];
		$referral_type = (int) $settings['referral_manager_referral_type'];

		$address = $road . ' ' . $housenumber . ', ' . $city . ' ' . $zip;

		as_enqueue_async_action('referral_manager_handle_referral', array(
			[
				'name' => $name,
				'email' => $email,
				'phone' => $phone,
				'address' => $address,
				'utm' => $utm,
				'referral_type' => $referral_type,
				'submission_date' => current_datetime()->format('d/m/Y H:i:s')
			],
			FALSE
		));

	}

    /**
	 * Register action controls.
	 *
	 * Creates the control interface
	 *
	 * @since 0.1.0
	 * @access public
	 * @param \Elementor\Widget_Base $widget
	 */
	public function register_settings_section( $widget ) {

		//referral information
		$widget->start_controls_section(
			'section_referral_information',
			[
				'label' => esc_html__( 'Referral Information', 'referral_manager' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				]
			]
		);
		$widget->add_control(
			'referral_manager_name_field',
			[
				'label' => esc_html__( 'Name Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		$widget->add_control(
			'referral_manager_phone_field',
			[
				'label' => esc_html__( 'Phone Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		$widget->add_control(
			'referral_manager_email_field',
			[
				'label' => esc_html__( 'Email Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		$widget->add_control(
			'referral_manager_zip_field',
			[
				'label' => esc_html__( 'ZIP Code Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		$widget->add_control(
			'referral_manager_road_field',
			[
				'label' => esc_html__( 'Road Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		$widget->add_control(
			'referral_manager_housenumber_field',
			[
				'label' => esc_html__( 'House Number Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		/* $widget->add_control(
			'referral_manager_neighborhood_field',
			[
				'label' => esc_html__( 'Neighborhood Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		); */

		$widget->add_control(
			'referral_manager_city_field',
			[
				'label' => esc_html__( 'City Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		/* $widget->add_control(
			'referral_manager_state_field',
			[
				'label' => esc_html__( 'State Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		); */

		$widget->add_control(
			'referral_manager_utm_field',
			[
				'label' => esc_html__( 'UTM Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		$widget->add_control(
			'referral_manager_referral_type',
			[
				'label' => esc_html__( 'Referral Type', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => array_column(ReferenceType::cases(), 'name', 'value')
			]
		);

		$widget->end_controls_section();
	}

    /**
	 * On export.
	 *
	 * Clear unwanted settings fields on export
	 *
	 * @since 0.1.0
	 * @access public
	 * @param array $element
	 */
	public function on_export( $element ) {
	}
}