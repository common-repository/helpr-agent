<?php
/*
	Plugin Name: Helpr Agent
	Plugin URI: https://wordpress.org/plugins/helpr-agent/
	Description: Helpr Agent will allow website administrators to be always in touch with developers. You are just one click away to get the proper assistance when needed. try Helpr!
	Version: 1.0
	Author: PixelDrops
	Author URI: http://www.pixeldrops.world
	License: GPLv2 and later
	Text Domain: helpr-agent-domain
*/

add_action('wp_ajax_send_helprequest_messsage', array('Helpr_Agent','send_helprequest_messsage'));

class Helpr_Agent {

	/**
	* The id of this widget.
	*/
	const wid = 'helpr_agent';
	public static $configuration;
	public static $plugin_dir;

	public static function init()
	{
		add_option('helpr_agent_options');
		self::$plugin_dir = basename(dirname(__FILE__));
		self::$configuration = get_option( 'helpr_agent_options' );
		load_plugin_textdomain( 'helpr-agent-domain', false, self::$plugin_dir );
		add_action('wp_dashboard_setup', array('Helpr_Agent','init_widget'));
		add_action('admin_menu', array('Helpr_Agent','register_request_help_menu_option') );
		add_action('admin_enqueue_scripts', array('Helpr_Agent','register_scripts'));
	}

	public static function register_scripts()
	{
		wp_register_style('helpr_agent_styles', plugins_url('css/helpr-agent.css', __FILE__) );
		wp_enqueue_style('helpr_agent_styles');

		//replace the menu link to the helprequest page by a dialog
	}


	public static function init_widget() {
		$widget_title = ( isset(self::$configuration['widget_title']) ) ? esc_attr(self::$configuration['widget_title']) : __("Do you need help?", "helpr-agent-domain");
		wp_add_dashboard_widget(
			self::wid,				//A unique slug/ID
			$widget_title,				//Visible name for the widget
			array('Helpr_Agent','widget')	//Callback for the main widget content
		);

		//try to move the widget to the top:
		self::move_widget_to_the_top(self::wid);
		//add_filter('admin_bar_menu', array('Helpr_Agent','add_help_call_to_menu'), 51);
	}

	public static function widget()
	{
		if(isset($_POST['helpr_agentor_message']))
		{
			self::send_helprequest_messsage();
		}
		else
		{
			self::echo_helprequest_form('dashboard_widget');
		}
	}


	public static function register_request_help_menu_option()
	{
		$page_title = __('Helpr Agent - Get Help');
		$menu_title = __('Request Help');
		$menu_slug = 'helpr-agent';
		$function = array('Helpr_Agent', 'display_helprequest_page' );
		$icon_url = plugins_url('media/helpragent-help.png', __FILE__);
		$position = '2.1';
		add_menu_page($page_title, $menu_title, 'manage_options', $menu_slug, $function, $icon_url, $position);

		//if javascript is activated, the scripts will be loaded and the register_help_request page will be replaced
		//if you click on the menu option a modal jquery dialog will be opened where the help request can be put
		//add_action('admin_footer', array('Helpr_Agent', 'dialogize_helprequest_page'));
	}

	public static function display_helprequest_page()
	{
		$context = 'dialog';
		//context variable needed to use different ways for sending the mail (in dashboard and on menu page, normal php, dialog works with js)
		if(isset($_POST['helpr_agentor_message-page']))
		{
			self::send_helprequest_messsage_page();
		}
		else
		{
			switch ($context)
			{
				case "dashboard_widget":
					$helptext = ( isset(self::$configuration['widget_helptext']) ) ? esc_attr(self::$configuration['widget_helptext']) : 'not configured';
					break;
				default:
					$helptext = ( isset( self::$configuration['helprequest_helptext'])) ? esc_attr(self::$configuration['helprequest_helptext']) : 'not configured';
			}

		//this is the form which is used by the dashboard widget and the dialog (or the mainmenu-page if js is disabled)
		//if a helptext is set, the form will be rendered
		if($helptext !== 'not configured'): ?>
			<p><?php echo $helptext; ?></p>

			<?php if($context == 'dialog'):
			/*we need to check if the form is loaded for the dialog or a widget. widget already sends the message via php
			and only if the dialog is rendering form (context==dialog) we have to add another id to handle sending the message with jquery */ ?>
			<form autocomplete="on" method="POST" helprequest_file="<?php echo plugins_url('send_helprequest_message.php', __FILE__); ?>" id="helprequest_dialog-page">
			<?php else: ?>

			<form autocomplete="on" method="POST" id="helprequest-page">
			<?php endif; ?>
				<div class="sub-entry">
				<div class="input-text-wrap-page" id="title-wrap-page">
			    	<input type="text" name="subject-page" id="subject-page" autocomplete="off" placeholder="Subject">
			    </div>
					<br>
				<textarea rows='8' name='helpr_agentor_message-page' id='optional_help_message-page' placeholder="Your message (optional)"></textarea>
				<div id="widget-footer-holder-page">
					<?php if(isset(self::$configuration['phone']) && !empty(self::$configuration['phone'])): ?>
						<span id='or_call_agency-page'>
							<?php _e("Call "); echo esc_attr(self::$configuration['phone']); ?> or
						</span>
					<?php endif; ?>
					<input type='submit' class='button-primary-page' value="<?php _e("Request Help", "helpr-agent-domain"); ?>">
				</div>

				</div>
				<div class="sub-entry2">

				</div>
			</form>




		<?php else: //if no helptext is set the please configure the plugin message is shown ?>
			<?php //if(current_user_can('edit_plugins')): ?>
			<p id="paragraph1-page"><?php printf('Thank you for using Helpr Agent!' )	?>	</p>
			<p id="paragraph-page"><?php printf( __( 'Please configure the plugin <a href="options-general.php?page=helpr_agent_settings">here</a>', 'helpr-agent-domain' ), self::link_to_settings_section()); 	?>	</p>
			<?php //else: ?>
			<p><?php //_e("You have to configure this widget before it can be used. Please log in as an administrator and configure this widget", "helpr-agent-domain") ?></p>
			<?php //endif; ?>
		<?php endif;
		}
	}

