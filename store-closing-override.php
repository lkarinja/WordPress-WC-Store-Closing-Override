<?php
/*
	Plugin Name: Store Closing Override
	Description: Overrides some functionality from <a href="http://dev.4gendesign.com/">Ozibal's</a> <a href="http://dev.4gendesign.com/magaza-kapama/">Store Closing</a> plugin
	Version: 2.0.0
	Author: <a href="https://github.com/lkarinja">Leejae Karinja</a>
	License: GPL3
	License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/*
	Store Closing Override WordPress Plugin
	Copyright (C) 2017 Leejae Karinja

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Prevents execution outside of core WordPress
if(!defined('ABSPATH'))
{
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit;
}

// Make sure we can access Ozibal's 'storeclosingget' class
if(!class_exists('storeclosingget'))
{
	require_once plugin_dir_path(__FILE__) . '/..' . '/store-closing/storeclosing-class.php';
}

if(!class_exists('Store_Closing_Override'))
{
	class Store_Closing_Override
	{
		// These are for the options found in the Admin Menu
		protected $options;
		protected $saved_options;

		/**
		 * Plugin constructor
		 */
		public function __construct()
		{
			// This fixes a header error and is needed to properly apply custom HTML/CSS inside this script
			ob_start();
			$this->textdomain = 'store-closing-override';

			// On all page loads (Note priority has to be lower (More priority) than Ozibal's hooks
			add_action('init', array($this, 'override_store_close'), 9);
			// On WooCommerce load
			add_action('woocommerce_init', array($this, 'init'));

			// Store Closing Override Options
			$this->options = array(
				'store_closing_method' => 'auto',
				'store_closing_open_day' => '6', // Saturday
				'store_closing_open_time' => '12:00',
				'store_closing_close_day' => '3', // Wednesday
				'store_closing_close_time' => '7:30',
			);
			$this->saved_options = array();
		}

		/**
		 * Initial initialization for the plugin, adding the options page in the admin menu
		 */
		public function init()
		{
			// Add options in the admin menu under WooCommerce
			add_action('admin_menu', array($this, 'add_menu_options'));
		}

		/**
		 * Adds an options page under WooCommerce -> Store Closing Override
		 *
		 * Parts of this function are referenced from Terry Tsang (http://shop.terrytsang.com) Extra Fee Option Plugin (http://terrytsang.com/shop/shop/woocommerce-extra-fee-option/)
		 * Licensed under GPL2
		 */
		public function add_menu_options()
		{
			$woocommerce_page = 'woocommerce';
			$settings_page = add_submenu_page(
				$woocommerce_page,
				__('Store Closing Override', $this->textdomain),
				__('Store Closing Override', $this->textdomain),
				'manage_options',
				'store-closing-override',
				array(
					$this,
					'settings_page_options'
				)
			);
		}

		/**
		 * Page builder for the Store Closing Override options page
		 *
		 * Parts of this function are referenced from Terry Tsang (http://shop.terrytsang.com) Extra Fee Option Plugin (http://terrytsang.com/shop/shop/woocommerce-extra-fee-option/)
		 * Licensed under GPL2
		 */
		public function settings_page_options()
		{
			// If options should be saved
			if(isset($_POST['submitted']))
			{
				check_admin_referer( $this->textdomain );

				// Try to load saved Store Closing Override options
				$this->saved_options['store_closing_method'] = !isset($_POST['store_closing_method']) ? 'auto' : $_POST['store_closing_method'];
				$this->saved_options['store_closing_open_day'] = !isset($_POST['store_closing_open_day']) ? '6' : $_POST['store_closing_open_day'];
				$this->saved_options['store_closing_open_time'] = !isset($_POST['store_closing_open_time']) ? '12:00' : $_POST['store_closing_open_time'];
				$this->saved_options['store_closing_close_day'] = !isset($_POST['store_closing_close_day']) ? '3' : $_POST['store_closing_close_day'];
				$this->saved_options['store_closing_close_time'] = !isset($_POST['store_closing_close_time']) ? '7:30' : $_POST['store_closing_close_time'];

				// For each options in the plugin
				foreach($this->options as $field => $value)
				{
					// If there was an update to an option
					if(get_option($field) != $this->saved_options[$field])
					{
						// Save the new value of that option
						update_option($field, $this->saved_options[$field]);
						// Recalculate status of the store
						$this->override_store_close();
					}
				}

				// Display a save message
				echo '<div><p>' . __('Options saved.', $this->textdomain) . '</p></div>';
			}

			// Store Closing Override options
			$store_closing_method = get_option('store_closing_method') ? get_option('store_closing_method') : 'auto';
			$store_closing_open_day = get_option('store_closing_open_day') ? get_option('store_closing_open_day') : '6';
			$store_closing_open_time = get_option('store_closing_open_time') ? get_option('store_closing_open_time') : '12:00';
			$store_closing_close_day = get_option('store_closing_close_day') ? get_option('store_closing_close_day') : '3';
			$store_closing_close_time = get_option('store_closing_close_time') ? get_option('store_closing_close_time') : '7:30';

			$actionurl = $_SERVER['REQUEST_URI'];
			$nonce = wp_create_nonce($this->textdomain);

			// HTML/inline PHP for the options page in the WooCommerce menu
			?>
			<h3><?php _e( 'Store Closing Override', $this->textdomain); ?></h3>
			<form action="<?php echo $actionurl; ?>" method="post">
				<table>
					<tbody>
						<tr>
							<td>
								<?php _e('Store Closing Option:', $this->textdomain); ?>
								<select name="store_closing_method">
									<option value="auto" <?php if($store_closing_method == 'auto') { echo 'selected="selected"'; } ?>><?php _e('Auto', $this->textdomain); ?></option>
									<option value="force_open" <?php if($store_closing_method == 'force_open') { echo 'selected="selected"'; } ?>><?php _e('Force Store as Open', $this->textdomain); ?></option>
									<option value="force_closed" <?php if($store_closing_method == 'force_closed') { echo 'selected="selected"'; } ?>><?php _e('Force Store as Closed', $this->textdomain); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td>
								<ul>
									<li>
										<?php _e('Auto will Open and Close the store automatically (Open on', $this->textdomain); ?>
										<select name="store_closing_open_day">
											<option value="1" <?php if($store_closing_open_day == '1') { echo 'selected="selected"'; } ?>><?php _e('Monday', $this->textdomain); ?></option>
											<option value="2" <?php if($store_closing_open_day == '2') { echo 'selected="selected"'; } ?>><?php _e('Tuesday', $this->textdomain); ?></option>
											<option value="3" <?php if($store_closing_open_day == '3') { echo 'selected="selected"'; } ?>><?php _e('Wednesday', $this->textdomain); ?></option>
											<option value="4" <?php if($store_closing_open_day == '4') { echo 'selected="selected"'; } ?>><?php _e('Thursday', $this->textdomain); ?></option>
											<option value="5" <?php if($store_closing_open_day == '5') { echo 'selected="selected"'; } ?>><?php _e('Friday', $this->textdomain); ?></option>
											<option value="6" <?php if($store_closing_open_day == '6') { echo 'selected="selected"'; } ?>><?php _e('Saturday', $this->textdomain); ?></option>
											<option value="7" <?php if($store_closing_open_day == '7') { echo 'selected="selected"'; } ?>><?php _e('Sunday', $this->textdomain); ?></option>
										</select>
										<?php _e('at', $this->textdomain); ?>
										<input type="text" value="<?php echo esc_attr($store_closing_open_time); ?>" name="store_closing_open_time"/>
										<?php _e('and Close on', $this->textdomain); ?>
										<select name="store_closing_close_day">
											<option value="1" <?php if($store_closing_close_day == '1') { echo 'selected="selected"'; } ?>><?php _e('Monday', $this->textdomain); ?></option>
											<option value="2" <?php if($store_closing_close_day == '2') { echo 'selected="selected"'; } ?>><?php _e('Tuesday', $this->textdomain); ?></option>
											<option value="3" <?php if($store_closing_close_day == '3') { echo 'selected="selected"'; } ?>><?php _e('Wednesday', $this->textdomain); ?></option>
											<option value="4" <?php if($store_closing_close_day == '4') { echo 'selected="selected"'; } ?>><?php _e('Thursday', $this->textdomain); ?></option>
											<option value="5" <?php if($store_closing_close_day == '5') { echo 'selected="selected"'; } ?>><?php _e('Friday', $this->textdomain); ?></option>
											<option value="6" <?php if($store_closing_close_day == '6') { echo 'selected="selected"'; } ?>><?php _e('Saturday', $this->textdomain); ?></option>
											<option value="7" <?php if($store_closing_close_day == '7') { echo 'selected="selected"'; } ?>><?php _e('Sunday', $this->textdomain); ?></option>
										</select>
										<?php _e('at', $this->textdomain); ?>
										<input type="text" value="<?php echo esc_attr($store_closing_close_time); ?>" name="store_closing_close_time"/>
									</li>
									<li><?php _e('Force Store as Open will cause the Store to remain Open until this option is deselected', $this->textdomain); ?></li>
									<li><?php _e('Force Store as Closed will cause the Store to remain Closed until this option is deselected', $this->textdomain); ?></li>
								</ul>
							</td>
						</tr>
						<tr>
							<td>
								<input class="button-primary" type="submit" name="Save" value="<?php _e('Save Options', $this->textdomain); ?>" id="submitbutton" />
								<input type="hidden" name="submitted" value="1" /> 
								<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo $nonce; ?>" />
							</td>
						</tr>
					</tbody>
				</table>
			</form>
			<br />
			<?php
		}

		/**
		 * Checks if there is a manual override on the Store status, and opens or closes the store accordingly
		 */
		public function override_store_close()
		{
			// Gets the option for the store override found in the admin menu
			$store_closing_method = get_option('store_closing_method') ? get_option('store_closing_method') : 'auto';

			// If Store Closing functionality should be done automatically
			if($store_closing_method == 'auto')
			{
				// If the store is closed
				if($this->is_store_closed())
				{
					// Close the store with Ozibal's plugin to display the notification
					$this->ozibal_close_with_reflection();

					// Instead of using Ozibal's Store Closing plugin method of displaying a closed store...
					$this->remove_storeclosing_disable_hooks();

					// Use our own method to specifically disable ALL cart related buttons
					$this->remove_cart_buttons();

					// Set the option 'store_status' to 'closed' (The store is closed)
					update_option('store_status', 'closed');
				}
				// If the store is not closed
				else
				{
					// Remove all of Ozibal's hooks to prevent store closing messages from displaying
					$this->remove_all_parent_hooks();
					// Set the option 'store_status' to 'open' (The store is open)
					update_option('store_status', 'open');
				}
			}
			// If the Store should be forced to Open
			elseif($store_closing_method == 'force_open')
			{
				// Remove all of Ozibal's hooks to prevent store closing messages from displaying
				$this->remove_all_parent_hooks();

				// Set the option 'store_status' to 'open' (The store is open)
				update_option('store_status', 'open');
			}
			// If the Store should be forced to Closed
			elseif($store_closing_method == 'force_closed')
			{
				// Close the store with Ozibal's plugin to display the notification
				$this->ozibal_close_with_reflection();

				// Use our own method to specifically disable ALL cart related buttons
				//$this->remove_cart_buttons();
				add_action('wp_loaded', array($this, 'remove_cart_buttons'), 10);

				// Set the option 'store_status' to 'closed' (The store is closed)
				update_option('store_status', 'closed');
			}
		}

		/**
		 * Uses reflection to close the store with Ozibal's Store Closeing plugin, which allows the notification set in Ozibal's plugin to be displayed
		 */
		public function ozibal_close_with_reflection()
		{
			// We need to use reflection to modify our own instance of Ozibal's plugin to access options which were declared private instead of protected
			$ozibal_store_closing = new storeclosingget();
			$ref_ozibal_store_closing = new ReflectionClass($ozibal_store_closing);

			// We need to manually set our instance of Ozibal's plugin variable 'store_check' as 'CLOSE'
			$ref_ozibal_store_closing_store_check = $ref_ozibal_store_closing->getProperty('store_check');
			$ref_ozibal_store_closing_store_check->setAccessible(true);
			$ref_ozibal_store_closing_store_check->setValue($ozibal_store_closing, 'CLOSE');

			// We need to manually edit our instance of Ozibal's plugin variable 'storeclosing_options' to remove some custom formatting
			$ref_ozibal_store_closing_storeclosing_options = $ref_ozibal_store_closing->getProperty('storeclosing_options');
			$ref_ozibal_store_closing_storeclosing_options->setAccessible(true);
			$storeclosing_options = $ref_ozibal_store_closing_storeclosing_options->getValue($ozibal_store_closing);
			$storeclosing_options[0][1] = str_replace('[tstamp]', '', $storeclosing_options[0][1]);
			$storeclosing_options[0][1] = str_replace('[countdown]', '', $storeclosing_options[0][1]);
			$ref_ozibal_store_closing_storeclosing_options->setValue($ozibal_store_closing, $storeclosing_options);

			// Remove all hooks that might have been created using our instance of Ozibal's plugin
			$this->remove_all_parent_hooks();

			// Manually format the Store Closing Notification with Ozibal's plugin
			$ozibal_store_closing->storeclosing_notification(0, 0);

			// Add Ozibal's notification messages for a closed store
			add_action('woocommerce_before_add_to_cart_button', array($ozibal_store_closing, 'storeclosing_show'), 10);
			add_action('woocommerce_review_order_before_payment', array($ozibal_store_closing, 'storeclosing_show'), 10);
		}

		/**
		 * Check if the store is closed according to Ozibal's Store Closing plugin
		 */
		public function is_store_closed()
		{
			// Gets the option for the store override found in the admin menu
			$store_closing_open_day = get_option('store_closing_open_day') ? get_option('store_closing_open_day') : '6';
			$store_closing_open_time = get_option('store_closing_open_time') ? get_option('store_closing_open_time') : '12:00';
			$store_closing_close_day = get_option('store_closing_close_day') ? get_option('store_closing_close_day') : '3';
			$store_closing_close_time = get_option('store_closing_close_time') ? get_option('store_closing_close_time') : '7:30';

			// Get the time of opening, closing, and now
			$now = new DateTime('Now', new DateTimeZone('America/New_York'));
			$open = new DateTime($store_closing_open_time, new DateTimeZone('America/New_York'));
			$close = new DateTime($store_closing_close_time, new DateTimeZone('America/New_York'));

			// Format dates
			$day_of_the_week = $now->format("N");
			$time_of_the_day = $now->format("H:i");

			// If there is an overlap in days of the week for opening and closing
			if($store_closing_open_day > $store_closing_close_day)
			{
				if(
					// If the current day of the week is exclusively between the opening and closing day when there is an overlap in days of the week
					in_array($day_of_the_week, array_diff(range(1, 7), range($store_closing_close_day, $store_closing_open_day))) ||
					// If today is on the opening day and after opening time
					($day_of_the_week == $store_closing_open_day && $now->getTimestamp() > $open->getTimestamp()) ||
					// If today is on the closing day and before closing time
					($day_of_the_week == $store_closing_close_day && $now->getTimestamp() < $close->getTimestamp())
				){
					// The store is still open
					return false;
				}
				else
				{
					// The store is closed
					return true;
				}
			}
			// If there is no overlap in days of the week for opening and closing
			elseif($store_closing_open_day < $store_closing_close_day)
			{
				if(
					// If the current day of the week is exclusively between the opening and closing day when there is no overlap in days of the week
					in_array($day_of_the_week, array_slice(array_intersect(range(1, 7), range($store_closing_open_day, $store_closing_close_day)), 1, -1)) ||
					// If today is on the opening day and after opening time
					($day_of_the_week == $store_closing_open_day && $now->getTimestamp() > $open->getTimestamp()) ||
					// If today is on the closing day and before closing time
					($day_of_the_week == $store_closing_close_day && $now->getTimestamp() < $close->getTimestamp())
				){
					// The store is still open
					return false;
				}
				else
				{
					// The store is closed
					return true;
				}
			}
			// If the opening and closing days are the same
			else
			{
				if(
					// If now is after opening time and before closing time
					($now->getTimestamp() > $open->getTimestamp()) && ($now->getTimestamp() < $close->getTimestamp())
				){
					// The store is still open
					return false;
				}
				else
				{
					// The store is closed
					return true;
				}
			}
		}

		/**
		 * Remove all references to adding items to cart and checking out throughout the store
		 */
		public function remove_cart_buttons()
		{
			if(!is_admin())
			{
				echo
				"<style>
					.woocommerce li.product .entry-header .button,
					.woocommerce-page li.product .entry-header .button,
					.woocommerce .quantity,
					.woocommerce-page .quantity,
					.woocommerce .single_add_to_cart_button,
					.woocommerce-page .single_add_to_cart_button,
					.woocommerce-page .checkout-button,
					.woocommerce #payment,
					.woocommerce-page .add_to_cart_button
					{
						display: none !important;
					}
				</style>";
			}
		}

		/**
		 * Remove all hooks from Ozibal's Store Closing plugin, preventing the store from closing
		 */
		protected function remove_all_parent_hooks()
		{
			$this->remove_storeclosing_disable_hooks();
			$this->remove_storeclosing_show_hooks();
		}

		/**
		 * Remove hooks calling "storeclosing_disable" (Disables cart related buttons) from Ozibal's Store Closing plugin
		 */
		protected function remove_storeclosing_disable_hooks()
		{
			$this->remove_class_action('woocommerce_after_single_product', 'storeclosingget', 'storeclosing_disable');
			$this->remove_class_action('woocommerce_proceed_to_checkout', 'storeclosingget', 'storeclosing_disable');
			$this->remove_class_action('woocommerce_review_order_before_submit', 'storeclosingget', 'storeclosing_disable');
		}

		/**
		 * Remove hooks calling "storeclosing_show" (Shows the close message) from Ozibal's Store Closing plugin
		 */
		protected function remove_storeclosing_show_hooks()
		{
			$this->remove_class_action('woocommerce_before_add_to_cart_button', 'storeclosingget', 'storeclosing_show');
			$this->remove_class_action('woocommerce_review_order_before_payment', 'storeclosingget', 'storeclosing_show');
		}

		/**
		 * RETRIEVED FROM https://wordpress.stackexchange.com/a/239431/122847
		 * Source and Full Documentation: https://gist.github.com/tripflex/c6518efc1753cf2392559866b4bd1a53
		 * This code was found with no copyright or license
		 * I, Leejae Karinja, will respect any changes to Licenses, Terms, or Copyrights made by the author, Myles McNamara (https://gist.github.com/tripflex)
		 *
		 * Remove Class Filter Without Access to Class Object
		 *
		 * @param string $tag         Filter to remove
		 * @param string $class_name  Class name for the filter's callback
		 * @param string $method_name Method name for the filter's callback
		 * @param int    $priority    Priority of the filter (default 10)
		 *
		 * @return bool Whether the function is removed.
		 */
		private function remove_class_filter($tag, $class_name = '', $method_name = '', $priority = 10)
		{
			global $wp_filter;

			if(!isset($wp_filter[$tag])){
				return FALSE;
			}

			if(is_object($wp_filter[$tag]) && isset($wp_filter[$tag]->callbacks)){
				$fob = $wp_filter[$tag];
				$callbacks = &$wp_filter[$tag]->callbacks;
			}else{
				$callbacks = &$wp_filter[$tag];
			}

			if(!isset($callbacks[$priority]) || empty($callbacks[$priority])){
				return FALSE;
			}

			foreach((array) $callbacks[$priority] as $filter_id => $filter){

				if(!isset($filter['function']) || !is_array($filter['function'])){
					continue;
				}
				if(!is_object($filter['function'][0])){
					continue;
				}
				if($filter['function'][1] !== $method_name){
					continue;
				}

				if(get_class($filter['function'][0]) === $class_name){
					if(isset($fob)){
						$fob->remove_filter($tag, $filter['function'], $priority);
					}else{
						unset($callbacks[$priority][$filter_id]);
						if(empty($callbacks[$priority])){
							unset($callbacks[$priority]);
						}
						if(empty($callbacks)){
							$callbacks = array();
						}
						unset($GLOBALS['merged_filters'][$tag]);
					}
					return TRUE;
				}
			}
			return FALSE;
		}

		/**
		 * RETRIEVED FROM https://wordpress.stackexchange.com/a/239431/122847
		 * Source and Full Documentation: https://gist.github.com/tripflex/c6518efc1753cf2392559866b4bd1a53
		 * This code was found with no copyright or license
		 * I, Leejae Karinja, will respect any changes to Licenses, Terms, or Copyrights made by the author, Myles McNamara (https://gist.github.com/tripflex)
		 *
		 * Remove Class Action Without Access to Class Object
		 *
		 * @param string $tag         Action to remove
		 * @param string $class_name  Class name for the action's callback
		 * @param string $method_name Method name for the action's callback
		 * @param int    $priority    Priority of the action (default 10)
		 *
		 * @return bool               Whether the function is removed.
		 */
		private function remove_class_action($tag, $class_name = '', $method_name = '', $priority = 10)
		{
			return $this->remove_class_filter($tag, $class_name, $method_name, $priority);
		}

		/**
		 * RETRIEVED FROM https://wordpress.stackexchange.com/a/239431/122847
		 * Source and Full Documentation: https://gist.github.com/tripflex/c6518efc1753cf2392559866b4bd1a53
		 * This code was found with no copyright or license
		 * I, Leejae Karinja, will respect any changes to Licenses, Terms, or Copyrights made by the author, Myles McNamara (https://gist.github.com/tripflex)
		 *
		 * Check if Class Filter Exists
		 *
		 * @param string $tag         Filter to check
		 * @param string $class_name  Class name for the filter's callback
		 * @param string $method_name Method name for the filter's callback
		 * @param int    $priority    Priority of the filter (default 10)
		 *
		 * @return bool Whether the filter with specified function hook exists
		 */
		private function has_class_filter($tag, $class_name = '', $method_name = '', $priority = 10)
		{
			global $wp_filter;

			if(!isset($wp_filter[$tag])){
				return FALSE;
			}

			if(is_object($wp_filter[$tag]) && isset($wp_filter[$tag]->callbacks)){
				$fob = $wp_filter[$tag];
				$callbacks = &$wp_filter[$tag]->callbacks;
			}else{
				$callbacks = &$wp_filter[$tag];
			}

			if(!isset($callbacks[$priority]) || empty($callbacks[$priority])){
				return FALSE;
			}

			foreach((array) $callbacks[$priority] as $filter_id => $filter){

				if(!isset($filter['function']) || !is_array($filter['function'])){
					continue;
				}
				if(!is_object($filter['function'][0])){
					continue;
				}
				if($filter['function'][1] !== $method_name){
					continue;
				}

				if(get_class($filter['function'][0]) === $class_name){
					return TRUE;
				}
			}
			return FALSE;
		}

		/**
		 * RETRIEVED FROM https://wordpress.stackexchange.com/a/239431/122847
		 * Source and Full Documentation: https://gist.github.com/tripflex/c6518efc1753cf2392559866b4bd1a53
		 * This code was found with no copyright or license
		 * I, Leejae Karinja, will respect any changes to Licenses, Terms, or Copyrights made by the author, Myles McNamara (https://gist.github.com/tripflex)
		 *
		 * Check if Class Action Exists
		 *
		 * @param string $tag         Action to check
		 * @param string $class_name  Class name for the action's callback
		 * @param string $method_name Method name for the action's callback
		 * @param int    $priority    Priority of the action (default 10)
		 *
		 * @return bool Whether the action with specified function hook exists
		 */
		private function has_class_action($tag, $class_name = '', $method_name = '', $priority = 10)
		{
			return $this->has_class_filter($tag, $class_name, $method_name, $priority);
		}

	}
	$store_closing_override = new Store_Closing_Override();
}

