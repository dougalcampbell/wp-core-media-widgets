<?php
/**
 * Widget API: WP_Widget_Video class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 4.8.0
 */

/**
 * Core class that implements a video widget.
 *
 * @since 4.8.0
 *
 * @todo Refactor this for latest WP_Widget_Media and remove codeCoverageIgnore
 * @codeCoverageIgnore
 * @see WP_Widget
 */
class WP_Widget_Video extends WP_Widget_Media {

	/**
	 * Constructor.
	 *
	 * @since  4.8.0
	 * @access public
	 */
	public function __construct() {
		parent::__construct( 'media_video', __( 'Video' ), array(
			'description' => __( 'Displays a video file.' ),
			'mime_type'   => 'video',
		) );

		$this->l10n = array_merge( $this->l10n, array(
			'no_media_selected' => __( 'No video selected' ),
			'select_media' => _x( 'Select Video', 'label for button in the video widget; should not be longer than ~13 characters long' ),
			'change_media' => _x( 'Change Video', 'label for button in the video widget; should not be longer than ~13 characters long' ),
			'edit_media' => _x( 'Edit Video', 'label for button in the video widget; should not be longer than ~13 characters long' ),
			'missing_attachment' => sprintf(
				/* translators: placeholder is URL to media library */
				__( 'We can&#8217;t find that video. Check your <a href="%s">media library</a> and make sure it wasn&#8217;t deleted.' ),
				esc_url( admin_url( 'upload.php' ) )
			),
			/* translators: %d is widget count */
			'media_library_state_multi' => _n_noop( 'Video Widget (%d)', 'Video Widget (%d)' ),
			'media_library_state_single' => __( 'Video Widget' ),
		) );
	}

	/**
	 * Get instance schema.
	 *
	 * This is protected because it may become part of WP_Widget eventually.
	 *
	 * @link https://core.trac.wordpress.org/ticket/35574
	 * @return array
	 */
	protected function get_instance_schema() {
		return array_merge(
			parent::get_instance_schema(),
			array(
				'autoplay' => array(
					'type' => 'boolean',
					'default' => false,
				),
				'caption' => array(
					'type' => 'string',
					'default' => '',
					'sanitize_callback' => 'wp_kses_post',
				),
				'preload' => array(
					'type' => 'string',
					'enum' => array( 'none', 'auto', 'metadata' ),
					'default' => 'none',
				),
				'loop' => array(
					'type' => 'boolean',
					'default' => false,
				),
			)
		);
	}

	/**
	 * Render the media on the frontend.
	 *
	 * @since  4.8.0
	 * @access public
	 *
	 * @param array $instance Widget instance props.
	 *
	 * @return void
	 */
	public function render_media( $instance ) {

		// @todo Support external video defined by 'url' only.
		if ( empty( $instance['attachment_id'] ) ) {
			return;
		}

		$attachment = get_post( $instance['attachment_id'] );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		// TODO: height and width
		echo wp_video_shortcode( array(
			'src' => wp_get_attachment_url( $attachment->ID ),
			'loop' => $instance['loop'],
			'autoplay' => $instance['autoplay'],
			'preload' => $instance['preload'],
		) );
	}

	/**
	 * Loads the required media files for the media manager and scripts for .
	 *
	 * @since 4.8.0
	 * @access public
	 */
	public function enqueue_admin_scripts() {
		parent::enqueue_admin_scripts();

		$handle = 'media-video-widget';
		wp_enqueue_script( $handle );

		$exported_schema = array();
		foreach ( $this->get_instance_schema() as $field => $field_schema ) {
			$exported_schema[ $field ] = wp_array_slice_assoc( $field_schema, array( 'type', 'default', 'enum', 'minimum', 'format' ) );
		}
		wp_add_inline_script(
			$handle,
			sprintf(
				'wp.mediaWidgets.modelConstructors[ %s ].prototype.schema = %s;',
				wp_json_encode( $this->id_base ),
				wp_json_encode( $exported_schema )
			)
		);

		wp_add_inline_script(
			$handle,
			sprintf(
				'
					wp.mediaWidgets.controlConstructors[ %1$s ].prototype.mime_type = %2$s;
					_.extend( wp.mediaWidgets.controlConstructors[ %1$s ].prototype.l10n, %3$s );
				',
				wp_json_encode( $this->id_base ),
				wp_json_encode( $this->widget_options['mime_type'] ),
				wp_json_encode( $this->l10n )
			)
		);
	}

	/**
	 * Render form template scripts.
	 *
	 * @since 4.8.0
	 * @access public
	 */
	public function render_control_template_scripts() {
		parent::render_control_template_scripts()
		?>
		<script type="text/html" id="tmpl-wp-media-widget-video-preview">
			<# if ( data.attachment.error && 'missing_attachment' === data.attachment.error ) { #>
				<div class="notice notice-error notice-alt notice-missing-attachment">
					<p><?php echo $this->l10n['missing_attachment']; ?></p>
				</div>
			<# } else if ( data.attachment.error ) { #>
				<div class="notice notice-error notice-alt">
					<p><?php _e( 'Unable to preview media due to an unknown error.' ); ?></p>
				</div>
			<# } else { #>
				<iframe class="media-widget-video-preview"></iframe>
			<# } #>
		</script>
		<?php
	}
}
