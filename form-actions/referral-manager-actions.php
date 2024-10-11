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
		$submission_date = date('d/m/Y h:i:s');

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
		$state = $fields[ $settings['referral_manager_state_field'] ];
		$utm = $fields[ $settings['referral_manager_utm_field'] ];
		$referral_type = (int) $settings['referral_manager_referral_type'];

		$address = $road . ' ' . $housenumber . ', ' . $city . ', ' . $state . ' ' . $zip;
		$phone = preg_replace("/[^0-9]/", "", $phone);

		$whatsapp_enabled = $settings['enable_whatsapp'];
		$webhook_enabled = $settings['enable_webhook'];

		$church_account_username = $settings['church_account_username'];
		$church_account_password = $settings['church_account_password'];
		$media_sec_email = $settings['media_sec_email'];
		$mission_id = $settings['mission_id'];
		$ad_information_url = $settings['ad_information_url'];
		

		//register in PMG App

		$referral_manager = new ReferralManager($church_account_username, $church_account_password, $media_sec_email, $mission_id);

		//get ad information
		if ($settings['include_ad_information'] === 'yes') {
			$ad_information = json_decode(WpOrg\Requests\Requests::get($ad_information_url . '?' . http_build_query(['utm' => $utm]))->body, true)['description'];
		} else {
			$ad_information = '';
		}

		$referral_manager->create_and_send_reference($name, '', $address, $phone, $email, $referral_type, $ad_information);

		$area_info = $referral_manager->get_area_for_address($address)['proselytingAreas'][0];
		$area_name = $area_info['name'];
		$missionaries = $area_info['missionaries'];
		foreach ($missionaries as &$missionary) {
			$missionary = ucfirst(strtolower($missionary['missionaryType'])) . ' ' . $missionary['lastName'];
		}

		$missionary_phonenumbers = $area_info['areaNumbers'];

		foreach ($missionary_phonenumbers as &$number) {
			$number = preg_replace("/[^0-9]/", "", $number);
		}
		

		//send Whatsapp messages
		if ($whatsapp_enabled === 'yes') {
			$whatsapp_phone_number_id = $fields['whatsapp_phone_number_id'];
			$whatsapp_access_token = $fields['whatsapp_access_token'];

			//create cloud api client
			$whatsapp_cloud_api = new WhatsAppCloudApi([
				'from_phone_number_id' => $whatsapp_phone_number_id,
				'access_token' => $whatsapp_access_token,
			]);

			//message to person
			$component_header = [];

			$component_body = [
				[
					'type' => 'text',
					'text' => $name,
				],
				[
					'type' => 'text',
					'text' => $missionaries[0] . ' e ' . $missionaries[1],
				],
				[
					'type' => 'text',
					'text' => $missionary_phonenumbers[0],
				]
			];

			$component_buttons = [];

			$components = new Component($component_header, $component_body, $component_buttons);
			$whatsapp_cloud_api->sendTemplate($phone, 'cadastro_feito_confirmacao', 'pt_BR', $components);

			//message to missionaries
			$component_header = [];

			$component_body = [
				[
					'type' => 'text',
					'text' => $area_name,
				],
				[
					'type' => 'text',
					'text' => $name,
				],
				[
					'type' => 'text',
					'text' => $number,
				],
				[
					'type' => 'text',
					'text' => $address,
				],
				[
					'type' => 'text',
					'text' => $submission_date,
				],
				[
					'type' => 'text',
					'text' => $settings['include_ad_information'] === 'yes' ? $ad_information['url'] : 'Sem Link'
				],
			];

			$component_buttons = [
				[
					'type' => 'button',
					'sub_type' => 'URL',
					'index' => 0,
					'parameters' => [
						[
							'type' => 'text',
							'text' => urlencode($address),
						]
					]
				]
			];

			$components = new Component($component_header, $component_body, $component_buttons);
			foreach ($missionary_phonenumbers as $number) {
				$whatsapp_cloud_api->sendTemplate($number, 'missionario_referencia_recebida', 'pt_BR', $components);
			}
		}

		//send information to webhook
		if($webhook_enabled === 'yes') {
			$referral_manager_webhook = $settings['referral_manager_webhook'];
			$data = [
				'name' => $name,
				'email' => $email,
				'phone' => $phone,
				'address' => $address,
				'utm' => $utm,
				'area' => $area_name,
				'missionaryPhone' => $missionary_phonenumbers,
				'time' => $submission_date
			];

			WpOrg\Requests\Requests::post($referral_manager_webhook . '?' . http_build_query($data));

		}

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

		$widget->add_control(
			'referral_manager_neighborhood_field',
			[
				'label' => esc_html__( 'Neighborhood Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		$widget->add_control(
			'referral_manager_city_field',
			[
				'label' => esc_html__( 'City Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		$widget->add_control(
			'referral_manager_state_field',
			[
				'label' => esc_html__( 'State Field ID', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

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
		
		//settings controls
		$widget->start_controls_section(
			'section_referral_manager_settings',
			[
				'label' => esc_html__( 'Referral Manager Settings', 'referral_manager' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				]
			]
		);

		$widget->add_control(
			'church_account_username',
			[
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'username',
				'description' => esc_html__( 'churchofjesuschrist.org username', 'referral_manager')
			]
		);

		$widget->add_control(
			'church_account_password',
			[
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'password',
				'description' => esc_html__( 'churchofjesuschrist.org password', 'referral_manager'),
				'input_type' => 'password'
			]
		);

		$widget->add_control(
			'media_sec_email',
			[
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'missionary.name@missionary.org',
				'description' => esc_html__( 'Media Secretary missionary email address', 'referral_manager'),
				'input_type' => 'email'
			]
		);

		$widget->add_control(
			'mission_id',
			[
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '11111',
				'description' => esc_html__( 'Mission ID number', 'referral_manager'),
				'input_type' => 'number'
			]
		);

		$widget->add_control(
			'include_ad_information',
			[
				'label' => esc_html__( 'Include Ad Information', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes'
			]
		);

		$widget->add_control(
			'ad_information_url',
			[
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'https://info-url.com',
				'description' => esc_html__( 'Ad information url', 'referral_manager'),
				'input_type' => 'url',
				'condition' => [
					'include_ad_information' => 'yes',
				]
			]
		);

		$widget->add_control(
			'enable_whatsapp',
			[
				'label' => esc_html__( 'Enable Whatsapp Messages', 'referral_manager' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes'
			]
		);

		$widget->add_control(
			'whatsapp_phone_number_id',
			[
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'Whatsapp phone number id...',
				'description' => esc_html__( 'Enter your Whatsapp API phone number id', 'referral_manager'),
				'condition' => [
					'enable_whatsapp' => 'yes',
				]
			]
		);

		$widget->add_control(
			'whatsapp_access_token',
			[
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'Whatsapp access token...',
				'description' => esc_html__( 'Enter your Whatsapp API access token', 'referral_manager'),
				'condition' => [
					'enable_whatsapp' => 'yes',
				]
			]
		);

		$widget->add_control(
			'enable_webhook',
			[
				'label' => esc_html__( 'Enable Webhook' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$widget->add_control(
			'referral_manager_webhook',
			[
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'https://webhook-url.com',
				'description' => esc_html__( 'Enter the URL of your webhook.', 'referral_manager'),
				'input_type' => 'url',
				'condition' => [
					'enable_webhook' => 'yes',
				]
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
		unset(
			$element['whatsapp_phone_number_id'],
			$element['whatsapp_access_token'],
			$element['mission_id'],
			$element['media_sec_email'],
			$element['church_account_username'],
			$element['church_account_password'],
		);
	}
}