	public static function dialogize_helprequest_page()
	{
		//context variable needed to use different ways for sending the mail (in dashboard and on menu page, normal php, dialog works with js)
		$context = 'dialog';
		//add the hidden div to the page. dialog.js uses this div to make a dialog out of it
		//dialog.js is enqueud on every site in register_scripts function
		echo '<div id="helpr_agent_dialog" title="'.__("Request Help").'" style="display: block;">';

		self::echo_helprequest_form('dialog');

		echo '</div>';

		/*TODO: find a way nicer way to do this. when the content of the dialog changes (showing error/success messages) and you reopen the dialog,
		the dialog has to get the original form again, so it doesn't still show the error message from last time. so it gets the old data from this div and copies it back to the dialog div just created */
		echo '<div id="backup_content_helpr_agent_dialog" title="'.__("Request Help").'" style="display: block;">';

		self::echo_helprequest_form('dialog');

		echo '</div>';

		//needs to be done so that I can access the image from javascript. I can't read out the right url in js.
		echo '<script type="application/javascript"> var ajax_loader_image_url = "'.plugins_url('media/ajax-loader.gif', __FILE__).'"; var ajax_failure_error_message = "'._e("A Problem occured when trying to oreach the mail server. Please it with the dashboard widget or contact your agency directly").'"</script>';
	}

	public static function move_widget_to_the_top($widgetID)
	{
	 	// Globalize the metaboxes array, this holds all the widgets for wp-admin
	 	global $wp_meta_boxes;
	 	// Get the regular dashboard widgets array
	 	// (which has our new widget already but at the end)
	 	$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
	 	// Backup and delete our new dashboard widget from the end of the array
	 	$widget_backup = array( $widgetID => $normal_dashboard[$widgetID] );
	 	unset( $normal_dashboard[$widgetID] );
	 	// Merge the two arrays together so our widget is at the beginning
	 	$sorted_dashboard = array_merge( $widget_backup, $normal_dashboard );
	 	// Save the sorted array back into the original metaboxes
	 	$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}

	public static function link_to_settings_section()
	{
		return '<a href="options-general.php?page=helpr-agent.php"></a>';
	}

	public static function send_helprequest_messsage()
	{
			if(!empty($_POST['helpr_agentor_message']) || is_string($_POST['helpr_agentor_message']))
			{
				$subject = !empty($_POST['subject']) ? sanitize_text_field($_POST['subject']) : __("No subject");
				$message = !empty($_POST['helpr_agentor_message']) ? sanitize_text_field($_POST['helpr_agentor_message']) : __("The customer didn't enter any message");
				$email_to = sanitize_email(self::$configuration['email']);
				$email_from = sanitize_email(self::$configuration['email_from']);
				$headers [] = 'From: Helpr Agent <' . $email_from . '>' . "\r\n";
				if (wp_mail($email_to, __("Helpr Agent: A Client Needs Help", "helpr-agent-domain"),$message.' From: '.site_url(), $headers))

				{
					echo '<p class="success">';
					printf(__('Your help request has been send.', "helpr-agent-domain"), $email_to);
					echo '</p>';
				}
				else
				{

					echo '<p class="error">';
					echo "sending to ".$email_to." the message is: ".$message." and it failed <br>";
					printf(__('An error occured while sending the help request. Please contact your agency directly at %s', 'helpr-agent-domain'), $email_to);
					echo '</p>';
				}
			}
			else
			{
				echo '<p class="error">'.__('The message you entered is not valid.').'</p>';
			}

		//needed, because elseways the ajax-request returns the message with a '0' at the end. some studip wp problem if I understood right
		if (defined('DOING_AJAX') && DOING_AJAX)
			die();
	}



