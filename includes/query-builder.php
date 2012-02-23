<?php

class BP_Docs_Query {
	var $post_type_name;
	var $associated_item_tax_name;
	
	var $item_type;
	var $item_id;
	var $item_name;
	var $item_slug;
	
	var $doc_id;
	var $doc_slug;
	
	var $current_view;
	
	var $term_id;
	var $item_type_term_id;
	var $user_term_id;
	
	var $is_new_doc;
	
	var $query_args;
	var $query;
	
	/**
	 * PHP 4 constructor
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 */
	function bp_docs_query() {
		$this->__construct();
	}

	/**
	 * PHP 5 constructor
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 */	
	function __construct( $args = array() ) {
		global $bp;
		
		$this->post_type_name 		= $bp->bp_docs->post_type_name;
		$this->associated_item_tax_name	= $bp->bp_docs->associated_item_tax_name;
	
		$this->item_type 		= $this->get_item_type();
		$this->setup_item();
		$this->current_view 		= $this->get_current_view();
		
		// Get the item slug, if there is one available
		if ( $this->current_view == 'single' || $this->current_view == 'edit' || $this->current_view == 'delete' || $this->current_view == 'history' ) {
			$this->doc_slug = $this->get_doc_slug();
		} else {
			$this->doc_slug = '';
		}
		
		$defaults = array(
			'doc_id'	 => array(),     // Array or comma-separated string
			'doc_slug'	 => $this->doc_slug, // String
			'group_id'	 => array(),     // Array or comma-separated string
			'parent_id'	 => 0,		 // int
			'author_id'	 => array(),     // Array or comma-separated string
			'tags'		 => array(),     // Array or comma-separated string
			'order'		 => 'ASC',       // ASC or DESC
			'orderby'	 => 'modified',  // 'modified', 'title', 'author', 'created'
			'paged'		 => 1,
			'posts_per_page' => 10,
			'search_terms'   => ''
		);
		$r = wp_parse_args( $args, $defaults );
		
		$this->query_args = $r;
		
	}

	/**
	 * Gets the item type of the item you're looking at - e.g 'group', 'user'.
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 *
	 * @return str $view The current item type
	 */
	function get_item_type() {
		global $bp;
		
		$type = apply_filters( 'bp_docs_get_item_type', 'user', $this );
		
		// Stuffing into the $bp global for later use. Barf.
		$bp->bp_docs->current_item_type = $type;
		
		return $type;
	}
	
	/**
	 * Gets the doc slug as represented in the URL
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 *
	 * @return str $view The current doc slug
	 */
	function get_doc_slug() {
		global $bp;
		
		$slug = apply_filters( 'bp_docs_this_doc_slug', '', $this );
		
		return $slug;
	}
	
	/**
	 * Gets the item id of the item (eg group, user) associated with the page you're on.
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 *
	 * @return str $view The current item type
	 */
	function setup_item() {
		global $bp;
		
		if ( empty( $this->item_type ) )
			return false;
		
		$id = '';
		$name = '';
		$slug = '';
		
		switch ( $this->item_type ) {
			case 'group' :
				if ( !empty( $bp->groups->current_group->id ) ) {
					$id   = $bp->groups->current_group->id;
					$name = $bp->groups->current_group->name;
					$slug = $bp->groups->current_group->slug;
				}
				break;
			case 'user' :
				if ( !empty( $bp->displayed_user->id ) ) {
					$id   = bp_displayed_user_id();
					$name = bp_get_displayed_user_fullname();
					$slug = bp_get_displayed_user_username();
				}
				break;
		}
		
		// Todo: abstract into groups. Will be a pain
		$this->item_id 		= apply_filters( 'bp_docs_get_item_id', $id );
		$this->item_name 	= apply_filters( 'bp_docs_get_item_name', $name );
		$this->item_slug 	= apply_filters( 'bp_docs_get_item_slug', $slug );
		
		// Put some stuff in $bp
		$bp->bp_docs->current_item	= $this->item_id;
	}
	
