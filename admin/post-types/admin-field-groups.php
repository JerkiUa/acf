<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

if ( ! class_exists( 'ACF_Admin_Field_Groups' ) ) :
	#[AllowDynamicProperties]
	class ACF_Admin_Field_Groups extends ACF_Admin_Internal_Post_Type_List {

		public $post_type = 'acf-field-group';

		public $admin_body_class = 'acf-admin-field-groups';

		public $store = 'field-groups';

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 7 );
			add_action( 'load-edit.php', array( $this, 'handle_redirection' ) );
			add_action( 'admin_footer', array( $this, 'include_pro_features' ) );

			parent::__construct();
		}

		public function include_pro_features() {

			if ( acf_is_pro() && acf_pro_is_license_active() ) {
				return;
			}

			if ( ! acf_is_screen( 'edit-acf-field-group' ) ) {
				return;
			}

			acf_get_view( $this->post_type . '/pro-features' );
		}

		public function admin_menu() {
			$parent_slug = 'edit.php?post_type=acf-field-group';
			$cap         = acf_get_setting( 'capability' );
			add_submenu_page( $parent_slug, __( 'Field Groups', 'acf' ), __( 'Field Groups', 'acf' ), $cap, $parent_slug );
		}

		public function handle_redirection() {
			if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === 'acf' ) { 
				wp_redirect( $this->get_admin_url() );
				exit;
			}
		}

		public function admin_table_columns( $_columns ) {

			if ( empty( acf_request_arg( 's' ) ) ) {
				global $wp_post_types;
				$this->not_found_label                               = $wp_post_types['acf-field-group']->labels->not_found;
				$wp_post_types['acf-field-group']->labels->not_found = $this->get_not_found_html();
			}

			$columns = array(
				'cb'              => $_columns['cb'],
				'title'           => $_columns['title'],
				'acf-description' => __( 'Description', 'acf' ),
				'acf-key'         => __( 'Key', 'acf' ),
				'acf-location'    => __( 'Location', 'acf' ),
				'acf-count'       => __( 'Fields', 'acf' ),
			);

			if ( acf_get_local_json_files( $this->post_type ) ) {
				$columns['acf-json'] = __( 'Local JSON', 'acf' );
			}

			return $columns;
		}

		public function render_admin_table_column( $column_name, $post ) {
			switch ( $column_name ) {

				case 'acf-key':
					echo '<i class="acf-icon acf-icon-key-solid"></i>';
					echo esc_html( $post['key'] );
					break;

				case 'acf-description':
					if ( is_string( $post['description'] ) && ! empty( $post['description'] ) ) {
						echo '<span class="acf-description">' . acf_esc_html( $post['description'] ) . '</span>';
					} else {
						echo '<span class="acf-emdash" aria-hidden="true">—</span>';
						echo '<span class="screen-reader-text">' . esc_html__( 'No description', 'acf' ) . '</span>';
					}
					break;

				case 'acf-location':
					$this->render_admin_table_column_locations( $post );
					break;

				case 'acf-count':
					$this->render_admin_table_column_num_fields( $post );
					break;

				case 'acf-json':
					$this->render_admin_table_column_local_status( $post );
					break;
			}
		}

		public function render_admin_table_column_locations( $field_group ) {
			$objects = array();

			if ( $field_group['location'] ) {
				foreach ( $field_group['location'] as $i => $rules ) {

					foreach ( $rules as $j => $rule ) {

						$location                = acf_get_location_rule( $rule['param'] );
						$location_object_type    = '';
						$location_object_subtype = '';
						if ( $location ) {
							$location_object_type    = $location->get_object_type( $rule );
							$location_object_subtype = $location->get_object_subtype( $rule );
						}
						$rules[ $j ]['object_type']    = $location_object_type;
						$rules[ $j ]['object_subtype'] = $location_object_subtype;
					}

					$object_types = array_column( $rules, 'object_type' );
					$object_types = array_filter( $object_types );
					$object_types = array_values( $object_types );
					if ( $object_types ) {
						$object_type = $object_types[0];
					} else {
						continue;
					}

					$object_subtypes = array_column( $rules, 'object_subtype' );
					$object_subtypes = array_filter( $object_subtypes );
					$object_subtypes = array_values( $object_subtypes );
					$object_subtypes = array_map( 'acf_array', $object_subtypes );
					if ( count( $object_subtypes ) > 1 ) {
						$object_subtypes = call_user_func_array( 'array_intersect', $object_subtypes );
						$object_subtypes = array_values( $object_subtypes );
					} elseif ( $object_subtypes ) {
						$object_subtypes = $object_subtypes[0];
					} else {
						$object_subtypes = array( '' );
					}

					foreach ( $object_subtypes as $object_subtype ) {
						$object = acf_get_object_type( $object_type, $object_subtype );
						if ( $object ) {
							$objects[ $object->name ] = $object;
						}
					}
				}
			}

			$objects = array_values( $objects );

			$html = '';
			if ( $objects ) {
				$limit = 3;
				$total = count( $objects );

				$html .= '<span class="dashicons ' . $objects[0]->icon . ( $total > 1 ? ' acf-multi-dashicon' : '' ) . '"></span>';

				$labels = array_column( $objects, 'label' );
				$labels = array_slice( $labels, 0, 3 );
				$html  .= implode( ', ', $labels );

				if ( $total > $limit ) {
					$html .= ', ...';
				}
			} else {
				$html = '<span class="dashicons dashicons-businesswoman"></span> ' . __( 'Various', 'acf' );
			}

			echo acf_esc_html( $html );
		}

		public function render_admin_table_column_num_fields( $field_group ) {
			$field_count = acf_get_field_count( $field_group );

			if ( ! $field_count || ! is_numeric( $field_count ) ) {
				echo '<span class="acf-emdash" aria-hidden="true">—</span>';
				echo '<span class="screen-reader-text">' . esc_html__( 'No fields', 'acf' ) . '</span>';
				return;
			}

			if ( empty( $field_group['ID'] ) || 'trash' === get_post_status( $field_group['ID'] ) ) {
				echo esc_html( number_format_i18n( $field_count ) );
				return;
			}

			printf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'post.php?action=edit&post=' . $field_group['ID'] ) ),
				esc_html( number_format_i18n( $field_count ) )
			);
		}

		public function trashed_post( $post_id ) {
			if ( get_post_type( $post_id ) === $this->post_type ) {
				acf_trash_field_group( $post_id );
			}
		}

		public function untrashed_post( $post_id ) {
			if ( get_post_type( $post_id ) === $this->post_type ) {
				acf_untrash_field_group( $post_id );
			}
		}

		public function deleted_post( $post_id ) {
			if ( get_post_type( $post_id ) === $this->post_type ) {
				acf_delete_field_group( $post_id );
			}
		}

		public function get_action_notice_text( $action, $count = 1 ) {
			$text  = '';
			$count = (int) $count;

			switch ( $action ) {
				case 'acfactivatecomplete':
					$text = sprintf(

						_n( 'Field group activated.', '%s field groups activated.', $count, 'acf' ),
						$count
					);
					break;
				case 'acfdeactivatecomplete':
					$text = sprintf(

						_n( 'Field group deactivated.', '%s field groups deactivated.', $count, 'acf' ),
						$count
					);
					break;
				case 'acfduplicatecomplete':
					$text = sprintf(

						_n( 'Field group duplicated.', '%s field groups duplicated.', $count, 'acf' ),
						$count
					);
					break;
				case 'acfsynccomplete':
					$text = sprintf(

						_n( 'Field group synchronized.', '%s field groups synchronized.', $count, 'acf' ),
						$count
					);
					break;
			}

			return $text;
		}
	}

	acf_new_instance( 'ACF_Admin_Field_Groups' );
endif; 