<?php
//Only display the page if the user can manage options
if ( !current_user_can( 'manage_options' ) ) {
    return;
}
?>

<div class="wrap">
	<h1>
	<?php echo esc_html( "Settings", 'referral_manager'); ?>
	</h1>
    <form action="options.php" method="post">
        <?php
			// output security fields for the registered setting "wporg_options"
			settings_fields( 'referral_manager' );
			// output setting sections and their fields
			// (sections are registered for "wporg", each field is registered to a specific section)
			do_settings_sections( 'referral_manager_settings' );
			// output save settings button
			submit_button( __( 'Save Settings', 'referral_manager' ) );
		?>
    </form>
</div>