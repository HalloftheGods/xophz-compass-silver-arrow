<?php

class Xophz_Compass_Silver_Arrow_Rest {

	private $namespace = 'xophz-compass/v1';

	public function register_routes() {
		register_rest_route( $this->namespace, '/silver-arrow/pages', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_targetable_pages' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		));

		register_rest_route( $this->namespace, '/silver-arrow/targeted', array(
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'get_targeted_pages' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			),
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'target_page' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		));

		register_rest_route( $this->namespace, '/silver-arrow/targeted/(?P<id>\d+)', array(
			'methods'  => 'DELETE',
			'callback' => array( $this, 'untarget_page' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		));

		register_rest_route( $this->namespace, '/silver-arrow/revisions/(?P<id>\d+)', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_revisions' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		));

		register_rest_route( $this->namespace, '/silver-arrow/revisions/(?P<id>\d+)/discard', array(
			'methods'  => 'DELETE',
			'callback' => array( $this, 'discard_revision' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		));

		register_rest_route( $this->namespace, '/silver-arrow/revisions/(?P<id>\d+)/restore', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'restore_revision' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		));

		register_rest_route( $this->namespace, '/silver-arrow/tests', array(
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'get_tests' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			),
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'create_test' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		));

		register_rest_route( $this->namespace, '/silver-arrow/tests/(?P<id>\d+)', array(
			array(
				'methods'  => 'PUT',
				'callback' => array( $this, 'update_test' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			),
			array(
				'methods'  => 'DELETE',
				'callback' => array( $this, 'delete_test' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		));

		register_rest_route( $this->namespace, '/silver-arrow/tests/(?P<id>\d+)/toggle', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'toggle_test' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		));

		register_rest_route( $this->namespace, '/silver-arrow/analytics/(?P<id>\d+)', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_analytics' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		));

		register_rest_route( $this->namespace, '/silver-arrow/analytics/test/(?P<id>\d+)', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_test_analytics' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		));
	}

	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	public function get_targetable_pages() {
		$pages = get_posts( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC'
		));

		$items = array();
		foreach ( $pages as $page ) {
			$is_targeted = get_post_meta( $page->ID, '_sa_is_targeted', true );
			$items[] = array(
				'id'          => $page->ID,
				'title'       => $page->post_title,
				'type'        => $page->post_type,
				'url'         => get_permalink( $page->ID ),
				'is_targeted' => (bool) $is_targeted,
				'modified'    => $page->post_modified
			);
		}

		return rest_ensure_response( $items );
	}

	public function get_targeted_pages() {
		global $wpdb;

		$targeted_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sa_is_targeted' AND meta_value = '1'"
		);

		if ( empty( $targeted_ids ) ) {
			return rest_ensure_response( array() );
		}

		$pages = get_posts( array(
			'post_type'      => array( 'page', 'post' ),
			'post__in'       => array_map( 'intval', $targeted_ids ),
			'posts_per_page' => -1
		));

		$items = array();
		foreach ( $pages as $page ) {
			$revisions = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_name FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' ORDER BY post_date DESC",
				$page->ID
			) );
			$active_tests = get_posts( array(
				'post_type'   => 'sa_test',
				'meta_key'    => '_sa_target_post_id',
				'meta_value'  => $page->ID,
				'post_status' => 'publish',
				'posts_per_page' => -1
			));

			$items[] = array(
				'id'             => $page->ID,
				'title'          => $page->post_title,
				'type'           => $page->post_type,
				'url'            => get_permalink( $page->ID ),
				'revision_count' => count( $revisions ) + 1, // +1 to include the current live page in the count
				'active_tests'   => count( $active_tests ),
				'modified'       => $page->post_modified,
				'debug_revisions'=> $revisions
			);
		}

		return rest_ensure_response( $items );
	}

	public function target_page( $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) );
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Page not found', array( 'status' => 404 ) );
		}

		update_post_meta( $post_id, '_sa_is_targeted', '1' );

		return rest_ensure_response( array( 'success' => true, 'post_id' => $post_id ) );
	}

	public function untarget_page( $request ) {
		$post_id = intval( $request['id'] );
		delete_post_meta( $post_id, '_sa_is_targeted' );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_revisions( $request ) {
		$post_id = intval( $request['id'] );
		$parent_post = get_post( $post_id );
		
		// Debug logging to determine why revisions might be missing
		global $wpdb;
		$revisions = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' ORDER BY post_date DESC",
			$post_id
		) );

		$items = array();

		// Ensure we always include the current live page as the 'Current' revision.
		if ( $parent_post ) {
			$has_archive = (bool) get_post_meta( $parent_post->ID, '_sa_meta_archive_' . $parent_post->ID, true );
			$impressions = $this->aggregate_meta( $parent_post->ID, '_sa_impressions_global_' );
			$conversions = $this->aggregate_meta( $parent_post->ID, '_sa_conversions_global_' );
			$conversion_rate = $impressions > 0 ? round( ( $conversions / $impressions ) * 100, 2 ) : 0;

			$items[] = array(
				'id'              => $parent_post->ID,
				'post_parent'     => $parent_post->post_parent,
				'title'           => $parent_post->post_title . ' (Current)',
				'date'            => $parent_post->post_modified,
				'author'          => get_the_author_meta( 'display_name', $parent_post->post_author ),
				'has_meta_archive'=> $has_archive,
				'impressions'     => $impressions,
				'conversions'     => $conversions,
				'conversion_rate' => $conversion_rate
			);
		}

		foreach ( $revisions as $rev ) {
			$has_archive = (bool) get_post_meta( $post_id, '_sa_meta_archive_' . $rev->ID, true );

			$impressions = $this->aggregate_meta( $rev->ID, '_sa_impressions_global_' );
			$conversions = $this->aggregate_meta( $rev->ID, '_sa_conversions_global_' );
			$conversion_rate = $impressions > 0 ? round( ( $conversions / $impressions ) * 100, 2 ) : 0;

			$items[] = array(
				'id'              => $rev->ID,
				'post_parent'     => $rev->post_parent,
				'title'           => $rev->post_title,
				'date'            => $rev->post_date,
				'author'          => get_the_author_meta( 'display_name', $rev->post_author ),
				'has_meta_archive'=> $has_archive,
				'impressions'     => $impressions,
				'conversions'     => $conversions,
				'conversion_rate' => $conversion_rate
			);
		}

		return rest_ensure_response( $items );
	}

	public function discard_revision( $request ) {
		$rev_id = intval( $request['id'] );
		$revision = get_post( $rev_id );

		if ( ! $revision || $revision->post_type !== 'revision' ) {
			return new WP_Error( 'invalid', 'Not a valid revision', array( 'status' => 400 ) );
		}

		$post_id = $revision->post_parent;
		delete_post_meta( $post_id, '_sa_meta_archive_' . $rev_id );
		wp_delete_post_revision( $rev_id );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function restore_revision( $request ) {
		$rev_id = intval( $request['id'] );
		$result = wp_restore_post_revision( $rev_id );

		if ( ! $result ) {
			return new WP_Error( 'restore_failed', 'Failed to restore revision', array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'success' => true, 'post_id' => $result ) );
	}

	public function get_tests( $request ) {
		$post_id = $request->get_param( 'post_id' );

		$args = array(
			'post_type'      => 'sa_test',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1
		);

		if ( $post_id ) {
			$args['meta_key'] = '_sa_target_post_id';
			$args['meta_value'] = intval( $post_id );
		}

		$tests = get_posts( $args );

		$items = array();
		foreach ( $tests as $test ) {
			$target_id = get_post_meta( $test->ID, '_sa_target_post_id', true );
			$config = get_post_meta( $test->ID, '_sa_test_config', true );
			$weight = get_post_meta( $test->ID, '_sa_global_weight', true );

			$items[] = array(
				'id'             => $test->ID,
				'title'          => $test->post_title,
				'status'         => $test->post_status,
				'target_post_id' => intval( $target_id ),
				'target_title'   => get_the_title( $target_id ),
				'global_weight'  => intval( $weight ),
				'config'         => $config ? $config : array( 'revisions' => array() ),
				'created'        => $test->post_date
			);
		}

		return rest_ensure_response( $items );
	}

	public function create_test( $request ) {
		$params = $request->get_json_params();
		$target_post_id = intval( $params['target_post_id'] );
		$title = sanitize_text_field( $params['title'] );
		$config = isset( $params['config'] ) ? $params['config'] : array( 'revisions' => array() );
		$weight = isset( $params['global_weight'] ) ? intval( $params['global_weight'] ) : 50;

		$test_id = wp_insert_post( array(
			'post_type'   => 'sa_test',
			'post_title'  => $title,
			'post_status' => 'draft'
		));

		if ( is_wp_error( $test_id ) ) {
			return $test_id;
		}

		update_post_meta( $test_id, '_sa_target_post_id', $target_post_id );
		update_post_meta( $test_id, '_sa_test_config', $config );
		update_post_meta( $test_id, '_sa_global_weight', $weight );

		return rest_ensure_response( array(
			'success' => true,
			'id'      => $test_id
		));
	}

	public function update_test( $request ) {
		$test_id = intval( $request['id'] );
		$params = $request->get_json_params();

		if ( isset( $params['title'] ) ) {
			wp_update_post( array(
				'ID'         => $test_id,
				'post_title' => sanitize_text_field( $params['title'] )
			));
		}

		if ( isset( $params['config'] ) ) {
			update_post_meta( $test_id, '_sa_test_config', $params['config'] );
		}

		if ( isset( $params['global_weight'] ) ) {
			update_post_meta( $test_id, '_sa_global_weight', intval( $params['global_weight'] ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_test( $request ) {
		$test_id = intval( $request['id'] );
		$result = wp_delete_post( $test_id, true );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', 'Failed to delete test', array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function toggle_test( $request ) {
		$test_id = intval( $request['id'] );
		$test = get_post( $test_id );

		if ( ! $test ) {
			return new WP_Error( 'not_found', 'Test not found', array( 'status' => 404 ) );
		}

		$new_status = $test->post_status === 'publish' ? 'draft' : 'publish';
		wp_update_post( array(
			'ID'          => $test_id,
			'post_status' => $new_status
		));

		return rest_ensure_response( array( 'success' => true, 'status' => $new_status ) );
	}

	public function get_analytics( $request ) {
		$post_id = intval( $request['id'] );
		global $wpdb;
		$revisions = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_date FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' ORDER BY post_date DESC",
			$post_id
		) );

		$timeline = array();
		foreach ( $revisions as $rev ) {
			$daily_data = $this->get_daily_breakdown( $rev->ID, '_sa_impressions_global_', '_sa_conversions_global_' );
			$total_impressions = $this->aggregate_meta( $rev->ID, '_sa_impressions_global_' );
			$total_conversions = $this->aggregate_meta( $rev->ID, '_sa_conversions_global_' );

			$timeline[] = array(
				'rev_id'           => $rev->ID,
				'title'            => $rev->post_title,
				'date'             => $rev->post_date,
				'total_impressions'=> $total_impressions,
				'total_conversions'=> $total_conversions,
				'conversion_rate'  => $total_impressions > 0 ? round( ( $total_conversions / $total_impressions ) * 100, 2 ) : 0,
				'daily'            => $daily_data
			);
		}

		return rest_ensure_response( $timeline );
	}

	public function get_test_analytics( $request ) {
		$test_id = intval( $request['id'] );
		$config = get_post_meta( $test_id, '_sa_test_config', true );

		if ( ! $config || empty( $config['revisions'] ) ) {
			return rest_ensure_response( array() );
		}

		$results = array();
		foreach ( $config['revisions'] as $rev_config ) {
			$rev_id = intval( $rev_config['id'] );
			$impressions = $this->aggregate_meta( $rev_id, "_sa_impressions_test_{$test_id}_" );
			$conversions = $this->aggregate_meta( $rev_id, "_sa_conversions_test_{$test_id}_" );
			$daily = $this->get_daily_breakdown( $rev_id, "_sa_impressions_test_{$test_id}_", "_sa_conversions_test_{$test_id}_" );

			$results[] = array(
				'rev_id'           => $rev_id,
				'weight'           => intval( $rev_config['weight'] ),
				'total_impressions'=> $impressions,
				'total_conversions'=> $conversions,
				'conversion_rate'  => $impressions > 0 ? round( ( $conversions / $impressions ) * 100, 2 ) : 0,
				'daily'            => $daily
			);
		}

		return rest_ensure_response( $results );
	}

	private function aggregate_meta( $post_id, $prefix ) {
		global $wpdb;
		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
			$post_id,
			$wpdb->esc_like( $prefix ) . '%'
		));
		return intval( $total );
	}

	private function get_daily_breakdown( $post_id, $impressions_prefix, $conversions_prefix ) {
		global $wpdb;

		$impression_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
			$post_id,
			$wpdb->esc_like( $impressions_prefix ) . '%'
		));

		$conversion_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
			$post_id,
			$wpdb->esc_like( $conversions_prefix ) . '%'
		));

		$daily = array();

		foreach ( $impression_rows as $row ) {
			$date = substr( $row->meta_key, -8 );
			$formatted = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
			if ( ! isset( $daily[$formatted] ) ) {
				$daily[$formatted] = array( 'date' => $formatted, 'impressions' => 0, 'conversions' => 0 );
			}
			$daily[$formatted]['impressions'] += intval( $row->meta_value );
		}

		foreach ( $conversion_rows as $row ) {
			$date = substr( $row->meta_key, -8 );
			$formatted = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
			if ( ! isset( $daily[$formatted] ) ) {
				$daily[$formatted] = array( 'date' => $formatted, 'impressions' => 0, 'conversions' => 0 );
			}
			$daily[$formatted]['conversions'] += intval( $row->meta_value );
		}

		ksort( $daily );
		return array_values( $daily );
	}
}
