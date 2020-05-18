<?php 

/*
Plugin Name: WP Fusion - Limit Post Views
Description: Allows visitors to read 5 blog posts, after which they are prompted to enter an email address to read more. (for https://www.thepublicdiscourse.com/)
Plugin URI: https://verygoodplugins.com/
Version: 1.1
Author: Very Good Plugins
Author URI: https://verygoodplugins.com/
*/

//
// Enqueue scripts and styles
//

function lpv_enqueue_scripts() {

	if( ! is_user_logged_in() || defined( 'DOING_WPF_AUTO_LOGIN' ) ) {

		wp_enqueue_script( 'sweetalert2', plugin_dir_url( __FILE__ ) . '/sweetalert2.min.js' );
		wp_enqueue_style( 'sweetalert2', plugin_dir_url( __FILE__ ) . '/sweetalert2.css' );

		wp_enqueue_script( 'limit-post-views', plugin_dir_url( __FILE__ ) . '/main.js', array( 'jquery', 'sweetalert2' ), '0.3', true );

		global $post;

		wp_localize_script( 'limit-post-views', 'limit_post_views', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'post_id' => $post->ID ) );

	}

}

add_action( 'wp_enqueue_scripts', 'lpv_enqueue_scripts' );


//
// Tag readers based on author and category
//

function lpv_tag_views() {

	$apply_tags = array();

	$contact_data = json_decode( stripslashes( $_COOKIE['wpf_contact'] ), true );

	$post_id = $_POST['post_id'];


	// Author stuff

	$author_id = get_post_field( 'post_author', $post_id );

	$author_tags = get_user_meta( $author_id, 'wpf_author_tags', true );

	if( ! empty( $author_tags ) ) {
		$apply_tags = array_merge( $apply_tags, $author_tags );
	}


	// Category stuff

	$terms = wp_get_post_terms( $post_id, 'pillars' );

	if( ! empty( $terms ) ) {

		$selected_terms = array();

		foreach( $terms as $term ) {
			$selected_terms[] = str_replace('&amp;', '&', $term->name);
		}

		$selected_terms = implode(';', $selected_terms);

		$response = wp_fusion()->crm->update_contact( $contact_data['contact_id'], array( 'Pillar__c' => $selected_terms ), false );

		if( is_wp_error( $response ) ) {

			wp_fusion()->logger->handle( 'error', 0, 'Error updating contact ' . $contact_data['contact_id'] . ' in ' . wp_fusion()->crm->name . ': ' . $response->get_error_message(), array( 'source' => 'limit-post-views' ) );
			return false;

		}

	}

	if( ! empty( $apply_tags ) ) {

		$response = wp_fusion()->crm->apply_tags( $apply_tags, $contact_data['contact_id'] );

		if( is_wp_error( $response ) ) {

			wp_fusion()->logger->handle( 'error', 0, 'Error applying tags to contact ' . $contact_data['contact_id'] . ' in ' . wp_fusion()->crm->name . ': ' . $response->get_error_message(), array( 'source' => 'limit-post-views' ) );
			return false;

		}

	}

}

//
// Check the number of times a visitor has viewed posts, and apply relevant tags
//

function lpv_check_views() {

	if( ! empty( $_COOKIE['wpf_contact'] ) ) {

		lpv_tag_views();
		wp_send_json_success( 'optin' );

	}


	if( ! isset( $_COOKIE['pd_views'] ) ) {
		$views = 0;
	} else {
		$views = $_COOKIE['pd_views'];
	}

	if( $views === 'optin' ) {

		wp_send_json_success( 'optin' );

	} else {

		$views++;

		setcookie( 'pd_views', $views, time() + DAY_IN_SECONDS * 30, COOKIEPATH, COOKIE_DOMAIN );
		wp_send_json_success( $views );

	}

}

add_action( 'wp_ajax_lpv_check_views', 'lpv_check_views' );
add_action( 'wp_ajax_nopriv_lpv_check_views', 'lpv_check_views' );


//
// Save the submitted email to Infusionsoft
//

