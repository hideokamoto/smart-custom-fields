<?php
class Smart_Custom_Fields_Test extends WP_UnitTestCase {

	/**
	 * @var array
	 */
	protected $post_ids;

	/**
	 * setUp
	 */
	public function setUp() {
		parent::setUp();
		$this->post_ids = $this->factory->post->create_many( 5, array(
			'post_type' => SCF_Config::NAME,
		) );
		foreach ( $this->post_ids as $post_id ) {
			update_post_meta( $post_id, SCF_Config::PREFIX . 'repeat-multiple-data', 'dummy' );
		}
		for ( $i = 1; $i <= 5; $i ++ ) {
			// 本来の名前とは異なるので注意
			update_option( SCF_Config::PREFIX . $i, 'dummy' );
		}
		SCF::clear_all_cache();
	}

	/**
	 * tearDown
	 */
	public function tearDown() {
		parent::tearDown();
		SCF::clear_all_cache();
	}

	/**
	 * @group uninstall
	 */
	public function test_uninstall__実行したら投稿タイプSmart_Custom_Fieldsの投稿は削除() {
		Smart_Custom_Fields::uninstall();
		$posts = get_posts( array(
			'post_type'      => SCF_Config::NAME,
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );
		$this->assertEquals( 0, count( $posts ) );
	}

	/**
	 * @group uninstall
	 */
	public function test_uninstall__実行したらrepeat_multiple_dataは削除() {
		Smart_Custom_Fields::uninstall();

		global $wpdb;
		$var = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT count( * ) FROM $wpdb->postmeta
					WHERE meta_key = %s
				",
				SCF_Config::PREFIX . 'repeat-multiple-data'
			)
		);
		$this->assertEquals( 0, $var );
	}

	/**
	 * @group uninstall
	 */
	public function test_uninstall__実行したらoptionは削除() {
		Smart_Custom_Fields::uninstall();

		global $wpdb;
		$var = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT count( * ) FROM $wpdb->options
					WHERE option_name LIKE %s
				",
				SCF_Config::PREFIX . '%'
			)
		);
		$this->assertEquals( 0, $var );
	}
}
