<?php
/**
 * Manage BuddyPress activity items.
 *
 * @since 1.5.0
 */
class BPCLI_Activity extends BPCLI_Component {

	/**
	 * Create an activity item.
	 *
	 * ## OPTIONS
	 *
	 * [--component=<component>]
	 * : The component for the activity item (groups, activity, etc). If
	 * none is provided, a component will be randomly selected from the
	 * active components.
	 *
	 * [--type=<type>]
	 * : Activity type (activity_update, group_created, etc). If none is
	 * provided, a type will be randomly chose from those natively
	 * associated with your <component>.
	 *
	 * [--action=<action>]
	 * : Action text (eg "Joe created a new group Foo"). If none is
	 * provided, one will be generated automatically based on other params.
	 *
	 * [--content=<content>]
	 * : Activity content text. If none is provided, default text will be
	 * generated.
	 *
	 * [--primary-link=<primary-link>]
	 * : URL of the item, as used in RSS feeds. If none is provided, a URL
	 * will be generated based on passed parameters.
	 *
	 * [--user-id=<user>]
	 * : ID of the user associated with the new item. If none is provided,
	 * a user will be randomly selected.
	 *
	 * [--item-id=<item-id>]
	 * : ID of the associated item. If none is provided, one will be
	 * generated automatically, if your activity type requires it.
	 *
	 * [--secondary-item-id=<secondary-item-id>]
	 * : ID of the secondary associated item. If none is provided, one will
	 * be generated automatically, if your activity type requires it.
	 *
	 * [--date-recorded=<date-recorded>]
	 * : GMT timestamp, in Y-m-d h:i:s format.
	 * ---
	 * Default: Current time
	 * ---
	 *
	 * [--hide-sitewide=<hide-sitewide>]
	 * : Whether to hide in sitewide streams.
	 * ---
	 * Default: 0
	 * ---
	 *
	 * [--is-spam=<is-spam>]
	 * : Whether the item should be marked as spam.
	 * ---
	 * Default: 0
	 * ---
	 *
	 * [--silent=<silent>]
	 * : Whether to silent the activity creation.
	 * ---
	 * Default: false
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp activity create --is-spam=1
	 *     Success: Successfully created new activity item (ID #5464)
	 *
	 *     $ wp bp activity add --component=groups --user-id=10
	 *     Success: Successfully created new activity item (ID #48949)
	 *
	 * @alias add
	 */
	public function create( $args, $assoc_args ) {
		$r = wp_parse_args( $assoc_args, array(
			'component'         => '',
			'type'              => '',
			'action'            => '',
			'content'           => '',
			'primary-link'      => '',
			'user-id'           => '',
			'item-id'           => '',
			'secondary-item-id' => '',
			'date-recorded'     => bp_core_current_time(),
			'hide-sitewide'     => 0,
			'is-spam'           => 0,
			'silent'            => false,
		) );

		// Fill in any missing information.
		if ( empty( $r['component'] ) ) {
			$r['component'] = $this->get_random_component();
		}

		if ( empty( $r['type'] ) ) {
			$r['type'] = $this->get_random_type_from_component( $r['component'] );
		}

		if ( 'groups' === $r['component'] ) {
			// Item ID for groups is a group ID.
			// Therefore, handle group slugs, too.
			// Convert --item-id to group ID.
			// @todo this'll be screwed up if the group has a numeric slug.
			if ( $r['item-id'] && ! is_numeric( $r['item-id'] ) ) {
				$r['item-id'] = groups_get_id( $r['item-id'] );
			}
		}

		// If some data is not set, we have to generate it.
		if ( empty( $r['item_id'] ) || empty( $r['secondary_item_id'] ) ) {
			$r = $this->generate_item_details( $r );
		}

		$id = bp_activity_add( array(
			'action'            => $r['action'],
			'content'           => $r['content'],
			'component'         => $r['component'],
			'type'              => $r['type'],
			'primary_link'      => $r['primary-link'],
			'user_id'           => $r['user-id'],
			'item_id'           => $r['item-id'],
			'secondary_item_id' => $r['secondary-item-id'],
			'date_recorded'     => $r['date-recorded'],
			'hide_sitewide'     => (bool) $r['hide-sitewide'],
			'is_spam'           => (bool) $r['is-spam'],
		) );

		if ( $r['silent'] ) {
			return;
		}

		if ( $id ) {
			WP_CLI::success( sprintf( 'Successfully created new activity item (ID #%d)', $id ) );
		} else {
			WP_CLI::error( 'Could not create activity item.' );
		}
	}

