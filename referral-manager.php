<?php
/**
 * Referral Manager
 *
 * @package           ReferralManager
 * @author            Corban Thompson
 * @copyright         2025 Corban Thompson
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Referral Manager
 * Description:       Adds additional submit actions to Elementor forms to integrate with various mission tools
 * Version:           0.2.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Corban Thompson
 * Author URI:        https://github.com/corbant
 * Text Domain:       referral-manager
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://example.com/my-plugin/
 * Requires Plugins:  elementor, action-scheduler
 */

require_once(plugin_dir_path(__FILE__) . 'lib/autoload.php');
require_once(plugin_dir_path(__FILE__) . 'src/ReferralManager.php');
require_once(plugin_dir_path(__FILE__) . 'src/Updater.php');

use ReferralManager\ReferralManager;
use Referralmanager\Updater;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Netflie\WhatsAppCloudApi\Message\Template\Component;
use FacebookAds\Api;
use FacebookAds\Object\Lead;
use FacebookAds\Object\Fields\LeadFields;
use Twilio\Rest\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add action to create reference in Preach My Gospel App after form submission.
 *
 * @since 0.1.0
 * @param ElementorPro\Modules\Forms\Registrars\Form_Actions_Registrar $form_actions_registrar
 * @return void
 */
function add_new_referral_manager_action( $form_actions_registrar ) {

	include_once( plugin_dir_path(__FILE__) . 'form-actions/referral-manager-actions.php' );

	$form_actions_registrar->register( new \Referral_Manager_Actions_After_Submit() );

}

add_action( 'elementor_pro/forms/actions/register', 'add_new_referral_manager_action' );


function referral_manager_pages() {
    add_menu_page(
        'referral_manager',
        'Referral Manager',
        'administrator',
        'referral_manager',
        null,
        'data:image/svg+xml;base64,' . base64_encode(file_get_contents(plugin_dir_url(__FILE__) . 'images/icon_referral_manager.svg')),
        99
    );

	add_submenu_page( 'referral_manager', "referral_manager", esc_html__('Dashboard', 'referral_manager'), 'administrator', 'referral_manager', function() {
		require plugin_dir_path(__FILE__) . 'admin/index.php';
	} );

	add_submenu_page( 'referral_manager', "settings", esc_html__('Settings', 'referral_manager'), 'manage_options', 'referral_manager_settings', function() {
		require plugin_dir_path(__FILE__) . 'admin/settings.php';
	} );
}

//add admin page
add_action( 'admin_menu', 'referral_manager_pages' );

