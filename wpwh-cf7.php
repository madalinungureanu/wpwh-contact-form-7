<?php
/**
 * Plugin Name: WP Webhooks - Contact Form 7 Integration
 * Plugin URI: https://ironikus.com/downloads/contact-form-7-webhook-integration/
 * Description: A WP Webhooks extension to integrate Contact Form 7
 * Version: 1.1.2
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
			add_action( 'admin_init', array( $this, 'wpwhpro_cf7_clear_preserved_files' ), 100 );
		}

		public function wpwhpro_cf7_throw_admin_notices(){

		    if( ! $this->is_cf7_active() ){
			    echo sprintf(WPWHPRO()->helpers->create_admin_notice( '<strong>Contact Form 7 Webhook Integration</strong> is active, but <strong>Contact Form 7</strong> isn\'t. Please activate it to use the functionality for <strong>Contact Form 7</strong>. <a href="%s" target="_blank" rel="noopener">More Info</a>', 'warning', false ), 'https://contactform7.com/');
            }

        }

		public function wpwhpro_cf7_create_upload_protection_files( $upload_path ) {
	
			// Top level .htaccess file
			$rules = $this->get_htaccess_rules();
			if ( $this->htaccess_exists() ) {
				$contents = @file_get_contents( $upload_path . '/.htaccess' );
				if ( $contents !== $rules || ! $contents ) {
					// Update the .htaccess rules if they don't match
					@file_put_contents( $upload_path . '/.htaccess', $rules );
				}
			} elseif( wp_is_writable( $upload_path ) ) {
				// Create the file if it doesn't exist
				@file_put_contents( $upload_path . '/.htaccess', $rules );
			}
	
			// Top level blank index.php
			$this->create_index_php( $upload_path );
	
			// Now place index.php files in all sub folders
			$folders = $this->plpl_scan_folders( $upload_path );
			foreach ( $folders as $folder ) {
				// Create index.php, if it doesn't exist
				$this->create_index_php( $folder );
			}
		}

		public function wpwhpro_cf7_clear_preserved_files(){
			$preserved_files = $this->get_preserved_files();
			$update = false;
			$current_stamp = time();

			foreach( $preserved_files as $preserved_files_key => $single_file ){
				if( $single_file['time_to_delete'] < $current_stamp ){

					if( file_exists( $single_file['file_path'] ) ){
						wp_delete_file( $single_file['file_path'] );
						unset( $preserved_files[ $preserved_files_key ] );
						$update = true;
					}
					
				}
			}

			if( $$update ){
				$this->update_preserved_files( $preserved_files );
			}
		}

		/**
		 * ######################
		 * ###
		 * #### HELPERS
		 * ###
		 * ######################
		 */

		 public function get_preserved_files(){
			$preserved_files = get_transient( 'wpwhcf7_preserved_files' );

			if( empty( $preserved_files ) ){
				$preserved_files = array();
			}

			return apply_filters( 'wpcf7_get_preserved_files', $preserved_files );
		 }

		 public function update_preserved_files( $preserved_files ){
			$success = set_transient( 'wpwhcf7_preserved_files', $preserved_files );
			return $success;
		 }

		 public function create_index_php( $folder ){
			if ( ! file_exists( $folder . '/index.php' ) && wp_is_writable( $folder ) ) {
				@file_put_contents( $folder . '/index.php', '<?php' . PHP_EOL . '// Silence is golden.' );
			}
		 }

		public function get_upload_dir( $create = true, $sub_dir = null ) {
			$wp_upload_dir = wp_upload_dir();
			$folder_name = apply_filters( 'wpcf7_upload_file_folder_name', 'wpwhcf7' );

			if( $create ){
				$create_files = false;
				if( ! is_dir( $wp_upload_dir['basedir'] . '/' . $folder_name ) ){
					$create_files = true;
				}

				wp_mkdir_p( $wp_upload_dir['basedir'] . '/' . $folder_name );

				if( $create_files ){
					$this->wpwhpro_cf7_create_upload_protection_files( $wp_upload_dir['basedir'] . '/' . $folder_name );
				}
			}
			
			$path = $wp_upload_dir['basedir'] . '/' . $folder_name;

			if( ! empty( $sub_dir ) ){
				if( $create ){
					wp_mkdir_p( $path . '/' . $sub_dir );
					$this->create_index_php( $path . '/' . $sub_dir );
				}
				
				$path = $path . '/' . $sub_dir;
			}
		
			return $path;
		}

		public function htaccess_exists() {
			$upload_path = $this->get_upload_dir();
		
			return file_exists( $upload_path . '/.htaccess' );
		}

		public function get_htaccess_rules() {

			$rules = "Options -Indexes\n";
			$rules .= "deny from all\n";
			
			return $rules;
		}

		function plpl_scan_folders( $path = '', $return = array() ) {
			$path = $path == ''? dirname( __FILE__ ) : $path;
			$lists = @scandir( $path );
		
			if ( ! empty( $lists ) ) {
				foreach ( $lists as $f ) {
					if ( is_dir( $path . DIRECTORY_SEPARATOR . $f ) && $f != "." && $f != ".." ) {
						if ( ! in_array( $path . DIRECTORY_SEPARATOR . $f, $return ) )
							$return[] = trailingslashit( $path . DIRECTORY_SEPARATOR . $f );
		
						$this->plpl_scan_folders( $path . DIRECTORY_SEPARATOR . $f, $return);
					}
				}
			}
		
			return $return;
		}

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
			$submission = WPCF7_Submission::get_instance();
			$uploaded_files = $submission->uploaded_files();

			foreach ( $form_tags as $stag ) {

				if ( empty( $stag->name ) ){
					continue;
                }

				$pipes = $stag->pipes;
				$value = ( ! empty( $_POST[ $stag->name ] ) ) ? $_POST[ $stag->name ] : '';
				$value = ( is_array( $value ) ) ? array_map( 'stripslashes', $value ) : stripslashes( $value );
				$payload_key = $stag->name;
				$form_key = $stag->get_option( 'wpwhkey' );

				if( ! empty( $form_key ) && is_array( $form_key ) && ! empty( $form_key[0] ) ){
					$payload_key = $form_key[0];
				}

				if ( is_array( $uploaded_files ) && ! empty( $uploaded_files[ $stag->name ] ) ) {
					$file_name = wp_basename( $uploaded_files[ $stag->name ] );
					$value = array(
						'file_name' => $file_name,
						'file_url' => str_replace( ABSPATH, trim( home_url(), '/' ) . '/', $uploaded_files[ $stag->name ] ),
						'absolute_path' => $uploaded_files[ $stag->name ],
					);
				} else {
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
				}

				$data[ $payload_key ] = $value;
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
					$mail_tag = new WPCF7_MailTag( '[' . $stag . ']', $stag, '' );

					if( isset( $stag_data[0] ) ){
						$special_tag_name = $stag_data[0];
						$special_tag_key = $stag_data[0];

						if( isset( $stag_data[1] ) ){
							$special_tag_key = $stag_data[1];
						}

						$return[ $special_tag_key ] = apply_filters( 'wpcf7_special_mail_tags', '', $special_tag_name, false, $mail_tag );
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

			$validated_payload = array(
				'form_id'   => WPWHPRO()->helpers->translate( "Form ID", "trigger-cf7" ),
				'form_data' => WPWHPRO()->helpers->translate( "Form Post Data", "trigger-cf7" ),
				'form_data_meta' => WPWHPRO()->helpers->translate( "Form Post Meta", "trigger-cf7" ),
				'form_submit_data' => WPWHPRO()->helpers->translate( "Form Submit Data", "trigger-cf7" ),
				'special_mail_tags' => WPWHPRO()->helpers->translate( "Special Mail Tags", "trigger-cf7" ),
			);

			$contact_forms = get_posts(
			        array(
			                'post_type' => 'wpcf7_contact_form',
							'post_status' => 'publish',
							'numberposts' => -1
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

			$translation_ident = "trigger-trigger-cf7-description";

			ob_start();
?>

<?php echo WPWHPRO()->helpers->translate( "This webhook trigger is used to send data, on the submission of a contact form (Via the Contact Form 7 plugin), to one or multiple given webhook URL's.", $translation_ident ); ?>
<br>
<?php echo WPWHPRO()->helpers->translate( "This description is uniquely made for the <strong>Send Data On Contact Form 7 Submits</strong> (cf7_forms) webhook trigger.", $translation_ident ); ?>
<br><br>
<h4><?php echo WPWHPRO()->helpers->translate( "How to use <strong>Send Data On Contact Form 7 Submits</strong> (cf7_forms)", $translation_ident ); ?></h4>
<ol>
    <li><?php echo WPWHPRO()->helpers->translate( "To get started, you need to add your receiving URL endpoint, that accepts webhook requests, from the third-party provider or service you want to use.", $translation_ident ); ?></li>
    <li><?php echo WPWHPRO()->helpers->translate( "Once you have this URL, please place it into the <strong>Webhook URL</strong> field above.", $translation_ident ); ?></li>
    <li><?php echo WPWHPRO()->helpers->translate( "For better identification of the webhook URL, we recommend to also fill the <strong>Webhook Name</strong> field. This field will be used as the slug for your webhook URL. In case you leave it empty, we will automatically generate a random number as an identifier.", $translation_ident ); ?></li>
    <li><?php echo WPWHPRO()->helpers->translate( "After you added your <strong>Webhook URL</strong>, press the <strong>Add</strong> button to finish adding the entry.", $translation_ident ); ?></li>
    <li><?php echo WPWHPRO()->helpers->translate( "That's it! Now you can receive data on the URL once the trigger fires.", $translation_ident ); ?></li>
    <li><?php echo WPWHPRO()->helpers->translate( "Next to the <strong>Webhook URL</strong>, you will find a settings item, which you can use to customize the payload/request.", $translation_ident ); ?></li>
</ol>
<br><br>

<h4><?php echo WPWHPRO()->helpers->translate( "When does this trigger fire?", $translation_ident ); ?></h4>
<br>
<?php echo WPWHPRO()->helpers->translate( "This trigger is registered on the <strong>wpcf7_mail_sent</strong> hook of the Contact Form 7 plugin:", $translation_ident ); ?> 
<a title="wordpress.org" target="_blank" href="https://de.wordpress.org/plugins/contact-form-7/">https://de.wordpress.org/plugins/contact-form-7/</a>
<br>
<br>
<?php echo WPWHPRO()->helpers->translate( "Here is the call within our code we use to fire this trigger:", $translation_ident ); ?>
<pre>add_action( 'wpcf7_mail_sent', array( $this, 'wpwh_wpcf7_mail_sent' ), 10, 1 );</pre>
<br><br><br>

<h4><?php echo WPWHPRO()->helpers->translate( "Tipps", $translation_ident ); ?></h4>
<ol>
	<li><?php echo WPWHPRO()->helpers->translate( "You can also make the temporary files from Contact Form 7 available for webhook calls. To do that, simply check out the settings of your added webhook endpoint. There you will find a feature called <strong>Preserve uploaded form files</strong>. It allows you to temporarily or permanently cache given files to make them available even after CF7 has deleted them from their structure.", $translation_ident ); ?></li>
    <li>
		<?php echo WPWHPRO()->helpers->translate( "You can also rename the webhook keys within the request by defining an additional attribute within the contact form template. Here is an example:", $translation_ident ); ?>
		<pre>[text your-email wpwhkey:new_key]</pre>
		<?php echo WPWHPRO()->helpers->translate( 'The above example changes the key within the payload from "your-email" to "new_key". To define it, simply set the argument "wpwhkey" and separate the new key using a double point (:)."', $translation_ident ); ?>
	</li>
    <li><?php echo WPWHPRO()->helpers->translate( "In case you don't need a specified webhook URL at the moment, you can simply deactivate it by clicking the <strong>Deactivate</strong> link next to the <strong>Webhook URL</strong>. This results in the specified URL not being fired once the trigger fires.", $translation_ident ); ?></li>
    <li><?php echo WPWHPRO()->helpers->translate( "You can use the <strong>Send demo</strong> button to send a static request to your specified <strong>Webhook URL</strong>. Please note that the data sent within the request might differ from your live data.", $translation_ident ); ?></li>
    <li><?php echo WPWHPRO()->helpers->translate( "Within the <strong>Settings</strong> link next to your <strong>Webhook URL</strong>, you can use customize the functionality of the request. It contains certain default settings like changing the request type the data is sent in, or custom settings, depending on your trigger. An explanation for each setting is right next to it. (Please don't forget to save the settings once you changed them - the button is at the end of the popup.)", $translation_ident ); ?></li>
    <li><?php echo WPWHPRO()->helpers->translate( "You can also check the response you get from the demo webhook call. To check it, simply open the console of your browser and you will find an entry there, which gives you all the details about the response.", $translation_ident ); ?></li>
</ol>
<br><br>

<?php echo WPWHPRO()->helpers->translate( "In case you would like to learn more about our plugin, please check out our documentation at:", $translation_ident ); ?>
<br>
<a title="Go to ironikus.com/docs" target="_blank" href="https://ironikus.com/docs/article-categories/get-started/">https://ironikus.com/docs/article-categories/get-started/</a>
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
					'wpwhpro_cf7_preserve_files' => array(
						'id'          => 'wpwhpro_cf7_preserve_files',
						'type'        => 'text',
						'default_value' => '',
						'label'       => WPWHPRO()->helpers->translate('Preserve uploaded form files', 'wpwhpro-fields-cf7-forms'),
						'placeholder' => 'none',
						'required'    => false,
						'description' => WPWHPRO()->helpers->translate('By default, files are automatically removed once the contact form was sent. Please set a number of the duration on how long the file should be preserved (In seconds). E.g. 180 is equal to three minutes. Type "0" to never delete them or "none" to not save them at all.', 'wpwhpro-fields-cf7-forms-tip')
					),
					'wpwhpro_cf7_customize_payload' => array(
						'id'          => 'wpwhpro_cf7_customize_payload',
						'type'        => 'select',
						'multiple'    => true,
						'choices'      => $validated_payload,
						'label'       => WPWHPRO()->helpers->translate('Cutomize your Payload', 'wpwhpro-fields-cf7-forms'),
						'placeholder' => '',
						'required'    => false,
						'description' => WPWHPRO()->helpers->translate('Select wich of the fields shoud be send along within the Payload. If nothing is selected, all will be send along.', 'wpwhpro-fields-cf7-forms-tip')
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
				'special_mail_tags' => array(
					'custom_key' => 123,
					'another_key' => 'Hello there'
				)
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

			$sub_directory = 'form-' . intval( $form_id ) . '-';
			$starting_random = wp_generate_password( 12, false );
			while( is_dir( $this->get_upload_dir( false, $sub_directory . '/' . $starting_random ) ) ){
				$starting_random = wp_generate_password( 12, false );
			}
			$sub_directory .= $starting_random;

			$webhooks = WPWHPRO()->webhook->get_hooks( 'trigger', 'cf7_forms' );
			foreach( $webhooks as $webhook ){

				$is_valid = true;
				$mail_tags = array();
				$single_data_array = $data_array;

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
							$single_data_array['special_mail_tags'] = $mail_tags;
						}
					}
					
					//Manage the response data
					if( isset( $webhook['settings']['wpwhpro_cf7_customize_payload'] ) && ! empty( $webhook['settings']['wpwhpro_cf7_customize_payload'] ) ){
						$allowed_payload_fields =  $webhook['settings']['wpwhpro_cf7_customize_payload'];
						if( is_array( $allowed_payload_fields ) ){
							foreach( $single_data_array as $single_data_array_key => $single_data_array_val ){
								if( ! in_array( $single_data_array_key, $allowed_payload_fields ) ){
									unset( $single_data_array[ $single_data_array_key ] );
								}
							}
						}
					}

					//Manage the response data
					if( isset( $webhook['settings']['wpwhpro_cf7_preserve_files'] ) ){
						$preserve_files_duration =  $webhook['settings']['wpwhpro_cf7_preserve_files'];

						if( is_numeric( $preserve_files_duration ) && $preserve_files_duration !== 'none' ){
							$preserve_files_duration = intval( $preserve_files_duration );

							if( is_array( $single_data_array['form_submit_data'] ) ){
								foreach( $single_data_array['form_submit_data'] as $single_form_data_key => $single_form_data ){
									if( is_array( $single_form_data ) && isset( $single_form_data['file_name'] ) ){
										$path = $this->get_upload_dir( true, $sub_directory );
										if( ! file_exists( $path . '/' . $single_form_data['file_name'] ) ){
											copy( $single_form_data['absolute_path'], $path . '/' . $single_form_data['file_name'] );
											$single_data_array['form_submit_data'][ $single_form_data_key ] = array(
												'file_name' => wp_basename( $path . '/' . $single_form_data['file_name'] ),
												'file_url' => str_replace( ABSPATH, trim( home_url(), '/' ) . '/', $path . '/' . $single_form_data['file_name'] ),
												'absolute_path' => $path . '/' . $single_form_data['file_name'],
											);

											if( $preserve_files_duration !== 0 ){
												$preserved_files = $this->get_preserved_files();
												$preserved_files[] = array(
													'time_created' => time(),
													'time_to_delete' => ( time() + $preserve_files_duration ),
													'file_path' => $path . '/' . $single_form_data['file_name'],
												);
												$this->update_preserved_files( $preserved_files );
											}
											
										}
									}
								}
							}
						} else {
							if( is_array( $single_data_array['form_submit_data'] ) ){
								foreach( $single_data_array['form_submit_data'] as $single_form_data_key => $single_form_data ){
									if( is_array( $single_form_data ) && isset( $single_form_data['file_name'] ) ){
										$single_data_array['form_submit_data'][ $single_form_data_key ] = '';
									}
								}
							}
						}
					} else { //make sure if nothing was set, we remove it to not show temporary data
						if( is_array( $single_data_array['form_submit_data'] ) ){
							foreach( $single_data_array['form_submit_data'] as $single_form_data_key => $single_form_data ){
								if( is_array( $single_form_data ) && isset( $single_form_data['file_name'] ) ){
									$single_data_array['form_submit_data'][ $single_form_data_key ] = '';
								}
							}
						}
					}
				}

				if( $is_valid ){
					$response_data[] = WPWHPRO()->webhook->post_to_webhook( $webhook, $single_data_array );
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