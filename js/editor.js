/**
 * editor.js
 * Version    : 1.0.0
 * Author     : Takashi Kitajima
 * Created    : September 23, 2014
 * Modified   :
 * License    : GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
jQuery( function( $ ) {

	$( '.smart-cf-meta-box' ).each( function( i, e ) {
		var wrapper = $( e );
		var btn_add_repeat_group    = wrapper.find( '.btn-add-repeat-group' );
		var btn_remove_repeat_group = wrapper.find( '.btn-remove-repeat-group' );
		var table_class             = '.smart-cf-meta-box-table';
		var cnt                     = wrapper.find( table_class ).length;

		/**
		 * ロード時に wysiwyg エディター用のテキストエリアがあったら wysiwyg 化する。
		 */
		wrapper.find( '.smart-cf-wp-editor' ).each( function( i, e ) {
			if ( $( this ).parents( table_class ).css( 'display' ) !== 'none' ) {
				$( this ).attr( 'id', 'smart-cf-wysiwyg-' + cnt + i );
				var editor_id = $( this ).attr( 'id' );
				$( this ).parents( '.wp-editor-wrap' ).find( 'a.add_media' ).attr( 'data-editor', editor_id );
				tinymce.execCommand( 'mceAddEditor', false, editor_id );
			}
		} );

		/**
		 * グループ追加ボタン
		 */
		btn_add_repeat_group.click( function( e ) {
			cnt ++;
			var parent = $( this ).parents( '.smart-cf-meta-box-repeat-tables' );
			var table = parent.find( table_class ).first();
			var clone = table.clone( true, true ).hide();

			clone.find( 'input, select, textarea' ).each( function( i, e ) {
				$( this ).attr( 'name',
					$( this ).attr( 'name' ).replace(
						/^(smart-custom-fields\[.+\])\[\d+\]/,
						'$1[' + cnt + ']'
					)
				);
			} );

			clone.find( '.smart-cf-wp-editor' ).each( function( i, e ) {
				$( this ).attr( 'id', 'smart-cf-wysiwyg-' + cnt + i );
			} );

			$( this ).parent().after( clone.fadeIn( 'fast' ) );
			$( this ).trigger( 'smart-cf-after-add-group' );
		} );

		/**
		 * グループ削除ボタン
		 */
		btn_remove_repeat_group.click( function() {
			var table = $( this ).parents( table_class );
			table.fadeOut( 'fast', function() {
				$( this ).remove();
			} );
		} );

		/**
		 * 画像アップローダー
		 */
		wrapper.find( '.btn-add-image' ).click( function( e ) {
			e.preventDefault();
			var custom_uploader_image;
			var upload_button = $( this );
			if ( custom_uploader_image ) {
				custom_uploader_image.open();
				return;
			}
			custom_uploader_image = wp.media( {
				title  : smart_cf_uploader.image_uploader_title,
				library: {
					type: 'image'
				},
				button : {
					text: smart_cf_uploader.image_uploader_title
				},
				multiple: false
			} );

			custom_uploader_image.on( 'select', function() {
				var images = custom_uploader_image.state().get( 'selection' );
				images.each( function( file ){
					var image_area = upload_button.parent().find( '.smart-cf-upload-image' );
					image_area.find( 'img' ).remove();
					image_area.prepend(
						'<img src="' + file.toJSON().url + '" />'
					);
					image_area.removeClass( 'hide' );
					upload_button.parent().find( 'input[type="hidden"]' ).val( file.toJSON().id );
				} );
			} );

			custom_uploader_image.open();
		} );

		/**
		 * 画像削除ボタン
		 */
		wrapper.find( '.smart-cf-upload-image' ).hover( function() {
			$( this ).find( '.btn-remove-image' ).fadeIn( 'fast', function() {
				$( this ).removeClass( 'hide' );
			} );
		}, function() {
			$( this ).find( '.btn-remove-image' ).fadeOut( 'fast', function() {
				$( this ).addClass( 'hide' );
			} );
		} );
		wrapper.find( '.btn-remove-image' ).click( function() {
			$( this ).parent().find( 'img' ).remove();
			$( this ).parent().siblings( 'input[type="hidden"]' ).val( '' );
			$( this ).parent().addClass( 'hide' );
		} );

		/**
		 * ファイルアップローダー
		 */
		wrapper.find( '.btn-add-file' ).click( function( e ) {
			e.preventDefault();
			var custom_uploader_file;
			var upload_button = $( this );
			if ( custom_uploader_file ) {
				custom_uploader_file.open();
				return;
			}
			custom_uploader_file = wp.media( {
				title : smart_cf_uploader.file_uploader_title,
				button: {
					text: smart_cf_uploader.file_uploader_title
				},
				multiple: false
			} );

			custom_uploader_file.on( 'select', function() {
				var images = custom_uploader_file.state().get( 'selection' );
				images.each( function( file ){
					var image_area = upload_button.parent().find( '.smart-cf-upload-file' );
					image_area.find( 'img' ).remove();
					image_area.prepend(
						'<a href="' + file.toJSON().url + '" target="_blank"><img src="' + file.toJSON().icon + '" /></a>'
					);
					image_area.removeClass( 'hide' );
					upload_button.parent().find( 'input[type="hidden"]' ).val( file.toJSON().id );
				} );
			} );

			custom_uploader_file.open();
		} );

		/**
		 * ファイル削除ボタン
		 */
		wrapper.find( '.smart-cf-upload-file' ).hover( function() {
			$( this ).find( '.btn-remove-file' ).fadeIn( 'fast', function() {
				$( this ).removeClass( 'hide' );
			} );
		}, function() {
			$( this ).find( '.btn-remove-file' ).fadeOut( 'fast', function() {
				$( this ).addClass( 'hide' );
			} );
		} );
		wrapper.find( '.btn-remove-file' ).click( function() {
			$( this ).parent().find( 'img' ).remove();
			$( this ).parent().siblings( 'input[type="hidden"]' ).val( '' );
			$( this ).parent().addClass( 'hide' );
		} );

		/**
		 * sortable
		 */
		wrapper.find( '.smart-cf-meta-box-repeat-tables' ).sortable( {
			cursor: 'move',
			items : '> .smart-cf-meta-box-table:not( :first-child )',
			start : function( e, ui ) {
				$( this ).trigger( 'smart-cf-repeat-table-sortable-start', ui.item );
			},
			stop  : function( e, ui ) {
				$( this ).trigger( 'smart-cf-repeat-table-sortable-stop', ui.item );
			},
		} );

	} );
} );