	/**
	 * Retrieve a list of activities.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : One or more parameters to pass to BP_Activity_Activity::get()
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 *  ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each activity:
	 *
	 * * ID
	 * * user_id
	 * * component
	 * * type
	 * * action
	 * * content
	 * * item_id
	 * * secondary_item_id
	 * * primary_link
	 * * date_recorded
	 * * is_spam
	 * * user_email
	 * * user_nicename
	 * * user_login
	 * * display_name
	 * * user_fullname
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp activity list --format=ids
	 *     $ wp bp activity list --format=count
	 *     $ wp bp activity list --per_page=5
	 *     $ wp bp activity list --search_terms="Activity Comment"
	 *
	 * @subcommand list
	 */
	public function _list( $_, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		$r = wp_parse_args( $assoc_args, array(
			'page'        => 1,
			'per_page'    => -1,
			'count_total' => false,
			'show_hidden' => true,
		) );

		$r = self::process_csv_arguments_to_arrays( $r );

		if ( 'ids' === $formatter->format ) {
			$r['fields'] = 'ids';
			$activities  = bp_activity_get( $r );

			echo implode( ' ', $activities['activities'] ); // WPCS: XSS ok.
		} elseif ( 'count' === $formatter->format ) {
			$r['fields']      = 'ids';
			$r['count_total'] = true;
			$activities       = bp_activity_get( $r );

			$formatter->display_items( $activities['activities'] );
		} else {
			$activities = bp_activity_get( $r );
			$formatter->display_items( $activities['activities'] );
		}
	}

	/**
	 * Generate random activity items.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : How many activity items to generate.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--skip-activity-comments=<skip-activity-comments>
	 * : Whether to skip activity comments. Recording activity_comment
	 * items requires a resource-intensive tree rebuild.
	 * ---
	 * Default: 1
	 * ---
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp bp activity generate --count=50
	 */
	public function generate( $args, $assoc_args ) {
		$component = $this->get_random_component();
		$type      = $this->get_random_type_from_component( $component );

		if ( (bool) $assoc_args['skip-activity-comments'] && 'activity_comment' === $type ) {
			$type = 'activity_update';
		}

		$notify = \WP_CLI\Utils\make_progress_bar( 'Generating activity items', $assoc_args['count'] );

		for ( $i = 0; $i < $assoc_args['count']; $i++ ) {
			$this->create( array(), array(
				'component' => $component,
				'type'      => $type,
				'silent'    => true,
			) );

			$notify->tick();
		}

		$notify->finish();
	}

	/**
	 * Fetch specific activity.
	 *
	 * ## OPTIONS
	 *
	 * <activity-id>
	 * : Identifier for the activity.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 * ---
	 * default: All fields.
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 *  ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - haml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp activity get 500
	 *     $ wp bp activity get 56 --format=json
	 */
	public function get( $args, $assoc_args ) {
		$activity_id = $args[0];

		$activity = new BP_Activity_Activity( $activity_id );

		if ( empty( $activity->id ) ) {
			WP_CLI::error( 'No activity found by that ID.' );
		}

		$activity = bp_activity_get_specific( array(
			'activity_ids' => $activity_id,
		) );

		$activity = $activity['activities'];

		if ( is_object( $activity ) ) {
			$activity_arr = get_object_vars( $activity );

			if ( empty( $assoc_args['fields'] ) ) {
				$assoc_args['fields'] = array_keys( $activity_arr );
			}

			$formatter = $this->get_formatter( $assoc_args );
			$formatter->display_item( $activity_arr );
		} else {
			WP_CLI::error( 'Could not find the activity.' );
		}
	}

	/**
	 * Delete an activity.
	 *
	 * ## OPTIONS
	 *
	 * <activity-id>
	 * : Identifier for the activity.
	 *
	 * --yes
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp bp activity delete 500 --yes
	 *     Success: Activity deleted.
	 */
	public function delete( $args, $assoc_args ) {
		WP_CLI::confirm( 'Are you sure you want to delete this activity?', $assoc_args );

		$retval = bp_activity_delete( array(
			'id' => $args[0],
		) );

		// Delete activity. True if deleted.
		if ( $retval ) {
			WP_CLI::success( 'Activity deleted.' );
		} else {
			WP_CLI::error( 'Could not delete the activity.' );
		}
	}