	/**
	 * Gets the id of the taxonomy term associated with the item
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 *
	 * @return str $view The current item type
	 */
	function setup_terms() {
		global $bp;
		
		$this->term_id = bp_docs_get_item_term_id( $this->item_id, $this->item_type, $this->item_name );
		
		if ( bp_is_user() ) {
			// If this is a User Doc, then the user_term_id is the same as the term_id
			$this->user_term_id = $this->term_id;
		} else {
			$this->user_term_id = bp_docs_get_item_term_id( $this->item_id, 'user',  bp_get_loggedin_user_fullname() );
		}
	}

	/**
	 * Gets the current view, based on the page you're looking at.
	 *
	 * Filter 'bp_docs_get_current_view' to extend to different components.
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 *
	 * @param str $item_type Defaults to the object's item type
	 * @return str $view The current view. Core values: edit, single, list, category
	 */
	function get_current_view( $item_type = false ) {
		global $bp;
		
		$view = '';
		
		if ( !$item_type )
			$item_type = $this->item_type;
		
		$view = apply_filters( 'bp_docs_get_current_view', $view, $item_type );
	
		// Stuffing into the $bp global for later use. Cheating, I know.
		$bp->bp_docs->current_view = $view;
	
		return $view;
	}
	
	function get_wp_query() {
		global $bp, $wpdb;
	
		// Set up the basic args
		$wp_query_args = array(
			'post_type'  => $this->post_type_name,
			'tax_query'  => array(),
			'meta_query' => array(),
		);
		
		// Skip everything else if this is a single doc query
		if ( $doc_id = (int)$this->query_args['doc_id'] ) {
			$wp_query_args['ID'] = $doc_id;
		} else if ( $doc_slug = $this->query_args['doc_slug'] ) {
			$wp_query_args['name'] = $doc_slug;
		} else {
				
			// Only call this here to reduce database calls on other pages
			$this->setup_terms();
		
			// Pagination and order args carry over directly
			foreach ( array( 'order', 'orderby', 'paged', 'posts_per_page' ) as $key ) {
				$wp_query_args[$key] = $this->query_args[$key];
			}
			
			// Only add a search parameter if it's been passed
			if ( !empty( $this->query_args['search_terms'] ) ) {
				$wp_query_args['s'] = $this->query_args['search_terms'];
			}
			
			// Set the taxonomy query. Filtered so that plugins can alter the query
			// Filtering by groups also happens in this way
			$wp_query_args['tax_query'] = apply_filters( 'bp_docs_tax_query', array(
				array( 
					'taxonomy'	=> $this->associated_item_tax_name,
					'terms' 	=> array( $this->term_id ),
					'field'		=> 'id'
				),
			), &$this );
			
			if ( !empty( $this->query_args['parent_id'] ) ) {
				$wp_query_args['post_parent'] = $this->query_args['parent_id'];
			}
		}
		
		// Filter these arguments just before they're sent to WP_Query
		// Devs: This allows you to send any custom parameter you'd like, and modify the
		// query appropriately
		$wp_query_args = apply_filters( 'bp_docs_pre_query_args', $wp_query_args, &$this );
		
		$this->query = new WP_Query( $wp_query_args );
		
		//echo $yes;var_dump( $wp_query_args ); die();
		return $this->query;
	}
	
	/**
	 * Used to be use to build the query_posts() query. Now handled by get_wp_query() and
	 * bp_docs_has_docs()
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 * @deprecated 1.2
	 */
	function build_query() {
		_deprecated_function( __FUNCTION__, '1.2', 'No longer used. See bp_docs_has_docs() and BP_Docs_Query::get_wp_query().' );
	}
	
