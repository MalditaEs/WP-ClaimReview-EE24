<?php
/**
 * Create and endpoint to get notices related to the EE24 repository
 *
 * @return void
 */
function euroclimatecheck_notices() {
	$namespace = 'api-euroclimatecheck/v1';
	$route     = 'repository-status';
	register_rest_route(
		$namespace,
		$route,
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => 'get_repository_request_status',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}

add_action( 'rest_api_init', 'euroclimatecheck_notices' );
add_action('current_screen', 'detecting_current_screen');

function detecting_current_screen()
{
	$current_screen = get_current_screen();

    if($current_screen->is_block_editor()){
	    add_action( 'admin_footer-post.php', 'repository_status_script' );
	    add_action( 'admin_footer-post-new.php', 'repository_status_script' );
    }
}

function repository_status_script() {
	?>
    <script type="text/javascript">

        const {subscribe, select} = wp.data;
        const {isSavingPost} = select('core/editor');
        let checked = true;
        subscribe(() => {
            if (isSavingPost()) {
                checked = false;
            } else {
                if (!checked) {
                    checkNotificationAfterPublish();
                    checked = true;
                }

            }
        });

        function checkNotificationAfterPublish() {
            const postId = wp.data.select("core/editor").getCurrentPostId();
            const url = wp.url.addQueryArgs(
                '/wp-json/api-euroclimatecheck/v1/repository-status',
                {id: postId},
            );
            wp.apiFetch({
                url,
            }).then(
                function (response) {
                    if (response.message) {
                        wp.data.dispatch("core/notices").createNotice(
                            response.type,
                            response.message,
                            {
                                id: 'repository_status_notice',
                                isDismissible: true
                            }
                        );
                    }
                }
            );
        };
    </script>
	<?php
}

function get_repository_request_status() {
	if ( isset( $_GET['id'] ) ) {

		$id = sanitize_text_field(
			wp_unslash( $_GET['id'] )
		);

		// Check both transient naming patterns
		$errorTransient   = get_transient( "euroclimatecheck_error" ) ?: get_transient( "ee24_error" );
		$successTransient = get_transient( "euroclimatecheck_success" ) ?: get_transient( "ee24_success" );
		$validationErrors = get_transient( "ee24_validation_errors" );

		if ( $errorTransient ) {
			// Clean up both possible transient names
			delete_transient( 'euroclimatecheck_error' );
			delete_transient( 'ee24_error' );

			if ( $validationErrors ) {
				delete_transient( 'ee24_validation_errors' );
			}

			$errorMessage = "EuroClimateCheck – " . $errorTransient;

			// Include validation errors if they exist
			if ( $validationErrors && is_array($validationErrors) && !empty($validationErrors) ) {
				$errorMessage .= " Missing required fields: " . implode(", ", $validationErrors);
			}

			return new \WP_REST_Response(
				array(
					'type'    => 'error',
					'message' => wp_unslash( $errorMessage ),
				)
			);
		}

		if ( $successTransient ) {
			// Clean up both possible transient names
			delete_transient( 'euroclimatecheck_success' );
			delete_transient( 'ee24_success' );

			return new \WP_REST_Response(
				array(
					'type'    => 'success',
					'message' => wp_unslash( "The EuroClimateCheck Repository has been updated. " . $successTransient ),
				)
			);
		}
	}

	return null;
}