	/**
	 * Spam an activity.
	 *
	 * ## OPTIONS
	 *
	 * <activity-id>
	 * : Identifier for the activity.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp activity spam 500
	 *     Success: Activity marked as spam.
	 *
	 *     $ wp bp activity unham 165165
	 *     Success: Activity marked as spam.
	 *
	 * @alias unham
	 */
	public function spam( $args, $assoc_args ) {
		$activity = new BP_Activity_Activity( $args[0] );

		if ( empty( $activity->id ) ) {
			WP_CLI::error( 'No activity found by that ID.' );
		}

		// Mark as spam.
		bp_activity_mark_as_spam( $activity );

		if ( $activity->save() ) {
			WP_CLI::success( 'Activity marked as spam.' );
		} else {
			WP_CLI::error( 'Could not mark the activity as spam.' );
		}
	}

	/**
	 * Ham an activity.
	 *
	 * ## OPTIONS
	 *
	 * <activity-id>
	 * : Identifier for the activity.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp activity ham 500
	 *     Success: Activity marked as ham.
	 *
	 *     $ wp bp activity unspam 4679
	 *     Success: Activity marked as ham.
	 *
	 * @alias unspam
	 */
	public function ham( $args, $assoc_args ) {
		$activity = new BP_Activity_Activity( $args[0] );

		if ( empty( $activity->id ) ) {
			WP_CLI::error( 'No activity found by that ID.' );
		}

		// Mark as ham.
		bp_activity_mark_as_ham( $activity );

		if ( $activity->save() ) {
			WP_CLI::success( 'Activity marked as ham.' );
		} else {
			WP_CLI::error( 'Could not mark the activity as ham.' );
		}
	}

	/**
	 * Post an activity update.
	 *
	 * ## OPTIONS
	 *
	 * [--user-id=<user>]
	 * : ID of the user. If none is provided, a user will be randomly selected.
	 *
	 * [--content=<content>]
	 * : Activity content text. If none is provided, default text will be generated.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp activity post_update --user-id=50 --content="Content to update"
	 *     Success: Successfully updated with a new activity item (ID #13165)
	 *
	 *     $ wp bp activity post_update --user-id=140
	 *     Success: Successfully updated with a new activity item (ID #4548)
	 */
	public function post_update( $args, $assoc_args ) {
		$r = wp_parse_args( $assoc_args, array(
			'content' => $this->generate_random_text(),
			'user-id' => $this->get_random_user_id(),
		) );

		// Post the activity update.
		$id = bp_activity_post_update( array(
			'content' => $r['content'],
			'user_id' => (int) $r['user-id'],
		) );

		// Activity ID returned on success update.
		if ( is_numeric( $id ) ) {
			WP_CLI::success( sprintf( 'Successfully updated with a new activity item (ID #%d)', $id ) );
		} else {
			WP_CLI::error( 'Could not post the activity update.' );
		}
	}

	/**
	 * Add an activity comment.
	 *
	 * ## OPTIONS
	 *
	 * <activity-id>
	 * : ID of the activity to add the comment.
	 *
	 * [--user-id=<user>]
	 * : ID of the user. If none is provided, a user will be randomly selected.
	 *
	 * [--content=<content>]
	 * : Activity content text. If none is provided, default text will be generated.
	 *
	 * [--skip-notification=<skip-notification>]
	 * : Whether to skip notification.
	 * * ---
	 * default: false
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp activity comment 560 --user-id=50 --content="New activity comment"
	 *     Success: Successfully added a new activity comment (ID #4645)
	 *
	 *     $ wp bp activity comment 459 --user-id=140 --skip-notification=1
	 *     Success: Successfully added a new activity comment (ID #494)
	 */
	public function comment( $args, $assoc_args ) {
		$r = wp_parse_args( $assoc_args,array(
			'content'           => $this->generate_random_text(),
			'user-id'           => $this->get_random_user_id(),
			'skip-notification' => false,
		) );

		$activity = new BP_Activity_Activity( $args[0] );

		if ( empty( $activity->id ) ) {
			WP_CLI::error( 'No activity found by that ID.' );
		}

		// Add activity comment.
		$id = bp_activity_new_comment( array(
			'content'           => $r['content'],
			'user_id'           => (int) $r['user-id'],
			'activity_id'       => $activity->id,
			'skip_notification' => $r['skip-notification'],
		) );

		// Activity Comment ID returned on success.
		if ( is_numeric( $id ) ) {
			WP_CLI::success( sprintf( 'Successfully added a new activity comment (ID #%d)', $id ) );
		} else {
			WP_CLI::error( 'Could not post a new activity comment.' );
		}
	}