function referral_manager_settings_init() {
	register_setting('referral_manager', 'referral_manager_api_settings', array(
		'type' => 'array',
		'default' => array(
			'username' => '',
			'password' => '',
			'sec_email' => '',
			'mission_id' => 0
		)
	));
	register_setting('referral_manager', 'facebook_api_settings', array(
		'type' => 'array',
		'default' => array(
			'app_id' => '',
			'app_secret' => '',
			'access_token' => '',
			'hub_verify_token' => 'referralmanagerverifytoken'
		)
	));
	register_setting('referral_manager', 'whatsapp_settings', array(
		'type' => 'array',
		'default' => array(
			'access_token' => '',
			'phone_number_id' => ''
		)
	));
	register_setting('referral_manager', 'twilio_settings', array(
		'type' => 'array',
		'default' => array(
			'account_sid' => '',
			'auth_token' => '',
			'phone_number' => ''
		)
	));
	register_setting('referral_manager', 'rm_urls', array(
		'type' => 'array',
		'default' => array(
			'ad_info_url' => '',
			'webhook_url' => ''
		)
	));

	//Referral Manager settings section
	add_settings_section('referral_manager_pmg_settings_section', esc_html__('Referral Manager Settings', 'referral_manager'), null, 'referral_manager_settings');

	//Username
	add_settings_field('referral_manager_username_settings_field', esc_html__('Username', 'referral_manager') . ' (chruchofjesuschrist.org)', function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('referral_manager_api_settings')['username'];
		// output the field
		?>
		<input type="text" name="referral_manager_api_settings[username]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'referral_manager_pmg_settings_section');

	//Password
	add_settings_field('referral_manager_password_settings_field', esc_html__('Password', 'referral_manager') . ' (chruchofjesuschrist.org)', function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('referral_manager_api_settings')['password'];
		// output the field
		?>
		<input type="password" name="referral_manager_api_settings[password]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'referral_manager_pmg_settings_section');

	//Media Sec Email
	add_settings_field('referral_manager_sec_email_settings_field', esc_html__('Media Secretary Missionary Email', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('referral_manager_api_settings')['sec_email'];
		// output the field
		?>
		<input type="email" name="referral_manager_api_settings[sec_email]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'referral_manager_pmg_settings_section');

	//Mission ID
	add_settings_field('referral_manager_mission_id_settings_field', esc_html__('Mission ID Number', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('referral_manager_api_settings')['mission_id'];
		// output the field
		?>
		<input type="number" name="referral_manager_api_settings[mission_id]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'referral_manager_pmg_settings_section');


	//Whatsapp API settings section
	add_settings_section('facebook_api_settings_section', esc_html__('Facebook API Settings', 'referral_manager'), null, 'referral_manager_settings');

	//Facebook App ID
	add_settings_field('referral_manager_facebook_app_id_settings_field', esc_html__('Facebook API App ID', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('facebook_api_settings')['app_id'];
		// output the field
		?>
		<input type="text" name="facebook_api_settings[app_id]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'facebook_api_settings_section');

	//Facebook App Secret
	add_settings_field('referral_manager_facebook_app_secret_settings_field', esc_html__('Facebook App Secret', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('facebook_api_settings')['app_secret'];
		// output the field
		?>
		<input type="password" name="facebook_api_settings[app_secret]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'facebook_api_settings_section');

	//Facebook Access Token
	add_settings_field('referral_manager_facebook_token_settings_field', esc_html__('Facebook Access Token', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('facebook_api_settings')['access_token'];
		// output the field
		?>
		<input type="password" name="facebook_api_settings[access_token]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'facebook_api_settings_section');

	//Facebook verify token
	add_settings_field('referral_manager_facebook_hub_verify_token_field', esc_html__('Facebook Webhook Hub Verify Token', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('facebook_api_settings')['hub_verify_token'];
		// output the field
		?>
		<input type="text" name="facebook_api_settings[hub_verify_token]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'facebook_api_settings_section');

	//Whatsapp settings section
	add_settings_section('whatsapp_settings_section', esc_html__('Whatsapp Settings', 'referral_manager'), null, 'referral_manager_settings');

	//Whatsapp Access Token
	add_settings_field('referral_manager_whatsapp_access_token_settings_field', esc_html__('Whatsapp Access Token', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('whatsapp_settings')['access_token'];
		// output the field
		?>
		<input type="text" name="whatsapp_settings[access_token]" value="<?php echo esc_attr( $setting ) ?>">
		<?php
	}, 'referral_manager_settings', 'whatsapp_settings_section');

	//Whatsapp Phone Number ID
	add_settings_field('referral_manager_whatsapp_phone_number_id_settings_field', esc_html__('Whatsapp Phone Number ID', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('whatsapp_settings')['phone_number_id'];
		// output the field
		?>
		<input type="text" name="whatsapp_settings[phone_number_id]" value="<?php echo esc_attr( $setting ) ?>">
		<?php
	}, 'referral_manager_settings', 'whatsapp_settings_section');

	//Twilio settings section
	add_settings_section('twilio_settings_section', esc_html__('Twilio Settings', 'referral_manager'), null, 'referral_manager_settings');

	//Twilio Account SID
	add_settings_field('referral_manager_twilio_sid_settings_field', esc_html__('Twilio Account SID', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('twilio_settings')['account_sid'];
		// output the field
		?>
		<input type="text" name="twilio_settings[account_sid]" value="<?php echo esc_attr( $setting ) ?>">
		<?php
	}, 'referral_manager_settings', 'twilio_settings_section');

	//Twilio Auth Token
	add_settings_field('referral_manager_twilio_auth_token_settings_field', esc_html__('Twilio Auth Token', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('twilio_settings')['auth_token'];
		// output the field
		?>
		<input type="password" name="twilio_settings[auth_token]" value="<?php echo esc_attr( $setting ) ?>">
		<?php
	}, 'referral_manager_settings', 'twilio_settings_section');

	//Twilio Phone Number
	add_settings_field('referral_manager_twilio_phone_number_settings_field', esc_html__('Twilio Phone Number', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('twilio_settings')['phone_number'];
		// output the field
		?>
		<input type="text" name="twilio_settings[phone_number]" value="<?php echo esc_attr( $setting ) ?>">
		<?php
	}, 'referral_manager_settings', 'twilio_settings_section');
	

	//Facebook webhook settings
	add_settings_section('url_settings_section', esc_html__('URL Settings', 'referral_manager'), null, 'referral_manager_settings');

	//Ad URL
	add_settings_field('referral_manager_ad_info_url_field', esc_html__('Ad Info URL', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('rm_urls')['ad_info_url'];
		// output the field
		?>
		<input type="text" name="rm_urls[ad_info_url]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'url_settings_section');

	//Webhook URL
	add_settings_field('referral_manager_webhook_settings_field', esc_html__('Webhook URL', 'referral_manager'), function() {
		// get the value of the setting we've registered with register_setting()
		$setting = get_option('rm_urls')['webhook_url'];
		// output the field
		?>
		<input type="text" name="rm_urls[webhook_url]" value="<?php echo esc_attr( $setting ) ?>">
    	<?php
	}, 'referral_manager_settings', 'url_settings_section');
	
}

add_action('admin_init', 'referral_manager_settings_init');

function referral_manager_facebook_webhooks_POST(\WP_REST_Request $request) {
	$body = $request->get_params();

	// Checks this is an event from a page subscription
	if ($body['object'] === 'page') {

		// Iterates over each entry - there may be multiple if batched
		foreach ($body['entry'] as $entry) {

			// Gets the leadgen_id to get the details of the lead
			$lead_id = $entry['changes'][0]['value']['leadgen_id'];
			//TODO: do stuff here
			$facebook_api_settings = get_option( 'facebook_api_settings' );
			$app_id = $facebook_api_settings['app_id'];
			$app_secret = $facebook_api_settings['app_secret'];
			$access_token = $facebook_api_settings['access_token'];

			Api::init($app_id, $app_secret, $access_token);

			$lead = (new Lead($lead_id))->getSelf(array(LeadFields::AD_NAME, LeadFields::FIELD_DATA))->getData();

			$fields = array();
			foreach ($lead['field_data'] as $field) {
    			$fields[$field['name']] = $field['values'][0];
			}

			$street = array_key_exists('street_address', $fields) ? $fields['street_address'] : $fields['street address'];
			$name = array_key_exists('full_name', $fields) ? $name = $fields['full_name'] : $fields['full name'];
			
			$address = $street . ', ' . $fields['city'] . ' ' . $fields['state'] . ' ' . $fields['post_code'];
			$utm = preg_match('/\[([A-Z]\w+)\]/', $lead['ad_name'], $matches) ? $matches[1] : '';
			$email = array_key_exists('email', $fields) ? $fields['email'] : '';
			$phone = $fields['phone_number'];

			as_enqueue_async_action( 'referral_manager_handle_referral', array(
				[
					'name' => $name,
					'email' => $email,
					'address' => $address,
					'utm' => $utm,
					'phone' => $phone,
					'submission_date' => current_datetime()->format('d/m/Y H:i:s')
				],
				TRUE
			));
		}

		// Returns a '200 OK' response to all requests
		return 'EVENT_RECEIVED';
	} else {
		// Returns a '404 Not Found' if event is not from a page subscription
		return new WP_Error(404); 
	}
}

function referral_manager_facebook_webhooks_GET(\WP_REST_Request $request) {
	$verify_token = get_option( 'facebook_api_settings')['hub_verify_token'];

	// Parse the query params
	$mode = $request['hub_mode'];
	$token = $request['hub_verify_token'];
	$challenge = $request['hub_challenge'];

	// Checks if a token and mode is in the query string of the request
	if ($mode && $token) {
	
		// Checks the mode and token sent is correct
		if ($mode == 'subscribe' && $token == $verify_token) {
		
			// Responds with the challenge token from the request
			return (int) $challenge;
		}
	}
	// Responds with '403 Forbidden' if verify tokens do not match
	return new WP_Error(403);  

}

add_action( 'rest_api_init', function () {
	register_rest_route( 'referralmanager/v1', '/facebook_webhooks', array(
		array(
			'methods' => 'GET',
			'callback' => 'referral_manager_facebook_webhooks_GET',
			'permission_callback' => '__return_true',
		),
		array(
			'methods' => 'POST',
			'callback' => 'referral_manager_facebook_webhooks_POST',
			'permission_callback' => '__return_true',
		)
	) );
} );

function referral_manager_handle_referral($referral_info, $from_facebook) {

	$name = $referral_info['name'];
	$address = $referral_info['address'];
	$phone = $referral_info['phone'];
	$email = $referral_info['email'];
	$utm = $referral_info['utm'];
	$referral_type = $referral_info['referral_type'];

	$ad_information_url = get_option('rm_urls')['ad_info_url'];

	$phone = preg_replace("/[^0-9]/", "", $phone);

	//register in PMG App
	$referral_manager = create_referral_manager();

	//get ad information
	if ($ad_information_url !== '') {
		$ad_information = json_decode(WpOrg\Requests\Requests::get($ad_information_url . '?' . http_build_query(['utm' => $utm]))->body, true);
	} else {
		$ad_information = [
			'name' => '',
			'description' => '',
			'url' => ''
		];
	}

	$area = $referral_manager->get_area_for_address($address);
	if ($area['bestProsAreaId'] == NULL) {
		$area_info = NULL;
	} else {
		$area_info = ReferralManager::format_area_info($area);
	}
	as_enqueue_async_action( 'whatsapp_message_and_webhook', array( $referral_info, $area_info, $ad_information, $from_facebook ));

	if (!$from_facebook) {
		try {
			$referral_manager->create_and_send_reference($name, '', $address, $phone, $email, $referral_type, $ad_information['description']);
		} catch(Exception $e) {
			//probably worked, the server just took a long time to respond
			return;
		}
	}
}

add_action( 'referral_manager_handle_referral', 'referral_manager_handle_referral', 10, 2 );

function send_whatsapp_messages($referral_info, $area_info, $ad_info) {
	//whatsapp
	$whatsapp_settings = get_option('whatsapp_settings');
	$whatsapp_phone_number_id = $whatsapp_settings['phone_number_id'];
	$whatsapp_access_token = $whatsapp_settings['access_token'];
	//twilio
	$twilio_settings = get_option('twilio_settings');
	$twilio_account_sid = $twilio_settings['account_sid'];
	$twilio_auth_token = $twilio_settings['auth_token'];
	$twilio_phone_number = $twilio_settings['phone_number'];

	//if the address is incorrect and doesn't find an area
	if($area_info == NULL) {
		return;
	}

	$name = $referral_info['name'];
	$address = $referral_info['address'];
	$phone = $referral_info['phone'];
	$area_name = $area_info['name'];
	$missionaries = $area_info['missionaries'];
	$missionary_phonenumbers = $area_info['phones'];

	//if whatsapp is configured
	if($whatsapp_access_token != '' && $whatsapp_phone_number_id != '') {

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
				'text' => $phone,
			],
			[
				'type' => 'text',
				'text' => $address,
			],
			[
				'type' => 'text',
				'text' => $referral_info['submission_date'],
			],
			[
				'type' => 'text',
				'text' => $ad_info['url'] == '' ? 'Sem Link' : $ad_info['url']
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
	else if ($twilio_phone_number != '' && $twilio_account_sid != '' && $twilio_auth_token != '') {
		$twilio = new Client($twilio_account_sid, $twilio_auth_token);

		//message to person
		/*$twilio->messages->create(
			$phone,
			[
				'from' => $twilio_phone_number,
				'body' => "Olá $name, sua referência foi recebida e os missionários $missionaries[0] e $missionaries[1] entrarão em contato com você em breve. O telefone deles é $missionary_phonenumbers[0]."
			]
		);*/

		//message to missionaries
		foreach ($missionary_phonenumbers as $number) {
			$twilio->messages->create(
				'+' . $number,
				[
					'from' => $twilio_phone_number,
					'body' => "Olá $area_name, vocês receberam uma nova referência!\nNome: $name\nTelefone: $phone\nEndereço: $address\nData de submissão: " . $referral_info['submission_date'] . "\nLink do anúncio: " . ($ad_info['url'] == '' ? 'Sem Link' : $ad_info['url'])
				]
			);
		}
	}
}

function output_to_webhook($referral_info, $area_info, $ad_info, $from_facebook) {
	$webhook_url = get_option('rm_urls')['webhook_url'];
	if($webhook_url == '') {
		return;
	}
	if ($area_info == NULL) {
		$area_name = '-';
		$missionary_phonenumber = '-';
	} else {
		$area_name = $area_info['name'];
		$missionary_phonenumber = $area_info['phones'][0];
	}
		$data = [
		'name' => $referral_info['name'],
		'email' => $referral_info['email'],
		'phone' => $referral_info['phone'],
		'address' => $referral_info['address'],
		'utm' => $ad_info['utm'],
		'description' => $ad_info['description'],
		'area' => $area_name,
		'missionaryPhone' => $missionary_phonenumber,
		'time' => $referral_info['submission_date'],
		'source' => $from_facebook ? 'Facebook/Instagram' : preg_replace('#^https?://#', '', get_site_url())
	];
	WpOrg\Requests\Requests::post($webhook_url . '?' . http_build_query($data));
}

add_action( 'whatsapp_message_and_webhook', 'send_whatsapp_messages', 10, 3 );
add_action( 'whatsapp_message_and_webhook', 'output_to_webhook', 9, 4 );

function assign_undesignated_referrals() {
	$referral_manager = create_referral_manager();
	
	$referral_manager->assign_referrals();
}

add_action( 'rm_assign_referrals', 'assign_undesignated_referrals', 10, 0);



register_activation_hook( __FILE__, 'referral_manager_plugin_activation' );

function referral_manager_plugin_activation() {
	if ( ! as_has_scheduled_action( 'rm_assign_referrals' ) ) {
        as_schedule_recurring_action( time() + 1800, 1800, 'rm_assign_referrals' );
    }
}

register_deactivation_hook( __FILE__, 'referral_manager_plugin_deactivation' );

function referral_manager_plugin_deactivation() {
    as_unschedule_all_actions( 'rm_assign_referrals' );
}


function create_referral_manager() {
	$referral_manager_settings = get_option('referral_manager_api_settings');
	$church_account_username = $referral_manager_settings['username'];
	$church_account_password = $referral_manager_settings['password'];
	$media_sec_email = $referral_manager_settings['sec_email'];
	$mission_id = $referral_manager_settings['mission_id'];
	$referral_manager = new ReferralManager($church_account_username, $church_account_password, $media_sec_email, $mission_id);

	return $referral_manager;
}

// Registers updater
if ( is_admin() ) {
    if( ! function_exists( 'get_plugin_data' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $plugin_data = get_plugin_data( __FILE__, false, false );

	new Updater(plugin_basename( __DIR__ ), $plugin_data['Version']);
}
