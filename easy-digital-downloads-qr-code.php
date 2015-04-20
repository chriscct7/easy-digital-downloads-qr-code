<?php
 /**
 * Plugin Name:         Easy Digital Downloads QR Code
 * Plugin URI:          http://www.chriscct7.com
 * Description:         Generate QR codes for your products!
 * Author:              Chris Christoff
 * Author URI:          http://www.chriscct7.com
 *
 * Contributors:        chriscct7
 *
 * Version:             1.1.1
 * Requires at least:   4.0
 * Tested up to:        4.1
 *
 * Text Domain:         edd_qrcode
 * Domain Path:         /languages/
 *
 * @category            Plugin
 * @copyright           Copyright © 2014 Chris Christoff
 * @author              Chris Christoff
 * @package             EDDQRC
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/** Check if Easy Digital Downloads is active */

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

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
                    $img_url          = esc_url( add_query_arg( $args, $api ) );

                    $args[ 'qrsize' ] = 1480;
                    $large_image_url  = esc_url( add_query_arg( $args, $api ) );

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

                echo '<p><a class="button download-qrcode" href="' . esc_url( add_query_arg( 'get_qrcode', $post->ID ) ) . '">' . __( 'Generate', 'edd_qrcode' ) . '</a></p>';

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
        if ( class_exists( 'Easy_Digital_Downloads' ) ) {
            new QR_Code_Main();
        }
    }
