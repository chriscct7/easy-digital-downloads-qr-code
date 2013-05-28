<?php
/**
 * Plugin Name:         Easy Digital Downloads QR Code
 * Plugin URI:          http://www.easydigitaldownloads.com
 * Description:         Generate QR codes for your products!
 * Author:              Chris Christoff
 * Author URI:          http://www.chriscct7.com
 *
 */
if ( class_exists( 'Easy_Digital_Downloads' ) ) {
    function edd_qrc_updater() {
        define( 'EDD_QR_STORE_URL', 'https://easydigitaldownloads.com' );
        define( 'EDD_QR', 'QR Code' );
        define( 'EDD_QR_VERSION', '1.0' );
        
        if ( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
            include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
        }
        
        // retrieve our license key from the DB
        $license_key = trim( get_option( 'edd_qr_license_key' ) );
        
        // setup the updater
        $edd_updater = new EDD_SL_Plugin_Updater( EDD_QR_STORE_URL, __FILE__, array(
             'version' => EDD_QR_VERSION, // current version number
            'license' => $license_key, // license key (used get_option above to retrieve from DB)
            'item_name' => EDD_QR, // name of this plugin
            'author' => 'Chris Christoff' // author of this plugin
        ) );
    }
    add_action( 'admin_init', 'edd_qrc_updater' );
    add_action( 'admin_menu', 'edd_qr_license_menu' );
    add_action( 'admin_init', 'edd_qr_register_option' );
    add_action( 'admin_init', 'edd_qr_deactivate_license' );
    add_action( 'admin_init', 'edd_qr_activate_license' );
    
    add_action( 'plugins_loaded', 'edd_qr_load' );
    function edd_qr_load() {
        
        class QR_Code_Main {
            
            public function __construct() {
                
                $this->title = __( 'QR Code', 'edd_qrcode' );
                $this->id    = 'edd_qrcodegen';
                
                /* Define the custom box */
                add_action( 'add_meta_boxes', array(
                     &$this,
                    'qrcode_add_custom_box' 
                ) );
                
                /* Do something with the data entered */
                add_action( 'save_post', array(
                     &$this,
                    'qrcode_save_postdata' 
                ) );
                
                /* jQuery AJAX download button eheh */
                add_action( 'wp_ajax_qrcode_download_image', array(
                     &$this,
                    'download_image' 
                ) );
            }
            
            /* Adds a box to the main column on the Post and Page edit screens */
            function qrcode_add_custom_box() {
                add_meta_box( 'qrcode_sectionid', __( 'QR Code', 'edd_qrcode' ), array(
                     &$this,
                    'qrcode_inner_custom_box' 
                ), 'download', 'side' );
            }
            
            function download_image() {
                
                if ( !empty( $_POST[ 'security' ] ) && wp_verify_nonce( $_POST[ 'security' ], 'download-qr-code' ) ) {
                    $api = 'http://qrickit.com/api/qr';
                    
                    $product_id = $_POST[ 'product_id' ];
                    
                    $purchase_link = edd_get_purchase_link( array(
                         'download_id' => $product_id 
                    ) );
                    
                    $args = array();
                    switch ( $_POST[ 'selection' ] ):
                        case 'product_url':
                            $args[ 'd' ] = get_permalink( $product_id );
                            break;
                        case 'add_to_cart_url':
                            $args[ 'd' ] = get_permalink( $product_id ) . '?download_id=' . $product_id . '&edd_action=add_to_cart';
                            break;
                    endswitch;
                    
                    $args[ 'qrsize' ] = 258;
                    $img_url          = add_query_arg( $args, $api );
                    
                    $args[ 'qrsize' ] = 1480;
                    $large_image_url  = add_query_arg( $args, $api );
                    
                    $short_url = $img_url;
                    if ( empty( $error ) ) {
                        $response = array(
                             'url' => $short_url,
                            'img' => $img_url,
                            'large' => $large_image_url 
                        );
                    } else {
                        $response = array(
                             'error_message' => $error 
                        );
                    }
                    echo json_encode( $response );
                }
                
                exit;
            }
            
            /* Prints the box content */
            function qrcode_inner_custom_box( $post ) {
                global $post;
?> 

				<?php
                wp_nonce_field( plugin_basename( __FILE__ ), $this->id . '_wp_nonce' );
?>

				<script>
				jQuery(function() {
					jQuery('#qr-results').hide();
					var current = jQuery('input[name*="edd_qrcodegen"]:checked').val();

					jQuery('input[name*="edd_qrcodegen"]').click(function() {
						var current = jQuery('input[name*="edd_qrcodegen"]:checked').val();

					});

					jQuery('a.download-qrcode').click(function(e) {
						e.preventDefault();

						var data = {
							action     : 'qrcode_download_image',
							product_id : '<?php
                echo $post->ID;
?>',
							selection  : jQuery('input[name*="edd_qrcodegen"]:checked').val(),
							security   : '<?php
                echo wp_create_nonce( "download-qr-code" );
?>'
						};

						jQuery.post( '<?php
                echo admin_url( 'admin-ajax.php' );
?>', data, function(response) {
							jQuery('p#generated-qr-code-error').fadeOut(function() {
								jQuery('div#qr-results').slideUp(function() {
									if ( response.error_message ) {
										jQuery('p#generated-qr-code-error').text(response.error_message).fadeIn();
									} else {
										jQuery('p#generated-qr-code-error').hide();
										jQuery('a#generated-qr-code-large').attr( 'href', response.large );
										jQuery('img#generated-qr-code').attr( 'src', response.img );
										jQuery('input#generated-qr-code-url').attr( 'value', response.url );
										jQuery('div#qr-results').slideDown();
									}
								});
							});
						}, "json");
					});
				});
				</script>

				<p id="generated-qr-code-error" style="color:red;"></p>

				<div id="qr-results">
					<p><img id="generated-qr-code"></p>
					<p><a id="generated-qr-code-large"><?php
                _e( 'Download large (1440x1440)', 'edd_qrcode' );
?></a><br/>
						<?php
                _e( '(right click, save link as)', 'edd_qrcode' );
?>
					</p>
					<p><input type="text" style="width:100%;" readonly="readonly" id="generated-qr-code-url" /></p>
				</div>

				<p>
					<label class="radio">
						<input type="radio" name="edd_qrcodegen[selection]" id="add_to_cart_url" value="add_to_cart_url" <?php
                checked( $meta[ 'selection' ], 'add_to_cart_url', true );
?>>
						<?php
                _e( 'Add to cart URL', 'edd_qrcode' );
?>
					</label>
				</p>

				<p>
					<label class="radio">
						<input type="radio" name="edd_qrcodegen[selection]" id="product_url" value="product_url" <?php
                checked( $meta[ 'selection' ], 'product_url', true );
?>>
						<?php
                _e( 'Product\'s page', 'edd_qrcode' );
?>
					</label>
				</p>
				<?php
                
                echo '<p><a class="button download-qrcode" href="' . add_query_arg( 'get_qrcode', $post->ID ) . '">' . __( 'Generate', 'edd_qrcode' ) . '</a></p>';
                
            }
            
            /* When the post is saved, saves our custom data */
            function qrcode_save_postdata( $post_id ) {
                // verify if this is an auto save routine.
                // If it is our form has not been submitted, so we dont want to do anything
                if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
                    return;
                
                // verify this came from the our screen and with proper authorization,
                // because save_post can be triggered at other times
                
                if ( empty( $_POST[ $this->id ] ) || !wp_verify_nonce( $_POST[ $this->id . '_wp_nonce' ], plugin_basename( __FILE__ ) ) )
                    return;
                if ( !current_user_can( 'edit_post', $post_id ) )
                    return;
                
                // OK, we're authenticated: we need to find and save the data
                $selection = $_POST[ $this->id ];
                
                update_post_meta( $post_id, $this->id . '_meta', $selection );
            }
            
        }
        
        new QR_Code_Main();
    }
    
    function edd_qr_license_menu() {
        add_plugins_page( 'EDD QR', 'EDD QR', 'manage_options', 'edd-qr-license', 'edd_qr_license_page' );
    }
    
    function edd_qr_license_page() {
        $license = get_option( 'edd_qr_license_key' );
        $status  = get_option( 'edd_qr_license_status' );
?>
			<div class="wrap">
				<h2><?php
        _e( 'EDD QR License Options' );
?></h2>
				<form method="post" action="options.php">
				
				<?php
        settings_fields( 'edd_qr_license' );
?>
			
				<table class="form-table">
					<tbody>
						<tr valign="top">	
							<th scope="row" valign="top">
								<?php
        _e( 'License Key' );
?>
							</th>
							<td>
								<input id="edd_qr_license_key" name="edd_qr_license_key" type="text" class="regular-text" value="<?php
        esc_attr_e( $license );
?>" />
								<label class="description" for="edd_qr_license_key"><?php
        _e( 'Enter your license key' );
?></label>
							</td>
						</tr>
						<?php
        if ( false !== $license ) {
?>
						<tr valign="top">	
							<th scope="row" valign="top">
								<?php
            _e( 'Activate License' );
?>
							</th>
							<td>
								<?php
            if ( $status !== false && $status == 'valid' ) {
?>
									<span style="color:green;"><?php
                _e( 'active' );
?></span>
									<?php
                wp_nonce_field( 'edd_qr_nonce', 'edd_qr_nonce' );
?>
									<input type="submit" class="button-secondary" name="edd_license_deactivate" value="<?php
                _e( 'Deactivate License' );
?>"/>
								<?php
            } else {
                wp_nonce_field( 'edd_qr_nonce', 'edd_qr_nonce' );
?>
									<input type="submit" class="button-secondary" name="edd_license_activate" value="<?php
                _e( 'Activate License' );
?>"/>
								<?php
            }
?>
							</td>
						</tr>
					<?php
        }
?>
				</tbody>
				</table>	
				<?php
        submit_button();
?>
		
			</form>
		<?php
    }
    
    function edd_qr_register_option() {
        // creates our settings in the options table
        register_setting( 'edd_qr_license', 'edd_qr_license_key', 'edd_sanitize_license' );
    }
    
    function edd_sanitize_license( $new ) {
        $old = get_option( 'edd_qr_license_key' );
        if ( $old && $old != $new ) {
            delete_option( 'edd_qr_license_status' ); // new license has been entered, so must reactivate
        }
        return $new;
    }
    
    function edd_qr_activate_license() {
        
        // listen for our activate button to be clicked
        if ( isset( $_POST[ 'edd_license_activate' ] ) ) {
            
            // run a quick security check 
            if ( !check_admin_referer( 'edd_qr_nonce', 'edd_qr_nonce' ) )
                return; // get out if we didn't click the Activate button
            
            // retrieve the license from the database
            $license = trim( get_option( 'edd_qr_license_key' ) );
            
            
            // data to send in our API request
            $api_params = array(
                 'edd_action' => 'activate_license',
                'license' => $license,
                'item_name' => urlencode( EDD_QR ) // the name of our product in EDD
            );
            
            // Call the custom API.
            $response = wp_remote_get( add_query_arg( $api_params, EDD_QR_STORE_URL ), array(
                 'timeout' => 15,
                'sslverify' => false 
            ) );
            
            // make sure the response came back okay
            if ( is_wp_error( $response ) )
                return false;
            
            // decode the license data
            $license_data = json_decode( wp_remote_retrieve_body( $response ) );
            
            // $license_data->license will be either "active" or "inactive"
            
            update_option( 'edd_qr_license_status', $license_data->license );
            
        }
    }
    
    function edd_qr_deactivate_license() {
        
        // listen for our activate button to be clicked
        if ( isset( $_POST[ 'edd_license_deactivate' ] ) ) {
            
            // run a quick security check 
            if ( !check_admin_referer( 'edd_qr_nonce', 'edd_qr_nonce' ) )
                return; // get out if we didn't click the Activate button
            
            // retrieve the license from the database
            $license = trim( get_option( 'edd_qr_license_key' ) );
            
            
            // data to send in our API request
            $api_params = array(
                 'edd_action' => 'deactivate_license',
                'license' => $license,
                'item_name' => urlencode( EDD_QR ) // the name of our product in EDD
            );
            
            // Call the custom API.
            $response = wp_remote_get( add_query_arg( $api_params, EDD_QR_STORE_URL ), array(
                 'timeout' => 15,
                'sslverify' => false 
            ) );
            
            // make sure the response came back okay
            if ( is_wp_error( $response ) )
                return false;
            
            // decode the license data
            $license_data = json_decode( wp_remote_retrieve_body( $response ) );
            
            // $license_data->license will be either "deactivated" or "failed"
            if ( $license_data->license == 'deactivated' )
                delete_option( 'edd_qr_license_status' );
            
        }
    }
    
    
    function edd_qr_check_license() {
        
        global $wp_version;
        
        $license = trim( get_option( 'edd_qr_license_key' ) );
        
        $api_params = array(
             'edd_action' => 'check_license',
            'license' => $license,
            'item_name' => urlencode( EDD_QR ) 
        );
        
        // Call the custom API.
        $response = wp_remote_get( add_query_arg( $api_params, EDD_QR_STORE_URL ), array(
             'timeout' => 15,
            'sslverify' => false 
        ) );
        
        
        if ( is_wp_error( $response ) )
            return false;
        
        $license_data = json_decode( wp_remote_retrieve_body( $response ) );
        
        if ( $license_data->license == 'valid' ) {
            return true;
            // this license is still valid
        } else {
            return false;
            // this license is no longer valid
        }
    }
}