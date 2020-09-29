<?php

namespace SeriouslySimplePodcasting\Controllers;

use SeriouslySimplePodcasting\Renderers\Renderer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Players_Controller class
 *
 * @author      Danilo Radovic
 * @category    Class
 * @package     SeriouslySimplePodcasting/Controllers
 * @since       2.3
 */
class Players_Controller extends Controller {

	public $renderer = null;
	public $episode_controller;

	public function __construct( $file, $version ) {
		parent::__construct( $file, $version );
		$this->renderer           = new Renderer();
		$this->episode_controller = new Episode_Controller( $file, $version );
		$this->init();
	}

	public function init() {
		/**
		 * Only register shortcodes once the init hook is triggered
		 */
		add_action( 'init', array( $this, 'register_shortcodes' ), 1 );
		/**
		 * Only load player assets once the wp_enqueue_scripts hook is triggered
		 * @todo ideally only when the player is loaded...
		 */
		add_action( 'wp_enqueue_scripts', array( $this, 'load_player_assets' ) );
	}

	public function load_player_assets() {
		wp_register_style( 'html5-player-v2', $this->assets_url . 'css/html5-player-v2.css', array(), $this->version );
		wp_enqueue_style( 'html5-player-v2' );
		wp_register_script( 'html5-player-v2', $this->assets_url . 'js/html5-player-v2.js', array( 'jquery' ), $this->version, true );
		wp_enqueue_script( 'html5-player-v2' );
	}


	public function register_shortcodes() {
		add_shortcode('elementor_html_player', array($this, 'elementor_html_player'));
		add_shortcode('elementor_subscribe_links', array($this, 'elementor_subscribe_links'));
	}

	public function elementor_html_player($attributes) {
		$templateData = $this->html_player($attributes['id']);

		return $this->renderer->render($templateData, 'players/html-player');
	}

	public function elementor_subscribe_links($attributes) {
		$templateData = $this->get_subscribe_links( $attributes['id'] );

		return $this->renderer->render( $templateData, 'players/subscribe-links' );
	}

	/**
	 * Return feed url.
	 *
	 * @return string
	 */
	public function get_feed_url() {
		// Get feed slug
		$feed_slug = apply_filters( 'ssp_feed_slug', $this->token );

		if ( get_option( 'permalink_structure' ) ) {
			$feed_url = $this->home_url . 'feed/' . $feed_slug;
		} else {
			$feed_url = $this->home_url . '?feed=' . $feed_slug;
		}

		$custom_feed_url = get_option( 'ss_podcasting_feed_url' );
		if ( $custom_feed_url ) {
			$feed_url = $custom_feed_url;
		}

		$feed_url = apply_filters( 'ssp_feed_url', $feed_url );

		return $feed_url;
	}

	/**
	 * Return html player for a given podcast (episode) id.
	 *
	 * @param int $id
	 *
	 * @return string
	 */
	public function html_player( $id ) {
		$episode         = get_post( $id );
		$episodeDuration = get_post_meta( $id, 'duration', true );
		$audioFile       = get_post_meta( $id, 'audio_file', true );
		$albumArt        = $this->episode_controller->get_album_art( $id );
		$podcastTitle    = get_option( 'ss_podcasting_data_title' );

		$subscribeLinks = $this->get_subscribe_links( $id );

		$feedUrl = $this->get_feed_url();
		// set any other info
		$templateData = array(
			'episode'      => $episode,
			'duration'     => $episodeDuration,
			'audioFile'    => $audioFile,
			'albumArt'     => $albumArt,
			'podcastTitle' => $podcastTitle,
			'feedUrl'      => $feedUrl,
			'itunes'       => $subscribeLinks['itunes'],
			'stitcher'     => $subscribeLinks['stitcher'],
			'spotify'      => $subscribeLinks['spotify'],
			'googlePlay'   => $subscribeLinks['googlePlay']
		);

		$templateData = apply_filters( 'ssp_html_player_data', $templateData );

		return $templateData;
	}

	/**
	 * Return media player for a given podcast (episode) id.
	 *
	 * @param int $id
	 *
	 * @return string
	 */
	public function media_player( $id ) {
		// get src file
		$srcFile = get_post_meta( $id, 'audio_file', true );
		$params  = array(
			'src'     => $srcFile,
			'preload' => 'none'
		);

		$mediaPlayer = wp_audio_shortcode( $params );

		return $mediaPlayer;
	}

	public function get_subscribe_links( $id ) {

		$seriesId = $this->get_series_id( $id );

		if ( $seriesId ) {
			$itunes     = get_option( "ss_podcasting_itunes_url_{$seriesId}" );
			$stitcher   = get_option( "ss_podcasting_stitcher_url_{$seriesId}" );
			$spotify    = get_option( "ss_podcasting_spotify_url_{$seriesId}" );
			$googlePlay = get_option( "ss_podcasting_google_play_url_{$seriesId}" );
		} else {
			$itunes     = get_option( "ss_podcasting_itunes_url" );
			$stitcher   = get_option( "ss_podcasting_stitcher_url" );
			$spotify    = get_option( "ss_podcasting_spotify_url" );
			$googlePlay = get_option( "ss_podcasting_google_play_url" );
		}

		$subscribeLinks = array(
			'itunes'     => ['title' => 'iTunes', 'link' => $itunes],
			'stitcher'   => ['title' => 'Stitcher', 'link' => $stitcher],
			'spotify'    => ['title' => 'Spotify', 'link' => $spotify],
			'googlePlay' => ['title' => 'GooglePlay', 'link' => $googlePlay]
		);

		return $subscribeLinks;
	}

	public function subscribe_links( $id ) {
		$templateData = $this->get_subscribe_links( $id );

		$templateData = apply_filters('ssp_subscribe_links_data', $templateData);

		return $this->renderer->render($templateData, 'players/subscribe-links.php');
	}

	public function get_series_id( $episode_id ) {
		$series_id = 0;
		$series    = get_the_terms( $episode_id, 'series' );

		if ( $series ) {
			$series_id = ( ! empty( $series ) && isset( $series[0] ) ) ? $series[0]->term_id : 0;
		}

		return $series_id;
	}

}