	/**
	 * Delete an activity comment.
	 *
	 * ## OPTIONS
	 *
	 * <activity-id>
	 * : Identifier for the activity.
	 *
	 * <comment-id>
	 * : ID of the comment to delete.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp bp activity delete_comment 100 500
	 *     Success: Activity comment deleted.
	 */
	public function delete_comment( $args, $assoc_args ) {
		$activity_id = $args[0];

		$activity = new BP_Activity_Activity( $activity_id );

		if ( empty( $activity->id ) ) {
			WP_CLI::error( 'No activity found by that ID.' );
		}

		$deleted = bp_activity_delete_comment( array(
			'activity_id' => (int) $activity_id,
			'comment_id'  => (int) $args[1],
		) );

		// Delete Comment. True if deleted.
		if ( $deleted ) {
			WP_CLI::success( 'Activity comment deleted.' );
		} else {
			WP_CLI::error( 'Could not delete the activity comment.' );
		}
	}

	/**
	 * Get the permalink for a single activity item.
	 *
	 * ## OPTIONS
	 *
	 * <activity-id>
	 * : Identifier for the activity.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp activity permalink 687
	 *     Success: Activity Permalink: https://site.com/activity/p/6465
	 *
	 *     $ wp bp activity url 16546
	 *     Success: Activity Permalink: https://site.com/activity/p/16546
	 *
	 * @alias url
	 */
	public function permalink( $args, $assoc_args ) {
		$activity_id = $args[0];

		$activity = new BP_Activity_Activity( $activity_id );

		if ( empty( $activity->id ) ) {
			WP_CLI::error( 'No activity found by that ID.' );
		}

		$permalink = bp_activity_get_permalink( $activity_id );

		if ( is_string( $permalink ) ) {
			WP_CLI::success( sprintf( 'Activity Permalink: %s', $permalink ) );
		} else {
			WP_CLI::error( 'No permalink found by that ID.' );
		}
	}

	/**
	 * Pull up a random active component for use in activity items.
	 *
	 * @since 1.1
	 *
	 * @return string
	 */
	protected function get_random_component() {
		$c = buddypress()->active_components;

		// Core components that accept activity items.
		$ca = $this->get_components_and_actions();

		return array_rand( array_flip( array_intersect( array_keys( $c ), array_keys( $ca ) ) ) );
	}

	/**
	 * Get a random type from a component.
	 *
	 * @since 1.1
	 *
	 * @param string $component Component name.
	 * @return string
	 */
	protected function get_random_type_from_component( $component ) {
		$ca = $this->get_components_and_actions();
		return array_rand( array_flip( $ca[ $component ] ) );
	}

	/**
	 * Get a list of activity components and actions
	 *
	 * @todo Add filter for plugins (when merged on BP core)
	 *
	 * @since 1.1
	 *
	 * @return array
	 */
	protected function get_components_and_actions() {
		return array(
			'activity' => array(
				'activity_update',
				'activity_comment',
			),
			'blogs' => array(
				'new_blog',
				'new_blog_post',
				'new_blog_comment',
			),
			'friends' => array(
				'friendship_created',
			),
			'groups' => array(
				'joined_group',
				'created_group',
			),
			'profile' => array(
				'new_avatar',
				'new_member',
				'updated_profile',
			),
		);
	}

