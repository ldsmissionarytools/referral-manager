<?php
/**
 * Referral Manager
 *
 * @package           ReferralManager
 * @author            Corban Thompson
 * @copyright         2024 Brazil Porto Alegre North Mission
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Referral Manager
 * Description:       Adds additional submit actions to Elementor forms to integrate with various mission tools
 * Version:           0.1.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Corban Thompson
 * Author URI:        https://github.com/corbant
 * Text Domain:       referral-manager
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://example.com/my-plugin/
 * Requires Plugins:  elementor
 */

require_once( plugin_dir_path(__FILE__) . 'lib/autoload.php');
require_once(plugin_dir_path(__FILE__) . 'src/ReferralManager.php');

use BrazilPOANorth\ReferralManager\ReferralManager;

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

	include_once( plugin_dir_path(__FILE__) .  'form-actions/referral-manager-actions.php' );

	$form_actions_registrar->register( new \Referral_Manager_Actions_After_Submit() );

}

add_action( 'elementor_pro/forms/actions/register', 'add_new_referral_manager_action' );