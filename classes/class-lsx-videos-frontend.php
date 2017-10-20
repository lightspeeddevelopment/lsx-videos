<?php
/**
 * LSX Videos Frontend Class.
 *
 * @package lsx-videos
 */
class LSX_Videos_Frontend {

	/**
	 * Construct method.
	 */
	public function __construct() {
		if ( function_exists( 'tour_operator' ) ) {
			$this->options = get_option( '_lsx-to_settings', false );
		} else {
			$this->options = get_option( '_lsx_settings', false );

			if ( false === $this->options ) {
				$this->options = get_option( '_lsx_lsx-settings', false );
			}
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 999 );
		add_action( 'wp_footer', array( $this, 'add_video_modal' ) );

		add_action( 'wp_ajax_get_video_embed', array( $this, 'get_video_embed' ) );
		add_action( 'wp_ajax_nopriv_get_video_embed', array( $this, 'get_video_embed' ) );

		add_filter( 'wp_kses_allowed_html', array( $this, 'wp_kses_allowed_html' ), 10, 2 );
		add_filter( 'template_include', array( $this, 'archive_template_include' ), 99 );

		if ( is_admin() ) {
			add_filter( 'lsx_videos_colour_selectors_body', array( $this, 'customizer_body_colours_handler' ), 15, 2 );
		}

		add_filter( 'lsx_banner_title', array( $this, 'lsx_banner_archive_title' ), 15 );

		add_filter( 'excerpt_more_p', array( $this, 'change_excerpt_more' ) );
		add_filter( 'excerpt_length', array( $this, 'change_excerpt_length' ) );
		add_filter( 'excerpt_strip_tags', array( $this, 'change_excerpt_strip_tags' ) );
	}

	/**
	 * Enqueue JS and CSS.
	 */
	public function assets() {
		$has_slick = wp_script_is( 'slick', 'queue' );

		if ( ! $has_slick ) {
			wp_enqueue_style( 'slick', LSX_VIDEOS_URL . 'assets/css/vendor/slick.css', array(), LSX_VIDEOS_URL, null );
			wp_enqueue_script( 'slick', LSX_VIDEOS_URL . 'assets/js/vendor/slick.min.js', array( 'jquery' ), null, LSX_VIDEOS_URL, true );
		}

		wp_enqueue_script( 'lsx-videos', LSX_VIDEOS_URL . 'assets/js/lsx-videos.min.js', array( 'jquery', 'slick' ), LSX_VIDEOS_VER, true );

		$params = apply_filters( 'lsx_videos_js_params', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		));

		wp_localize_script( 'lsx-videos', 'lsx_videos_params', $params );

		wp_enqueue_style( 'lsx-videos', LSX_VIDEOS_URL . 'assets/css/lsx-videos.css', array( 'slick' ), LSX_VIDEOS_VER );
		wp_style_add_data( 'lsx-videos', 'rtl', 'replace' );
	}

	/**
	 * Add video modal.
	 */
	public function add_video_modal() {
		?>
		<div class="lsx-modal modal fade" id="lsx-videos-modal" role="dialog">
			<div class="modal-dialog">
				<div class="modal-content">
					<button type="button" class="close" data-dismiss="modal">&times;</button>
					<div class="modal-header">
						<h4 class="modal-title"></h4>
					</div>
					<div class="modal-body"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get video embed (ajax).
	 */
	public function get_video_embed() {
		if ( isset( $_GET['video'] ) ) {
			$video = sanitize_text_field( wp_unslash( $_GET['video'] ) );

			if ( ! empty( $video ) ) {
				echo do_shortcode( '[video width="992" height="558" src="' . $video . '"]' );
			}
		}

		wp_die();
	}

	/**
	 * Allow data params for Slick slider addon.
	 * Allow data params for Bootstrap modal.
	 */
	public function wp_kses_allowed_html( $allowedtags, $context ) {
		$allowedtags['div']['data-slick'] = true;
		$allowedtags['a']['data-toggle'] = true;
		$allowedtags['a']['data-video'] = true;
		$allowedtags['a']['data-title'] = true;
		return $allowedtags;
	}

	/**
	 * Archive template.
	 */
	public function archive_template_include( $template ) {
		if ( is_main_query() && ( is_post_type_archive( 'video' ) || is_tax( 'video-category' ) ) ) {
			if ( empty( locate_template( array( 'archive-videos.php' ) ) ) && file_exists( LSX_VIDEOS_PATH . 'templates/archive-videos.php' ) ) {
				$template = LSX_VIDEOS_PATH . 'templates/archive-videos.php';
			}
		}

		return $template;
	}

	/**
	 * Handle body colours that might be change by LSX Customiser.
	 */
	public function customizer_body_colours_handler( $css, $colors ) {
		$css .= '
			@import "' . LSX_VIDEOS_PATH . '/assets/css/scss/customizer-videos-body-colours";

			/**
			 * LSX Customizer - Body (LSX Videos)
			 */
			@include customizer-videos-body-colours (
				$bg: 		' . $colors['background_color'] . ',
				$breaker: 	' . $colors['body_line_color'] . ',
				$color:    	' . $colors['body_text_color'] . ',
				$link:    	' . $colors['body_link_color'] . ',
				$hover:    	' . $colors['body_link_hover_color'] . ',
				$small:    	' . $colors['body_text_small_color'] . '
			);
		';

		return $css;
	}

	/**
	 * Change the LSX Banners title for videos archive.
	 */
	public function lsx_banner_archive_title( $title ) {
		if ( is_main_query() && is_post_type_archive( 'video' ) ) {
			$title = '<h1 class="page-title">' . esc_html__( 'Videos', 'lsx-videos' ) . '</h1>';
		}

		if ( is_main_query() && is_tax( 'video-category' ) ) {
			$tax = get_queried_object();
			$title = '<h1 class="page-title">' . esc_html__( 'Videos Category', 'lsx-videos' ) . ': ' . apply_filters( 'the_title', $tax->name ) . '</h1>';
		}

		return $title;
	}

	/**
	 * Remove the "continue reading".
	 */
	public function change_excerpt_more( $excerpt_more ) {
		global $post;

		if ( 'video' === $post->post_type ) {
			$excerpt_more = '<p><a href="#lsx-videos-modal" data-toggle="modal" data-video="' . esc_url( $video_url ) . '" data-title="' . the_title( '', '', false ) . '" class="moretag">' . esc_html__( 'View video', 'lsx' ) . '</a></p>';
		}

		return $excerpt_more;
	}

	/**
	 * Change the word count when crop the content to excerpt.
	 */
	public function change_excerpt_length( $excerpt_word_count ) {
		global $post;

		if ( is_front_page() && 'video' === $post->post_type ) {
			$excerpt_word_count = 20;
		}

		if ( is_singular( 'video' ) ) {
			$excerpt_word_count = 20;
		}

		return $excerpt_word_count;
	}

	/**
	 * Change the allowed tags crop the content to excerpt.
	 */
	public function change_excerpt_strip_tags( $allowed_tags ) {
		global $post;

		if ( is_front_page() && 'video' === $post->post_type ) {
			$allowed_tags = '<p>,<br>,<b>,<strong>,<i>,<u>,<ul>,<ol>,<li>,<span>';
		}

		if ( is_singular( 'video' ) ) {
			$allowed_tags = '<p>,<br>,<b>,<strong>,<i>,<u>,<ul>,<ol>,<li>,<span>';
		}

		return $allowed_tags;
	}

}

global $lsx_videos_frontend;
$lsx_videos_frontend = new LSX_Videos_Frontend();
