<?php
/**
 * Plugin Name: WP Webhooks - Contact Form 7 Integration
 * Plugin URI: https://ironikus.com/downloads/contact-form-7-webhook-integration/
 * Description: A WP Webhooks extension to integrate Contact Form 7
 * Version: 1.0.0
 * Author: Ironikus
 * Author URI: https://ironikus.com/
 * License: GPL2
 *
 * You should have received a copy of the GNU General Public License.
 * If not, see <http://www.gnu.org/licenses/>.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'WP_Webhooks_Contact_Form_7' ) ){

	class WP_Webhooks_Contact_Form_7{

	    private $cf7_is_active = null;

		public function __construct() {

			add_action( 'plugins_loaded', array( $this, 'add_webhook_triggers' ), 20 );
			add_filter( 'wpwhpro/webhooks/get_webhooks_triggers', array( $this, 'add_webhook_triggers_content' ), 20 );
			add_action( 'admin_notices', array( $this, 'wpwhpro_cf7_throw_admin_notices' ), 100 );
		}

		public function wpwhpro_cf7_throw_admin_notices(){

		    if( ! $this->is_cf7_active() ){
			    echo sprintf(WPWHPRO()->helpers->create_admin_notice( '<strong>Contact Form 7 Webhook Integration</strong> is active, but <strong>Contact Form 7</strong> isn\'t. Please activate it to use the functionality for <strong>Contact Form 7</strong>. <a href="%s" target="_blank" rel="noopener">More Info</a>', 'warning', false ), 'https://contactform7.com/');
            }

        }

		/**
		 * ######################
		 * ###
		 * #### HELPERS
		 * ###
		 * ######################
		 */

		public function is_cf7_active(){

		    if( $this->cf7_is_active !== null ){
		        return $this->cf7_is_active;
            }

            $return = false;

		    if( defined( 'WPCF7_VERSION' ) ){
			    $return = true;
            }

			$this->cf7_is_active = $return;

            return $return;

        }

		/**
		 * Validate the form data into an array we can send to Zapier
		 *
		 * @since 1.0.0
		 * @param object $contact_form - ContactForm Obj
		 */
		private function get_contact_form_data( $contact_form ) {
			$data = array();
			$form_tags = $contact_form->scan_form_tags();

			foreach ( $form_tags as $stag ) {

				if ( empty( $stag->name ) ){
					continue;
                }

				$pipes = $stag->pipes;
				$value = ( ! empty( $_POST[ $stag->name ] ) ) ? $_POST[ $stag->name ] : '';

				if ( defined( 'WPCF7_USE_PIPE' ) && WPCF7_USE_PIPE && $pipes instanceof WPCF7_Pipes && ! $pipes->zero() ) {
					if ( is_array( $value) ) {
						$new_value = array();

						foreach ( $value as $svalue ) {
							$new_value[] = $pipes->do_pipe( wp_unslash( $svalue ) );
						}

						$value = $new_value;
					} else {
						$value = $pipes->do_pipe( wp_unslash( $value ) );
					}
				}

				$data[ $stag->name ] = $value;
			}

			return $data;
		}


		private function validate_special_mail_tags( $cf ) {
			$return = array();

			if( empty( $cf ) ){
				return $return;
			}

			$tags_data = explode( ',', $cf );
			if( ! empty( $tags_data ) && is_array( $tags_data ) ){
				foreach( $tags_data as $stag ){
					$stag_data = explode( ':', $stag );

					if( isset( $stag_data[0] ) ){
						$special_tag_name = $stag_data[0];
						$special_tag_key = $stag_data[0];

						if( isset( $stag_data[1] ) ){
							$special_tag_key = $stag_data[1];
						}

						$return[ $special_tag_key ] = apply_filters( 'wpcf7_special_mail_tags', '', $special_tag_name, false );
					}
				}
			}
			
			return $return;
		}

		/**
		 * ######################
		 * ###
		 * #### WEBHOOK TRIGGERS
		 * ###
		 * ######################
		 */

		/**
		 * Regsiter all available webhook triggers
		 *
		 * @param $triggers - All registered triggers by the current plugin
		 *
		 * @return array - A array of all available triggers
		 */
		public function add_webhook_triggers_content( $triggers ){

			$triggers[] = $this->trigger_send_contact_form();

			return $triggers;
		}

		/*
		 * Add the specified webhook triggers logic.
		 * We also add the demo functionality here
		 */
		public function add_webhook_triggers(){

			$active_webhooks = WPWHPRO()->settings->get_active_webhooks();
			$available_triggers = $active_webhooks['triggers'];

			if( isset( $available_triggers['cf7_forms'] ) ){
				add_action( 'wpcf7_mail_sent', array( $this, 'wpwh_wpcf7_mail_sent' ), 10, 1 );
				add_filter( 'ironikus_demo_test_cf7_forms', array( $this, 'ironikus_send_demo_cf7_form' ), 10, 3 );
				add_filter( 'wpcf7_skip_mail', array( $this, 'wpwh_wpcf7_skip_mail' ), 10, 2 );
			}

		}

		/**
         * Trigger send contact form
         *
		 * @return array -
		 */
		public function trigger_send_contact_form(){

			$validated_forms = array();
			$contact_forms = get_posts(
			        array(
			                'post_type' => 'wpcf7_contact_form',
                            'post_status' => 'publish'
                    )
            );
			foreach( $contact_forms as $form ){

				$id = $form->ID;
				$name = $form->post_title;

				if( ! empty( $id ) && ! empty( $name ) ){
					$validated_forms[ $id ] = $name;
                }

			}

			$parameter = array(
				'form_id'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The id of the form that the data comes from.', 'trigger-cf7' ) ),
				'form_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The post data of the form itself.', 'trigger-cf7' ) ),
				'form_data_meta'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The meta data of the form itself.', 'trigger-cf7' ) ),
				'form_submit_data'   => array( 'short_description' => WPWHPRO()->helpers->translate( 'The data which was submitted by the form. For more details, check the return code area.', 'trigger-cf7' ) ),
			);

			ob_start();
			?>
            <p><?php echo WPWHPRO()->helpers->translate( "Please copy your webhook URL into the provided input field. After that you can test your data via the Send demo button.", "trigger-cf7" ); ?></p>
            <p><?php echo WPWHPRO()->helpers->translate( 'You will recieve a full response of the user form data, the form post meta, as well as of the sumitted form data.', 'trigger-cf7' ); ?></p>
            <p><?php echo WPWHPRO()->helpers->translate( 'You can also filter contact forms to specify where exactly you want to run the trigger on.', 'trigger-cf7' ); ?></p>
            <p><?php echo WPWHPRO()->helpers->translate( 'To check the Webhooks response on a demo request, just open your browser console and you will see the object.', 'trigger-cf7' ); ?></p>
			<?php
			$description = ob_get_clean();

			$settings = array(
				'load_default_settings' => true,
				'data' => array(
					'wpwhpro_cf7_forms' => array(
						'id'          => 'wpwhpro_cf7_forms',
						'type'        => 'select',
						'multiple'    => true,
						'choices'      => $validated_forms,
						'label'       => WPWHPRO()->helpers->translate('Trigger on selected forms', 'wpwhpro-fields-cf7-forms'),
						'placeholder' => '',
						'required'    => false,
						'description' => WPWHPRO()->helpers->translate('Select only the forms you want to fire the trigger on. You can also choose multiple ones. If none is selected, all are triggered.', 'wpwhpro-fields-cf7-forms-tip')
					),
					'wpwhpro_cf7_forms_send_email' => array(
						'id'          => 'wpwhpro_cf7_forms_send_email',
						'type'        => 'checkbox',
						'default_value' => '',
						'label'       => WPWHPRO()->helpers->translate('Don\'t send mail as usually', 'wpwhpro-fields-cf7-forms'),
						'placeholder' => '',
						'required'    => false,
						'description' => WPWHPRO()->helpers->translate('Check the button if you don\'t want to send the contact form to the specified email as usual.', 'wpwhpro-fields-cf7-forms-tip')
					),
					'wpwhpro_cf7_special_mail_tags' => array(
						'id'          => 'wpwhpro_cf7_special_mail_tags',
						'type'        => 'text',
						'default_value' => '',
						'label'       => WPWHPRO()->helpers->translate('Add special mail tags', 'wpwhpro-fields-cf7-forms'),
						'placeholder' => '_post_id,_post_name',
						'required'    => false,
						'description' => WPWHPRO()->helpers->translate('Comma-separate special mail tags. E.g.: For [_post_id] and [_post_name], simply add _post_id,_post_name. To use a custom key, simply add ":MYKEY" behind the tag. E.g: _post_id:post_id,_post_name:post_name', 'wpwhpro-fields-cf7-forms-tip')
					),
				)
			);

			return array(
				'trigger'           => 'cf7_forms',
				'name'              => WPWHPRO()->helpers->translate( 'Send Data On Contact Form 7 Submits', 'trigger-cf7' ),
				'parameter'         => $parameter,
				'settings'          => $settings,
				'returns_code'      => WPWHPRO()->helpers->display_var( $this->ironikus_send_demo_cf7_form( array(), '', '' ) ),
				'short_description' => WPWHPRO()->helpers->translate( 'This webhook fires after one or multiple contact forms are sent.', 'trigger-cf7' ),
				'description'       => $description,
				'callback'          => 'test_cf7_forms'
			);

		}

		/**
		 * Register the demo Contact Form 7 callback
		 *
		 * @since 1.2
		 *
		 * @param $data - The default data
		 * @param $webhook - The current webhook
		 * @param $webhook_group - The current webhook group (trigger-)
		 *
		 * @return array
		 */
		public function ironikus_send_demo_cf7_form( $data, $webhook, $webhook_group ) {

			$data = array(
				'form_id'   => 1,
				'form_data' => array(
					'ID' => 1,
					'post_author' => '1',
					'post_date' => '2018-11-06 14:19:18',
					'post_date_gmt' => '2018-11-06 14:19:18',
					'post_content' => 'THE FORM CONTENT',
					'post_title' => 'My form',
					'post_excerpt' => '',
					'post_status' => 'publish',
					'comment_status' => 'open',
					'ping_status' => 'open',
					'post_password' => '',
					'post_name' => 'my-form',
					'to_ping' => '',
					'pinged' => '',
					'post_modified' => '2018-11-06 14:19:18',
					'post_modified_gmt' => '2018-11-06 14:19:18',
					'post_content_filtered' => '',
					'post_parent' => 0,
					'guid' => 'https://mydomain.dev/?p=1',
					'menu_order' => 0,
					'post_type' => 'wpcf7_contact_form',
					'post_mime_type' => '',
					'comment_count' => '1',
					'filter' => 'raw',
                ),
				'form_data_meta' => array(
                    'my_first_meta_key' => 'MY second meta key value',
                    'my_second_meta_key' => 'MY second meta key value',
                ),
				'form_submit_data' => array(
                    'your-name' => 'xxxxxx',
                    'your-email' => 'xxxxxx',
                    'your-message' => 'xxxxxx'
                ),
			);

			return $data;
		}

		/**
		 * Filter the 'wpcf7_skip_mail' to skip if necessary
		 *
		 * @since 1.0.0
		 * @param bool $skip_mail
		 * @param object $contact_form - ContactForm Obj
		 */
		public function wpwh_wpcf7_skip_mail( $skip_mail, $contact_form ) {
			$form_id = $contact_form->id();
			$is_valid = $skip_mail;

			$webhooks = WPWHPRO()->webhook->get_hooks( 'trigger', 'cf7_forms' );
			foreach( $webhooks as $webhook ){

				if( isset( $webhook['settings'] ) ){
				    if( isset( $webhook['settings']['wpwhpro_cf7_forms'] ) ){

				        if( ! empty( $webhook['settings']['wpwhpro_cf7_forms'] ) ){

				            //If a specific contact form is set, we only check against the set one
					        if( in_array( $form_id, $webhook['settings']['wpwhpro_cf7_forms'] ) ){
						        if( isset( $webhook['settings']['wpwhpro_cf7_forms_send_email'] ) && ! empty( $webhook['settings']['wpwhpro_cf7_forms_send_email'] ) ) {
							        $is_valid = true;
						        }
					        }

                        } else {

				            //If no specific contact form is set, we check against all
					        if( isset( $webhook['settings']['wpwhpro_cf7_forms_send_email'] ) && ! empty( $webhook['settings']['wpwhpro_cf7_forms_send_email'] ) ) {
						        $is_valid = true;
					        }

                        }
                    } else {

					    //If no specific contact form is set, we check against all
					    if( isset( $webhook['settings']['wpwhpro_cf7_forms_send_email'] ) && ! empty( $webhook['settings']['wpwhpro_cf7_forms_send_email'] ) ) {
						    $is_valid = true;
					    }
					}
				}
			}

			return $is_valid;
		}

		/**
		 * Post the data to the specified webhooks
		 *
		 * @since 1.0.0
		 * @param bool $skip_mail
		 * @param object $contact_form - ContactForm Obj
		 */
		public function wpwh_wpcf7_mail_sent( $contact_form ) {
			$form_id = $contact_form->id();
			$response_data = array();
			$data_array = array(
				'form_id'   => $form_id,
				'form_data' => get_post( $form_id ),
				'form_data_meta' => get_post_meta( $form_id ),
				'form_submit_data' => $this->get_contact_form_data( $contact_form ),
				'special_mail_tags' => array(),
			);

			$webhooks = WPWHPRO()->webhook->get_hooks( 'trigger', 'cf7_forms' );
			foreach( $webhooks as $webhook ){

				$is_valid = true;
				$mail_tags = array();

				if( isset( $webhook['settings'] ) ){
				    if( isset( $webhook['settings']['wpwhpro_cf7_forms'] ) && ! empty( $webhook['settings']['wpwhpro_cf7_forms'] ) ){
					    if( ! in_array( $form_id, $webhook['settings']['wpwhpro_cf7_forms'] ) ){
						    $is_valid = false;
					    }
					}
					
					//Add Custom Tags
					if( isset( $webhook['settings']['wpwhpro_cf7_special_mail_tags'] ) && ! empty( $webhook['settings']['wpwhpro_cf7_special_mail_tags'] ) ){
						$mail_tags = $this->validate_special_mail_tags( $webhook['settings']['wpwhpro_cf7_special_mail_tags'] );
						if( ! empty( $mail_tags ) ){
							$data_array['special_mail_tags'] = $mail_tags;
						}
					}
				}

				if( $is_valid ){
					$response_data[] = WPWHPRO()->webhook->post_to_webhook( $webhook, $data_array );
				}
			}

			do_action( 'wpwhpro/webhooks/trigger_cf7_forms', $form_id, $data_array, $response_data );
		}


	} // End class

	function wpwhpro_load_cf7(){
		new WP_Webhooks_Contact_Form_7();
	}

	// Make sure we load the extension after main plugin is loaded
	if( defined( 'WPWH_SETUP' ) || defined( 'WPWHPRO_SETUP' ) ){
		wpwhpro_load_cf7();
    } else {
		add_action( 'wpwhpro_plugin_loaded', 'wpwhpro_load_cf7' );
    }

	//Throw message in case WP Webhook is not active
	add_action( 'admin_notices', 'wpwh_cf7', 100 );
    function wpwh_cf7(){

        if( ! defined( 'WPWH_SETUP' ) && ! defined( 'WPWHPRO_SETUP' ) ){

                ob_start();
                ?>
                <div class="notice notice-warning">
                    <p><?php echo sprintf( '<strong>Contact Form 7 Integration</strong> is active, but <strong>WP Webhooks</strong> or <strong>WP Webhooks Pro</strong> isn\'t. Please activate it to use the functionality for <strong>Contact Form 7</strong>. <a href="%s" target="_blank" rel="noopener">More Info</a>', 'https://de.wordpress.org/plugins/wp-webhooks/' ); ?></p>
                </div>
                <?php
                echo ob_get_clean();

        }

    }

}