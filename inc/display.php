<?php

/**
 * Build the JSON LD for the Claim Review
 *
 * @return void
 */
function claim_review_build_json_ld() {

	// Check if the post is singlular.
	if ( is_singular() ) {

		// Check if the post itself is able to have the claim review
		global $post;
		$allvalidposttypes = get_option( 'cr-post-types' );
		$post_type         = get_post_type( $post );


		if ( array_key_exists( 'cr-showon' . $post_type, $allvalidposttypes ) ) {

			if ( ! $allvalidposttypes['cr-showon' . $post_type] ) {
				return;
			}

			$jsonarray = array();

			$claimtoreviewid = $post->ID;

			$allclaims = get_post_meta( $claimtoreviewid, '_fullfact_all_claims', true );

			if ( $allclaims ) {
				$url         = get_permalink( $claimtoreviewid );
				$date        = get_the_date( 'Y-m-d', $claimtoreviewid );
				$orgname     = get_option( 'cr-organisation-name' );
				$orgurl      = get_option( 'cr-organisation-url' );
				$maxrating   = get_option( 'cr-organisation-max-number-rating' );
				$minrating   = get_option( 'cr-organisation-min-number-rating' );
				$temparray   = array();
				$authorarray = array(
					'@type' => 'Organization',
					'name'  => $orgname,
					'url'   => $orgurl
				);

				foreach ( $allclaims as $claim ) {

					// If we have no claim to review, or a rating for it
					if ( '' == $claim['claimreviewed'] || '' == $claim['assessment'] ) {
						$temparray = array();
						continue;
					}



					// Start adding data
					$temparray['@context']      = "http://schema.org";
					$temparray['@type']         = "ClaimReview";
					$temparray['datePublished'] = $date;
					$temparray['author']        = $authorarray;
					$temparray['claimReviewed'] = $claim['claimreviewed'];

					/* if ( array_key_exists( 'anchor', $claim ) ) {
						$temparray['@id'] = trailingslashit( $url ) . '#' . $claim['anchor'];
					} else {
						$temparray['@id'] = trailingslashit( $url ) . '#' . $claim['anchor'];
					} */

					// Add Claim items, as well as first appearances
					$appearance      = array();
					$firstappearance = array();
					$itemobject      = array();

					if ( ! empty( $claim['appearance']['url'] ) ) {

						$originalappearance = $claim['appearance']['original'];
						$firstitem          = TRUE;

						foreach ( $claim['appearance']['url'] as $itemurl ) {

							if ( filter_var( $itemurl, FILTER_VALIDATE_URL) === FALSE ) {
								continue;
							}

							if ( $originalappearance && $firstitem ) {
								$firstappearance['@type'] = 'CreativeWork';
								$firstappearance['url']   = $itemurl;
								$firstitem = FALSE;
							} else {
								$appearance[] = array(
									'@type' => 'CreativeWork',
									'url'   => $itemurl
								);
							}

						}
					}

					if ( ! empty( $appearance ) ) {
						$itemobject['appearance'] = $appearance;
					}

					if ( !empty( $firstappearance ) ) {
						$itemobject['firstAppearance'] = $firstappearance;
					}

					// Add the claim location
					if ( '' != $claim['location'] ) {
						$itemobject['name'] = $claim['location'];
					}


					// Author Data
					$author = array();
					if ( '' != $claim['author'] ) {
						$author['name'] = $claim['author'];
					}

					if ( filter_var( $claim['image'] , FILTER_VALIDATE_URL) !== FALSE ) {
						$author['image'] = $claim['image'];
					}

					if ( '' != $claim['job-title'] ) {
						$author['jobTitle'] = $claim['job-title'];
					}

					if ( !empty( $author ) ) {
						$author['@type'] = "Person";
						$itemobject['author'] = $author;
					}

					if ( '' != $claim['date'] ) {
						$itemobject['datePublished'] = $claim['date'];
					}

					// And now if Item Object isn't empty, we add it to the temp array
					if ( !empty( $itemobject ) ) {
						$itemobject['@type']         = 'Claim';
						$temparray['itemReviewed']   = $itemobject;
					}

					// Rating
					$reviewrating = array();
					$reviewrating['@type']         = 'Rating';
					$reviewrating['alternateName'] = $claim['assessment'];

					// Score out of 5
					if ( '' != 'numeric-rating' && '-1' != $maxrating && '-1' != $minrating ) {
						$reviewrating['bestRating']  = $maxrating;
						$reviewrating['worstRating'] = $minrating;
						$reviewrating['ratingValue'] = $claim['numeric-rating'];
					}

					// Rating Image
					if ( filter_var( $claim['rating-image'] , FILTER_VALIDATE_URL) !== FALSE ) {
						$reviewrating['image'] = $claim['rating-image'];
					}

					$temparray['reviewRating'] = $reviewrating;

					// Add the URL At the End
					if ( array_key_exists( 'anchor', $claim ) && $claim['anchor'] != '' ) {
						$temparray['url'] = trailingslashit( $url ) . '#' . $claim['anchor'];
					} else {
						$temparray['url'] = trailingslashit( $url );
					}

					// Now add to the end
					$jsonarray[] = $temparray;

				}

				// Now let's build the JSON
				if ( !empty( $jsonarray ) ) {
					$schemastring = json_encode( $jsonarray, JSON_UNESCAPED_SLASHES );
					echo '<script type="application/ld+json">' . $schemastring . '</script>';
				}
			}
		}
	}
} add_action( 'wp_head', 'claim_review_build_json_ld' );