function lpv_popup_submit() {

	if( function_exists( 'wp_fusion' ) ) {

		$result = wp_fusion()->crm->connect();

		if( is_wp_error( $result ) ) {
			setcookie( 'pd_views', -100, time() + DAY_IN_SECONDS * 30, COOKIEPATH, COOKIE_DOMAIN );

			wp_fusion()->logger->handle( 'error', 0, 'Error connecting to ' . wp_fusion()->crm->name . ': ' . $result->get_error_message(), array( 'source' => 'limit-post-views' ) );
			wp_die();
		}

		$data = json_decode( stripslashes( $_POST['data'] ) );

		if( empty( $data[0] ) || empty( $data[1] ) || empty( $data[2] ) ) {
			wp_die();
		}

		$cid = wp_fusion()->crm->get_contact_id( $data[0] );

		if( empty( $cid ) ) {

			wp_fusion()->logger->handle( 'info', 0, 'Adding contact to ' . wp_fusion()->crm->name . '. Email: ' . $data[2], array( 'source' => 'limit-post-views' ) );

			$cid = wp_fusion()->crm->add_contact( array( 'user_email' => $data[2], 'first_name' => $data[0], 'last_name' => $data[1] ) );

		}

		if( ! is_wp_error( $cid ) ) {

			wp_fusion()->auto_login->start_auto_login( $cid );
			setcookie( 'pd_views', 'optin', time() + DAY_IN_SECONDS * 360, COOKIEPATH, COOKIE_DOMAIN );

		} else {

			setcookie( 'pd_views', -100, time() + DAY_IN_SECONDS * 30, COOKIEPATH, COOKIE_DOMAIN );
			wp_fusion()->logger->handle( 'error', 0, 'Error adding contact to ' . wp_fusion()->crm->name . ': ' . $cid->get_error_message(), array( 'source' => 'limit-post-views' ) );

		}

	}

	wp_die();

}

add_action( 'wp_ajax_nopriv_lpv_popup_submit', 'lpv_popup_submit' );


//
// Allow tagging based on author
//

function lpv_user_profile( $user ) {

	if( ! in_array( 'author', $user->roles ) ) {
		return;
	}

	?>

    <h3>WP Fusion - Public Discourse</h3>

    <table class="form-table">
        <tr>
            <th><label>Author Tags</label></th>
            <td><style>.select4-container{ min-width: 400px; }</style>
            	<?php

					$args = array(
						'setting' 		=> get_user_meta( $user->ID, 'wpf_author_tags', true ),
						'meta_name'		=> 'wpf_author_tags'
					);

					wpf_render_tag_multiselect( $args );

				?>

            	<p class="description">These tags will be applied to a contact when they view a post by this author</p>
            </td>
        </tr>
    </table>

	<?php

}

add_action( 'show_user_profile', 'lpv_user_profile' );
add_action( 'edit_user_profile', 'lpv_user_profile' );

//
// Save author tag settings
//

function lpv_user_profile_update( $user_id ) {

	global $pagenow;

	// See if tags have manually been modified on the user edit screen

	if ( ($pagenow == 'profile.php' || $pagenow == 'user-edit.php') && isset( $_POST[ 'wpf_author_tags' ] ) ) {

		update_user_meta( $user_id, 'wpf_author_tags', $_POST[ 'wpf_author_tags' ] );

	}

}

add_action( 'profile_update', 'lpv_user_profile_update' );


//
// Allow tagging by category
//

function lpv_category_tags( $term, $taxonomy ) {

	?>

	<tr class="form-field">
	    <th scope="row" valign="top"><label>Apply tags on view</label></th>
	    <td><style>.select4-container{ min-width: 400px; }</style>
        	<?php

				$args = array(
					'setting' 		=> get_term_meta( $term->term_id, 'wpf_apply_tags', true ),
					'meta_name'		=> 'wpf_apply_tags'
				);

				wpf_render_tag_multiselect( $args );

			?>

        	<p class="description">These tags will be applied to a contact when they view a post in this category</p>
	    </td>
	</tr>


	<?php

}

add_action( 'category_edit_form_fields', 'lpv_category_tags', 10, 2 );


//
// Save category settings
//

function lpv_save_category_tags( $term_id, $taxonomy_term_id ) {

	if( isset( $_POST['wpf_apply_tags'] ) ) {
		update_term_meta( $term_id, 'wpf_apply_tags', $_POST['wpf_apply_tags'] );
	}

}

add_action( 'edited_category', 'lpv_save_category_tags', 10, 2 );
