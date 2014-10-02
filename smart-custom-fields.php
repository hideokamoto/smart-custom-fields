<?php
/**
 * Plugin name: Smart Custom Fields
 * Plugin URI: https://github.com/inc2734/smart-custom-fields/
 * Description: Smart Custom Fields is a simple plugin that management custom fields.
 * Version: 1.0.0
 * Author: Takashi Kitajima
 * Author URI: http://2inc.org
 * Created: September 23, 2014
 * Modified:
 * Text Domain: smart-custom-fields
 * Domain Path: /languages/
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
class Smart_Custom_Fields {

	/**
	 * post_custom 格納用
	 * 何度も関数呼び出ししなくて良いように保存
	 */
	protected $post_custom = array();

	/**
	 * repeat_checkboxes
	 * 何度も関数呼び出ししなくて良いように保存
	 */
	protected $repeat_checkboxes = array();

	/**
	 * Fields
	 * Smart_Custom_Fields_Fields のインスタンス
	 */
	protected $Fields;

	/**
	 * __construct
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );
	}

	/**
	 * plugins_loaded
	 */
	public function plugins_loaded() {
		require_once plugin_dir_path( __FILE__ ) . 'classes/class.config.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/class.settings.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/class.fields.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/class.revisions.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/class.scf.php';
		new Smart_Custom_Fields_Settings();
		$this->Fields = new Smart_Custom_Fields_Fields();
		new Smart_Custom_Fields_Revisions();
		new SCF();

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ) );
		add_action( 'wp_ajax_smart-cf-relational-posts-search', array( $this, 'relational_posts_search' ) );
	}

	/**
	 * uninstall
	 */
	public static function uninstall() {
		$cf_posts = get_posts( array(
			'post_type'      => SCF_Config::NAME,
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );
		foreach ( $cf_posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
		delete_post_meta_by_key( SCF_Config::PREFIX . 'repeat-checkboxes' );
	}

	/**
	 * admin_enqueue_scripts
	 * @param string $hook
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( in_array( $hook, array( 'post-new.php', 'post.php' ) ) ) {
			$post_type = get_post_type();
			$settings = SCF::get_settings( $post_type );

			if ( empty( $settings ) )
				return;

			wp_enqueue_style(
				SCF_Config::PREFIX . 'editor',
				plugin_dir_url( __FILE__ ) . 'css/editor.css'
			);
			wp_enqueue_media();
			wp_enqueue_script(
				SCF_Config::PREFIX . 'editor',
				plugin_dir_url( __FILE__ ) . 'js/editor.js',
				array( 'jquery' ),
				null,
				true
			);
			wp_localize_script( SCF_Config::PREFIX . 'editor', 'smart_cf_uploader', array(
				'image_uploader_title' => esc_html__( 'Image setting', 'smart-custom-fields' ),
				'file_uploader_title'  => esc_html__( 'File setting', 'smart-custom-fields' ),
			) );
			add_action( 'after_wp_tiny_mce', array( $this, 'after_wp_tiny_mce' ) );

			// relation field
			wp_enqueue_script(
				SCF_Config::PREFIX . 'editor-relation',
				plugin_dir_url( __FILE__ ) . 'js/editor-relation.js',
				array( 'jquery' ),
				null,
				true
			);
			wp_localize_script( SCF_Config::PREFIX . 'editor-relation', 'smart_cf_relation', array(
				'endpoint' => admin_url( 'admin-ajax.php' ),
				'action'   => SCF_Config::PREFIX . 'relational-posts-search',
				'nonce'    => wp_create_nonce( SCF_Config::NAME . '-relation' )
			) );
		}
	}

	public function after_wp_tiny_mce() {
		printf( '<script type="text/javascript" src="%s"></script>', plugin_dir_url( __FILE__ ) . 'js/editor-wysiwyg.js' );
	}

	/**
	 * add_meta_boxes
	 * 投稿画面にカスタムフィールドを表示
	 * @param stirng $post_type
	 * @param object $post
	 */
	public function add_meta_boxes( $post_type, $post ) {
		$cf_posts = SCF::get_settings_posts( $post_type );
		foreach ( $cf_posts as $post ) {
			setup_postdata( $post );
			$settings = get_post_meta( $post->ID, SCF_Config::PREFIX . 'setting', true );
			if ( !$settings )
				continue;
			add_meta_box(
				SCF_Config::PREFIX . 'custom-field-' . $post->ID,
				$post->post_title,
				array( $this, 'display_meta_box' ),
				$post_type,
				'normal',
				'default',
				$settings
			);
			wp_reset_postdata();
		}
	}

	/**
	 * display_meta_box
	 * @param object $post
	 * @param array $setings カスタムフィールドの設定情報
	 */
	public function display_meta_box( $post, $settings ) {
		$groups = $settings['args'];
		$tables = $this->get_tables( $post->ID, $groups );

		printf( '<div class="%s">', esc_attr( SCF_Config::PREFIX . 'meta-box' ) );
		$index = 0;
		foreach ( $tables as $group_key => $group ) {
			$btn_repeat = '';
			$is_repeat  = ( isset( $group['repeat'] ) && $group['repeat'] === true ) ? true : false;
			if ( $is_repeat ) {
				if ( $index === 0 ) {
					printf(
						'<div class="%s">',
						esc_attr( SCF_Config::PREFIX . 'meta-box-repeat-tables' )
					);
					$this->display_tr( $post->ID, $is_repeat, $group['fields'] );
				}
			}
			
			$this->display_tr( $post->ID, $is_repeat, $group['fields'], $index );

			// ループの場合は添字をカウントアップ
			// ループを抜けたらカウントをもとに戻す
			if ( $is_repeat &&
				 isset( $tables[$group_key + 1 ]['group-name'] ) &&
				 $tables[$group_key + 1 ]['group-name'] === $group['group-name'] ) {
				$index ++;
			} else {
				$index = 0;
			}
			if ( $is_repeat && $index === 0 ) {
				echo '</div>';
			}
		}
		printf( '</div>' );
		wp_nonce_field( SCF_Config::NAME . '-fields', SCF_Config::PREFIX . 'fields-nonce' );
	}

	/**
	 * get_field
	 * @param array $field フィールドの情報
	 * @param int $index インデックス番号
	 * @param mixed $value 保存されている値（check のときだけ配列）
	 */
	private function get_field( $field, $index, $value ) {
		$form_field = '';
		$name = SCF_Config::NAME . '[' . $field['name'] . '][' . $index . ']';
		switch ( $field['type'] ) {
			case 'text' :
				$form_field = $this->Fields->text( $name, array(
					'value' => $value,
				) );
				break;
			case 'check' :
				$choices = $this->Fields->get_choices( $field['choices'] );
				$form_field = $this->Fields->checkbox( $name, $choices, array(
					'value' => $value,
				) );
				break;
			case 'radio' :
				$choices = $this->Fields->get_choices( $field['choices'] );
				$form_field = $this->Fields->radio( $name, $choices, array(
					'value' => $value,
				) );
				break;
			case 'select' :
				$choices = $this->Fields->get_choices( $field['choices'] );
				$form_field = $this->Fields->select( $name, $choices, array(
					'value' => $value,
				) );
				break;
			case 'textarea' :
				$form_field = $this->Fields->textarea( $name, array(
					'value' => $value,
				) );
				break;
			case 'wysiwyg' :
				$form_field = $this->Fields->wysiwyg( $name, array(
					'value' => $value,
				) );
				break;
			case 'image' :
				$form_field = $this->Fields->image( $name, array(
					'value' => $value,
				) );
				break;
			case 'file' :
				$form_field = $this->Fields->file( $name, array(
					'value' => $value,
				) );
				break;
			case 'relation' :
				$form_field = $this->Fields->relation( $name, array(
					'value'     => $value,
					'post_type' => $field['post-type'],
				) );
		}
		return $form_field;
	}

	/**
	 * save_post
	 * @param int $post_id
	 */
	public function save_post( $post_id ) {
		if ( !isset( $_POST[SCF_Config::PREFIX . 'fields-nonce'] ) ) {
			return;
		}
		if ( !wp_verify_nonce( $_POST[SCF_Config::PREFIX . 'fields-nonce'], SCF_Config::NAME . '-fields' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ){
			return;
		}
		if ( !isset( $_POST[SCF_Config::NAME] ) ) {
			return;
		}

		// 繰り返しフィールドのチェックボックスは、普通のチェックボックスと混ざって
		// 判別できなくなるのでわかるように保存しておく
		$repeat_checkboxes = array();

		// チェックボックスが未入力のときは "" がくるので、それは保存しないように判別
		$checkbox_fields = array();

		$post_type = get_post_type();
		$settings = SCF::get_settings( $post_type );
		foreach ( $settings as $setting ) {
			foreach ( $setting as $group ) {
				$is_repeat = ( isset( $group['repeat'] ) && $group['repeat'] === true ) ? true : false;
				foreach ( $group['fields'] as $field ) {
					delete_post_meta( $post_id, $field['name'] );

					if ( in_array( $field['type'], array( 'check', 'relation' ) ) ) {
						$checkbox_fields[] = $field['name'];
					}

					if ( $is_repeat && in_array( $field['type'], array( 'check', 'relation' ) ) ) {
						$repeat_checkbox_fields = $_POST[SCF_Config::NAME][$field['name']];
						foreach ( $repeat_checkbox_fields as $values ) {
							if ( is_array( $values ) ) {
								$repeat_checkboxes[$field['name']][] = count( $values );
							} else {
								$repeat_checkboxes[$field['name']][] = 0;
							}
						}
					}
				}
			}
		}
		delete_post_meta( $post_id, SCF_Config::PREFIX . 'repeat-checkboxes' );
		if ( $repeat_checkboxes ) {
			update_post_meta( $post_id, SCF_Config::PREFIX . 'repeat-checkboxes', $repeat_checkboxes );
		}

		foreach ( $_POST[SCF_Config::NAME] as $name => $values ) {
			foreach ( $values as $value ) {
				if ( in_array( $name, $checkbox_fields ) && $value === '' )
					continue;
				if ( !is_array( $value ) ) {
					add_post_meta( $post_id, $name, $value );
				} else {
					foreach ( $value as $val ) {
						add_post_meta( $post_id, $name, $val );
					}
				}
			}
		}
	}

	/**
	 * get_post_custom
	 * @param int $post_id
	 * @return array
	 */
	protected function get_post_custom( $post_id ) {
		$post_custom = $this->post_custom;
		if ( empty( $post_custom ) ) {
			$post_custom = get_post_custom( $post_id );
			if ( empty( $post_custom ) ) {
				return array();
			}
			$this->post_custom = $post_custom;
		}
		return $this->post_custom;
	}

	/**
	 * get_repeat_checkboxes
	 * @param int $post_id
	 * @return array $this->repeat_checkboxes
	 */
	protected function get_repeat_checkboxes( $post_id ) {
		$repeat_checkboxes = $this->repeat_checkboxes;
		if ( empty( $repeat_checkboxes ) ) {
			$repeat_checkboxes = get_post_meta( $post_id, SCF_Config::PREFIX . 'repeat-checkboxes', true );
			if ( empty( $repeat_checkboxes ) ) {
				return array();
			}
			if ( is_serialized( $repeat_checkboxes ) ) {
				$repeat_checkboxes = maybe_unserialize( $repeat_checkboxes );
			}
			$this->repeat_checkboxes = $repeat_checkboxes;
		}
		return $this->repeat_checkboxes;
	}

	/**
	 * get_tables
	 * カスタムフィールドを出力するための配列を生成
	 * @param array $groups カスタムフィールド設定ページで保存した設定
	 * @return array $tables カスタムフィールド表示用のテーブルを出力するための配列
	 */
	protected function get_tables( $post_id, $groups ) {
		$post_custom = $this->get_post_custom( $post_id );
		$repeat_checkboxes = $this->get_repeat_checkboxes( $post_id );
		$tables = array();
		foreach ( $groups as $group ) {
			// ループのときは、ループの分だけグループを追加する
			// ループだけどループがないとき（新規登録時とか）は1つだけ入れる
			if ( isset( $group['repeat'] ) && $group['repeat'] === true ) {
				$loop_count = 1;
				foreach ( $group['fields'] as $field ) {
					if ( isset( $post_custom[$field['name']] ) && is_array( $post_custom[$field['name']] ) ) {
						$post_meta       = $post_custom[$field['name']];
						$post_meta_count = count( $post_meta );
						// 同名のカスタムフィールドが複数のとき（チェックボックス or ループ）
						if ( $post_meta_count > 1 ) {
							// チェックボックスの場合
							if ( is_array( $repeat_checkboxes ) && array_key_exists( $field['name'], $repeat_checkboxes ) ) {
								$checkbox_post_meta_count = count( $repeat_checkboxes[$field['name']] );
								if ( $loop_count < $checkbox_post_meta_count )
									$loop_count = $checkbox_post_meta_count;
							}
							// チェックボックス以外
							else {
								if ( $loop_count < $post_meta_count )
									$loop_count = $post_meta_count;
							}
						}
					}
				}
				if ( $loop_count >= 1 ) {
					for ( $i = $loop_count; $i > 0; $i -- ) {
						$tables[] = $group;
					}
					continue;
				}
			}
			$tables[] = $group;
		}
		return $tables;
	}

	/**
	 * get_checkbox_value
	 * @param int $post_id
	 * @param string $field_name
	 * @param int $index
	 * @return array or null
	 */
	protected function get_checkbox_value( $post_id, $field_name, $index ) {
		$post_custom = $this->get_post_custom( $post_id );
		$repeat_checkboxes = $this->get_repeat_checkboxes( $post_id );
		$value = null;
		if ( isset( $post_custom[$field_name] ) && is_array( $post_custom[$field_name] ) ) {
			$value = $post_custom[$field_name];
			// ループのとき
			if ( is_array( $repeat_checkboxes ) && array_key_exists( $field_name, $repeat_checkboxes ) ) {
				$now_num = 0;
				if ( isset( $repeat_checkboxes[$field_name][$index] ) ) {
					$now_num = $repeat_checkboxes[$field_name][$index];
				}

				// 自分（$index）より前の個数の合計が指す index が start
				$_temp = array_slice( $repeat_checkboxes[$field_name], 0, $index );
				$sum = array_sum( $_temp );
				$start = $sum;

				$value = null;
				if ( $now_num ) {
					$value = array_slice( $post_custom[$field_name], $start, $now_num );
				}
			}
		}
		return $value;
	}

	/**
	 * get_non_checkbox_value
	 * @param int $post_id
	 * @param string $field_name
	 * @param int $index
	 * @return string or null
	 */
	protected function get_non_checkbox_value( $post_id, $field_name, $index ) {
		$post_custom = $this->get_post_custom( $post_id );
		$value = null;
		if ( isset( $post_custom[$field_name][$index] ) ) {
			$value = $post_custom[$field_name][$index];
		}
		return $value;
	}

	/**
	 * display_tr
	 * @param int $post_id
	 * @param bool $is_repeat
	 * @param array $fields
	 * @param int, null $index
	 */
	protected function display_tr( $post_id, $is_repeat, $fields, $index = null ) {
		$btn_repeat = '';
		if ( $is_repeat ) {
			$btn_repeat = '<span class="button btn-add-repeat-group">+</span>';
			if ( $index > 0 || $index === null ) {
				$btn_repeat .= ' <span class="button btn-remove-repeat-group">-</span>';
			}
		}

		$style = '';
		if ( is_null( $index ) ) {
			$style = 'style="display: none;"';
		}

		printf(
			'<div class="%s" %s>%s<table>',
			esc_attr( SCF_Config::PREFIX . 'meta-box-table' ),
			$style,
			$btn_repeat
		);

		foreach ( $fields as $field ) {
			$field_label = $field['label'];
			if ( !$field_label ) {
				$field_label = $field['name'];
			}

			// チェックボックスのとき
			$post_status = get_post_status( $post_id );
			if ( in_array( $field['type'], array( 'check', 'relation' ) ) ) {
				$value = array();
				if ( !empty( $field['choices-default'] ) && ( $post_status === 'auto-draft' || is_null( $index ) ) ) {
					$value = $this->Fields->get_choices( $field['choices-default'] );
				}
				$_value = $this->get_checkbox_value( $post_id, $field['name'], $index );
			}
			// チェックボックス以外のとき
			else {
				$value = '';
				if ( $post_status === 'auto-draft' || is_null( $index ) ) {
					if ( in_array( $field['type'], array( 'textarea', 'wysiwyg' ) ) && !empty( $field['textarea-default'] ) ) {
						$value = $field['textarea-default'];
					} elseif ( !empty( $field['single-default'] ) ) {
						$value = $field['single-default'];
					}
				}
				$_value = $this->get_non_checkbox_value( $post_id, $field['name'], $index );
			}
			if ( !is_null( $_value ) ) {
				$value = $_value;
			}

			$notes = '';
			if ( !empty( $field['notes'] ) ) {
				$notes = sprintf(
					'<p class="description">%s</p>',
					esc_html( $field['notes'] )
				);
			}
			
			$form_field_index = $index;
			if ( is_null( $form_field_index ) ) {
				$form_field_index = 0;
			}
			$form_field = $this->get_field( $field, $form_field_index, $value );
			printf(
				'<tr><th>%s</th><td>%s%s</td></tr>',
				esc_html( $field_label ),
				$form_field,
				$notes
			);
		}
		echo '</table></div>';
	}

	/**
	 * relational_posts_search
	 */
	public function relational_posts_search() {
		check_ajax_referer( SCF_Config::NAME . '-relation', 'nonce' );
		$_posts = array();
		if ( isset( $_POST['post_types'], $_POST['click_count' ] ) ) {
			$post_type = explode( ',', $_POST['post_types'] );
			$posts_per_page = get_option( 'posts_per_page' );
			$offset = $_POST['click_count'] * $posts_per_page;
			$_posts = get_posts( array(
				'post_type'      => $post_type,
				'offset'         => $offset,
				'order'          => 'ASC',
				'orderby'        => 'ID',
				'posts_per_page' => $posts_per_page,
			) );
		}
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $_posts );
		die();
	}
}
new Smart_Custom_Fields();