	/****SEND Message FROM PAGE*****/


	public static function send_helprequest_messsage_page()
	{
			if(!empty($_POST['helpr_agentor_message-page']) || is_string($_POST['helpr_agentor_message-page']))
			{
				$subject = !empty($_POST['subject-page']) ? sanitize_text_field($_POST['subject-page']) : __("No subject");
				$message = !empty($_POST['helpr_agentor_message-page']) ? sanitize_text_field($_POST['helpr_agentor_message-page']) : __("The customer didn't enter any message");
				$email_to = sanitize_email(self::$configuration['email']);
				$email_from = sanitize_email(self::$configuration['email_from']);
				$headers [] = 'From: Helpr Agent <' . $email_from . '>' . "\r\n";
				if (wp_mail($email_to, $subject, $message.' From: '.site_url(), $headers))

				{
					echo '<p class="success">';
					printf(__('Your help request has been send.', "helpr-agent-domain"), $email_to);
					echo '</p>';
				}
				else
				{

					echo '<p class="error">';
					echo "sending to ".$email_to." the message is: ".$message." and it failed <br>";
					printf(__('An error occured while sending the help request. Please contact your agency directly at %s', 'helpr-agent-domain'), $email_to);
					echo '</p>';
				}
			}
			else
			{
				echo '<p class="error">'.__('The message you entered is not valid.').'</p>';
			}

		//needed, because elseways the ajax-request returns the message with a '0' at the end. some studip wp problem if I understood right
		if (defined('DOING_AJAX') && DOING_AJAX)
			die();
	}


	public static function echo_helprequest_form($context)
	{
		//context is 'dashboard_widget', 'menupage' or 'dialog' depending on for what the form is rendered
		switch ($context)
		{
			case "dashboard_widget":
				$helptext = ( isset(self::$configuration['widget_helptext']) ) ? esc_attr(self::$configuration['widget_helptext']) : 'not configured';
				break;
			default:
				$helptext = ( isset( self::$configuration['helprequest_helptext'])) ? esc_attr(self::$configuration['helprequest_helptext']) : 'not configured';
		}

		//this is the form which is used by the dashboard widget and the dialog (or the mainmenu-page if js is disabled)
		//if a helptext is set, the form will be rendered
		if($helptext !== 'not configured'): ?>
			<p><?php echo $helptext; ?></p>
			<?php if($context == 'dialog'):
			/*we need to check if the form is loaded for the dialog or a widget. widget already sends the message via php
			and only if the dialog is rendering form (context==dialog) we have to add another id to handle sending the message with jquery */ ?>
			<form autocomplete="on" method="POST" helprequest_file="<?php echo plugins_url('send_helprequest_message.php', __FILE__); ?>" id="helprequest_dialog">
			<?php else: ?>
			<form autocomplete="on" method="POST" id="helprequest">
			<?php endif; ?>

				<div class="input-text-wrap" id="title-wrap">
			    	<input type="text" name="subject" id="subject" autocomplete="off" placeholder="Subject">
		    	</div>
					<br>
					<textarea rows='6' name='helpr_agentor_message' id='optional_help_message' placeholder="Your message (optional)"></textarea>
				<div id="widget-footer-holder">
					<?php if(isset(self::$configuration['phone']) && !empty(self::$configuration['phone'])): ?>
						<span id='or_call_agency'>
							<?php _e("Call "); echo esc_attr(self::$configuration['phone']); ?> or
						</span>
					<?php endif; ?>
					<input type='submit' class='button-primary' value="<?php _e("Request Help", "helpr-agent-domain"); ?>">
				</div>

			</form>


		<?php else: //if no helptext is set the please configure the plugin message is shown ?>
			<?php //if(current_user_can('edit_plugins')): ?>
			<p id="paragraph-widget"><?php printf( __( 'Please configure the plugin <a href="options-general.php?page=helpr_agent_settings">here</a>', 'helpr-agent-domain' ), self::link_to_settings_section()); 					?>				</p>
			<?php //else: ?>
			<p><?php //_e("You have to configure this widget before it can be used. Please log in as an administrator and configure this widget", "helpr-agent-domain") ?></p>
			<?php //endif; ?>
		<?php endif;
	}
}

require_once('helpr_agent_settings.php');
add_action( 'plugins_loaded', array('Helpr_Agent', 'init'));
//add_action( 'admin_init', array('Helpr_Agent', 'register_settings'));