	/**
	 * Fires the WP query and loads the appropriate template
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 */
	function load_template() {
		global $bp, $post;
		
		// Docs are stored on the root blog
		if ( !bp_is_root_blog() )
			switch_to_blog( BP_ROOT_BLOG );
		
		switch ( $this->current_view ) {
			case 'create' :
				// Todo: Make sure the user has permission to create
								
				/** 
				 * Load the template tags for the edit screen
				 */
				if ( !function_exists( 'wp_tiny_mce' ) ) {
					$this->define_wp_tiny_mce();
				}
				
				require BP_DOCS_INCLUDES_PATH . 'templatetags-edit.php';
				
				$template = 'edit-doc.php';
				break;
			case 'list' :
				
				$template = 'docs-loop.php';				
				break;
			case 'category' :
				// Check to make sure the category exists
				// If not, redirect back to list view with error
				// Otherwise, get args based on category ID
				// Then load the loop template
				break;
			case 'single' :
			case 'edit' :
			case 'delete' :
			case 'history' :
				
				// If this is the edit screen, we won't really be able to use a 
				// regular have_posts() loop in the template, so we'll stash the
				// post in the $bp global for the edit-specific template tags
				if ( $this->current_view == 'edit' ) {
					if ( bp_docs_has_docs() ) : while ( bp_docs_has_docs() ) : bp_docs_the_doc();
						$bp->bp_docs->current_post = $post;
										
						// Set an edit lock
						wp_set_post_lock( $post->ID );
					endwhile; endif;
					
					/** 
					 * Load the template tags for the edit screen
					 */
					require BP_DOCS_INCLUDES_PATH . 'templatetags-edit.php';
				}

				switch ( $this->current_view ) {
					case 'single' :
						$template = 'single/index.php';	
						break;
					case 'edit' :
						$template = 'single/edit.php';
						break;
					case 'history' :
						$template = 'single/history.php';
						break;
				
				}
				// Todo: Maybe some sort of error if there is no edit permission?
	
				break;
		}
		
		// Only register on the root blog
		if ( !bp_is_root_blog() )
			restore_current_blog();
		
		$template_path = bp_docs_locate_template( $template );
		
		if ( !empty( $template ) )
			include( apply_filters( 'bp_docs_template', $template_path, $this ) );
	}