	/**
	 * Generate item details.
	 *
	 * @since 1.1
	 */
	protected function generate_item_details( $r ) {
		global $wpdb, $bp;

		switch ( $r['type'] ) {
			case 'activity_update':
				if ( empty( $r['user-id'] ) ) {
					$r['user-id'] = $this->get_random_user_id();
				}

				// Make group updates look more like actual group updates.
				// i.e. give them links to their groups.
				if ( 'groups' === $r['component'] ) {

					if ( empty( $r['item-id'] ) ) {
						WP_CLI::error( 'No group found by that ID.' );
					}

					// get the group.
					$group_obj = groups_get_group( array(
						'group_id' => $r['item-id'],
					) );

					// make sure such a group exists.
					if ( empty( $group_obj->id ) ) {
						WP_CLI::error( 'No group found by that slug or id.' );
					}

					// stolen from groups_join_group.
					$r['action']  = sprintf( __( '%1$s posted an update in the group %2$s', 'buddypress'), bp_core_get_userlink( $r['user-id'] ), '<a href="' . bp_get_group_permalink( $group_obj ) . '">' . esc_attr( $group_obj->name ) . '</a>' );
				} else {
					// old way, for some other kind of update.
					$r['action'] = sprintf( __( '%s posted an update', 'buddypress' ), bp_core_get_userlink( $r['user-id'] ) );
				}
				if ( empty( $r['content'] ) ) {
					$r['content'] = $this->generate_random_text();
				}

				$r['primary-link'] = bp_core_get_userlink( $r['user-id'] );

				break;

			case 'activity_comment':
				if ( empty( $r['user-id'] ) ) {
					$r['user-id'] = $this->get_random_user_id();
				}

				$parent_item = $wpdb->get_row( "SELECT * FROM {$bp->activity->table_name} ORDER BY RAND() LIMIT 1" );

				if ( 'activity_comment' === $parent_item->type ) {
					$r['item-id'] = $parent_item->id;
					$r['secondary-item-id'] = $parent_item->secondary_item_id;
				} else {
					$r['item-id'] = $parent_item->id;
				}

				$r['action'] = sprintf( __( '%s posted a new activity comment', 'buddypress' ), bp_core_get_userlink( $r['user-id'] ) );
				$r['content'] = $this->generate_random_text();
				$r['primary-link'] = bp_core_get_userlink( $r['user-id'] );

				break;

			case 'new_blog':
			case 'new_blog_post':
			case 'new_blog_comment':
				if ( ! bp_is_active( 'blogs' ) ) {
					return $r;
				}

				if ( is_multisite() ) {
					$r['item-id'] = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} ORDER BY RAND() LIMIT 1" );
				} else {
					$r['item-id'] = 1;
				}

				// Need blog content for posts/comments.
				if ( 'new_blog_post' === $r['type'] || 'new_blog_comment' === $r['type'] ) {

					if ( is_multisite() ) {
						switch_to_blog( $r['item-id'] );
					}

					$comment_info = $wpdb->get_results( "SELECT comment_id, comment_post_id FROM {$wpdb->comments} ORDER BY RAND() LIMIT 1" );
					$comment_id = $comment_info[0]->comment_id;
					$comment = get_comment( $comment_id );

					$post_id = $comment_info[0]->comment_post_id;
					$post = get_post( $post_id );

					if ( is_multisite() ) {
						restore_current_blog();
					}
				}

				// new_blog.
				if ( 'new_blog' === $r['type'] ) {
					if ( '' === $r['user-id'] ) {
						$r['user-id'] = $this->get_random_user_id();
					}

					if ( ! $r['action'] ) {
						$r['action'] = sprintf( __( '%s created the site %s', 'buddypress'), bp_core_get_userlink( $r['user-id'] ), '<a href="' . get_home_url( $r['item-id'] ) . '">' . esc_attr( get_blog_option( $r['item-id'], 'blogname' ) ) . '</a>' );
					}

					if ( ! $r['primary-link'] ) {
						$r['primary-link'] = get_home_url( $r['item-id'] );
					}

				// new_blog_post.
				} elseif ( 'new_blog_post' === $r['type'] ) {
					if ( '' === $r['user-id'] ) {
						$r['user-id'] = $post->post_author;
					}

					if ( '' === $r['primary-link'] ) {
						$r['primary-link'] = add_query_arg( 'p', $post->ID, trailingslashit( get_home_url( $r['item-id'] ) ) );
					}

					if ( '' === $r['action'] ) {
						$r['action'] = sprintf( __( '%1$s wrote a new post, %2$s', 'buddypress' ), bp_core_get_userlink( (int) $post->post_author ), '<a href="' . $r['primary-link'] . '">' . $post->post_title . '</a>' );
					}

					if ( '' === $r['content'] ) {
						$r['content'] = $post->post_content;
					}

					if ( '' === $r['secondary-item-id'] ) {
						$r['secondary-item-id'] = $post->ID;
					}

				// new_blog_comment.
				} else {
					// groan - have to fake this.
					if ( '' === $r['user-id'] ) {
						$user = get_user_by( 'email', $comment->comment_author_email );
						$r['user-id'] = ( empty( $user ) )
							? $this->get_random_user_id()
							: $user->ID;
					}

					$post_permalink = get_permalink( $comment->comment_post_ID );
					$comment_link   = get_comment_link( $comment->comment_ID );

					if ( '' === $r['primary-link'] ) {
						$r['primary-link'] = $comment_link;
					}

					if ( '' === $r['action'] ) {
						$r['action'] = sprintf( __( '%1$s commented on the post, %2$s', 'buddypress' ), bp_core_get_userlink( $r['user-id'] ), '<a href="' . $post_permalink . '">' . apply_filters( 'the_title', $post->post_title ) . '</a>' );
					}

					if ( '' === $r['content'] ) {
						$r['content'] = $comment->comment_content;
					}

					if ( '' === $r['secondary-item-id'] ) {
						$r['secondary-item-id'] = $comment->ID;
					}
				}

				$r['content'] = '';

				break;

			case 'friendship_created':
				if ( empty( $r['user-id'] ) ) {
					$r['user-id'] = $this->get_random_user_id();
				}

				if ( empty( $r['item-id'] ) ) {
					$r['item-id'] = $this->get_random_user_id();
				}

				$r['action'] = sprintf( __( '%1$s and %2$s are now friends', 'buddypress' ), bp_core_get_userlink( $r['user-id'] ), bp_core_get_userlink( $r['item-id'] ) );

				break;

			case 'created_group':
				if ( empty( $r['item-id'] ) ) {
					$r['item-id'] = $this->get_random_group_id();
				}

				$group = groups_get_group( array(
					'group_id' => $r['item-id'],
				) );

				// @todo what if it's not a group? ugh
				if ( empty( $r['user-id'] ) ) {
					$r['user-id'] = $group->creator_id;
				}

				$group_permalink = bp_get_group_permalink( $group );

				if ( empty( $r['action'] ) ) {
					$r['action'] = sprintf( __( '%1$s created the group %2$s', 'buddypress'), bp_core_get_userlink( $r['user-id'] ), '<a href="' . $group_permalink . '">' . esc_attr( $group->name ) . '</a>' );
				}

				if ( empty( $r['primary-link'] ) ) {
					$r['primary-link'] = $group_permalink;
				}

				break;

			case 'joined_group':
				if ( empty( $r['item-id'] ) ) {
					$r['item-id'] = $this->get_random_group_id();
				}

				$group = groups_get_group( array(
					'group_id' => $r['item-id'],
				) );

				if ( empty( $r['user-id'] ) ) {
					$r['user-id'] = $this->get_random_user_id();
				}

				if ( empty( $r['action'] ) ) {
					$r['action'] = sprintf( __( '%1$s joined the group %2$s', 'buddypress' ), bp_core_get_userlink( $r['user-id'] ), '<a href="' . bp_get_group_permalink( $group ) . '">' . esc_attr( $group->name ) . '</a>' );
				}

				if ( empty( $r['primary-link'] ) ) {
					$r['primary-link'] = bp_get_group_permalink( $group );
				}

				break;

			case 'new_avatar':
			case 'new_member':
			case 'updated_profile':
				if ( empty( $r['user-id'] ) ) {
					$r['user-id'] = $this->get_random_user_id();
				}

				$userlink = bp_core_get_userlink( $r['user-id'] );

				// new_avatar.
				if ( 'new_avatar' === $r['type'] ) {
					$r['action'] = sprintf( __( '%s changed their profile picture', 'buddypress' ), $userlink );

				// new_member.
				} elseif ( 'new_member' === $r['type'] ) {
					$r['action'] = sprintf( __( '%s became a registered member', 'buddypress' ), $userlink );

				// updated_profile.
				} else {
					$r['action'] = sprintf( __( '%s updated their profile', 'buddypress' ), $userlink );
				}

				break;
		}

		return $r;
	}
}

WP_CLI::add_command( 'bp activity', 'BPCLI_Activity', array(
	'before_invoke' => function() {
		if ( ! bp_is_active( 'activity' ) ) {
			WP_CLI::error( 'The Activity component is not active.' );
		}
	},
) );