	/**
	 * Saves a doc.
	 *
	 * This method handles saving for both new and existing docs. It detects the difference by
	 * looking for the presence of $this->doc_slug
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 */
	function save( $args = false ) {
		global $bp;
		
		check_admin_referer( 'bp_docs_save' );
		
		// Get the required taxonomy items associated with the group. We only run this
		// on a save because it requires extra database hits.
		$this->setup_terms();
		
		// Set up the default value for the result message
		$results = array(
			'message' => __( 'Unknown error. Please try again.', 'bp-docs' ),
			'redirect' => 'create'
		);
		
		// Backward compatibility. Had to change to doc_content to work with wp_editor
		$doc_content = '';
		if ( isset( $_POST['doc_content'] ) ) {
			$doc_content = $_POST['doc_content'];
		} else if ( isset( $_POST['doc']['content'] ) ) {
			$doc_content = $_POST['doc']['content'];
		}
		
		if ( empty( $_POST['doc']['title'] ) || empty( $doc_content ) ) {
			// Both the title and the content fields are required
			$result['message'] = __( 'Both the title and the content fields are required.', 'bp-doc' );
			$result['redirect'] = $this->current_view;
		} else {
			// If both the title and content fields are filled in, we can proceed
			$defaults = array(
				'post_type'    => $this->post_type_name,
				'post_title'   => $_POST['doc']['title'],
				'post_name'    => isset( $_POST['doc']['permalink'] ) ? sanitize_title( $_POST['doc']['permalink'] ) : sanitize_title( $_POST['doc']['title'] ),
				'post_content' => stripslashes( sanitize_post_field( 'post_content', $doc_content, 0, 'db' ) ),
				'post_status'  => 'publish'
			);
			
			$r = wp_parse_args( $args, $defaults );

			if ( empty( $this->doc_slug ) ) {
				$this->is_new_doc = true;
				
				$r['post_author'] = bp_loggedin_user_id();
				
				// This is a new doc
				if ( !$post_id = wp_insert_post( $r ) ) {
					$result['message'] = __( 'There was an error when creating the doc.', 'bp-doc' );
					$result['redirect'] = 'create';
				} else {
					// If the doc was saved successfully, place it in the proper tax
					wp_set_post_terms( $post_id, $this->term_id, $this->associated_item_tax_name, true );
					
					$this->doc_id = $post_id;
					
					$the_doc = get_post( $this->doc_id );
					$this->doc_slug = $the_doc->post_name;
					
					// A normal, successful save
					$result['message'] = __( 'Doc successfully created!', 'bp-doc' );
					$result['redirect'] = 'single';
				}				
			} else {
				$this->is_new_doc = false;
				
				// This is an existing doc, so we need to get the post ID
				$the_doc_args = array(
					'name' => $this->doc_slug,
					'post_type' => $this->post_type_name
				);
				
				$the_docs = get_posts( $the_doc_args );			
				$this->doc_id = $the_docs[0]->ID;	
					
				$r['ID'] 		= $this->doc_id;
				$r['post_author'] 	= $the_docs[0]->post_author; 
				
				// Make sure the post_name is set
				if ( empty( $r['post_name'] ) )
					$r['post_name'] = sanitize_title( $r['post_title'] );
				
				// Make sure the post_name is unique
				$r['post_name'] = wp_unique_post_slug( $r['post_name'], $this->doc_id, $r['post_status'], $this->post_type_name, $the_docs[0]->post_parent );
				
				$this->doc_slug = $r['post_name'];
				
				if ( !wp_update_post( $r ) ) {
					$result['message'] = __( 'There was an error when saving the doc.', 'bp-doc' );
					$result['redirect'] = 'edit';
				} else {
					// Remove the edit lock
					delete_post_meta( $this->doc_id, '_edit_lock' );
					
					// When the post has been autosaved, we need to leave a
					// special success message
					if ( !empty( $_POST['is_auto'] ) && $_POST['is_auto'] ) {
						$result['message'] = __( 'You idled a bit too long while in Edit mode. In order to allow others to edit the doc you were working on, your changes have been autosaved. Click the Edit button to return to Edit mode.', 'bp-docs' );
					} else {
						// A normal, successful save
						$result['message'] = __( 'Doc successfully edited!', 'bp-doc' );
					}
					$result['redirect'] = 'single';
				}
				
				$post_id = $this->doc_id;
			}
			
			// Make sure the current user is added as one of the authors
			wp_set_post_terms( $post_id, $this->user_term_id, $this->associated_item_tax_name, true );
			
			// Save the last editor id. We'll use this to create an activity item
			update_post_meta( $this->doc_id, 'bp_docs_last_editor', bp_loggedin_user_id() );
			
			// Save settings
			if ( !empty( $_POST['settings'] ) ) {
				update_post_meta( $this->doc_id, 'bp_docs_settings', $_POST['settings'] );
			}
			
			// Provide a custom hook for plugins and optional components.
			// WP's default save_post isn't enough, because we need something that fires
			// only when we save from the front end (for things like taxonomies, which
			// the WP admin handles automatically)
			do_action( 'bp_docs_doc_saved', $this );
		}

		$message_type = $result['redirect'] == 'single' ? 'success' : 'error';
		bp_core_add_message( $result['message'], $message_type );
		
		// todo: abstract this out so I don't have to call group permalink here
		$redirect_url = trailingslashit( bp_is_group() ? bp_get_group_permalink( $bp->groups->current_group ) . $bp->bp_docs->slug : bp_displayed_user_domain() . bp_docs_get_slug() );
		
		if ( $result['redirect'] == 'single' ) {
			$redirect_url .= $this->doc_slug;
		} else if ( $result['redirect'] == 'edit' ) {
			$redirect_url .= $this->doc_slug . '/' . BP_DOCS_EDIT_SLUG;
		} else if ( $result['redirect'] == 'create' ) {
			$redirect_url .= BP_DOCS_CREATE_SLUG;
		}
		
		bp_core_redirect( $redirect_url );
	}	
	
	/**
	 * In WP 3.3, wp_tiny_mce() was deprecated, with its JS loading handled by the_editor. So we just
	 * provide a dummy function, for backward template support.
	 *
	 * @package BuddyPress_Docs
	 * @since 1.1.19
	 */
	function define_wp_tiny_mce() {
		function wp_tiny_mce() {
			return;
		}
	}


}

?>