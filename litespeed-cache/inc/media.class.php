<?php

/**
 * The class to operate media data.
 *
 * @since      1.4
 * @package    LiteSpeed_Cache
 * @subpackage LiteSpeed_Cache/includes
 * @author     LiteSpeed Technologies <info@litespeedtech.com>
 */

class LiteSpeed_Cache_Media
{
	private static $_instance ;

	const LAZY_LIB = '/min/lazyload.js' ;

	private $content ;

	/**
	 * Init
	 *
	 * @since  1.4
	 * @access private
	 */
	private function __construct()
	{
		LiteSpeed_Cache_Log::debug2( 'Media init' ) ;

		$this->_static_request_check() ;
	}

	/**
	 * Check if the request is for static file
	 *
	 * @since  1.4
	 * @access private
	 * @return  string The static file content
	 */
	private function _static_request_check()
	{
		// This request is for js/css_async.js
		if ( strpos( $_SERVER[ 'REQUEST_URI' ], self::LAZY_LIB ) !== false ) {
			LiteSpeed_Cache_Log::debug( 'Media run lazyload lib' ) ;

			LiteSpeed_Cache_Control::set_cacheable() ;
			LiteSpeed_Cache_Control::set_no_vary() ;
			LiteSpeed_Cache_Control::set_custom_ttl( 8640000 ) ;
			LiteSpeed_Cache_Tag::add( LiteSpeed_Cache_Tag::TYPE_MIN . '_LAZY' ) ;

			$file = LSWCP_DIR . 'js/lazyload.js' ;

			header( 'Content-Length: ' . filesize( $file ) ) ;
			header( 'Content-Type: application/x-javascript; charset=utf-8' ) ;

			echo file_get_contents( $file ) ;
			exit ;
		}
	}

	/**
	 * Run lazy load process
	 * NOTE: As this is after cache finalized, can NOT set any cache control anymore
	 *
	 * Only do for main page. Do NOT do for esi or dynamic content.
	 *
	 * @since  1.4
	 * @access public
	 * @return  string The buffer
	 */
	public static function run( $content )
	{
		if ( ! defined( 'LITESPEED_IS_HTML' ) ) {
			LiteSpeed_Cache_Log::debug2( 'Media bypass: Not frontend HTML type' ) ;
			return $content ;
		}

		LiteSpeed_Cache_Log::debug( 'Media start' ) ;

		$instance = self::get_instance() ;
		$instance->content = $content ;

		$instance->_proceed() ;
		return $instance->content ;
	}

	/**
	 * Run lazyload replacement for images in buffer
	 *
	 * @since  1.4
	 * @access private
	 */
	private function _proceed()
	{
		if ( ! LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_MEDIA_IMG_LAZY ) ) {
			return ;
		}

		$lazy_lib_url = LiteSpeed_Cache_Utility::get_permalink_url( self::LAZY_LIB ) ;

		$this->content = str_replace( '</head>', '<script>window.lazyLoadOptions={threshold:50};</script></head>', $this->content ) ;
		$this->content = str_replace( '</body>', '<script src="' . $lazy_lib_url . '"></script></body>', $this->content ) ;

		$html_list = $this->_parse_img() ;
		$html_list_ori = $html_list ;

		foreach ( $html_list as $k => $v ) {
			$snippet = '<noscript>' . $v . '</noscript>' ;
			$v = str_replace( array( ' src=', ' srcset=' ), array( ' data-src=', ' data-srcset=' ), $v ) ;
			$v = str_replace( '<img ', '<img data-lazyloaded="1" src="' . LITESPEED_PLACEHOLDER . '" ', $v ) ;
			$snippet = $v . $snippet ;

			$html_list[ $k ] = $snippet ;
		}

		$this->content = str_replace( $html_list_ori, $html_list, $this->content ) ;
	}

	/**
	 * Parse img src
	 *
	 * @since  1.4
	 * @access private
	 * @return array  All the src & related raw html list
	 */
	private function _parse_img()
	{
		$html_list = array() ;

		$content = preg_replace( '#<!--.*-->#sU', '', $this->content ) ;
		preg_match_all( '#<img \s*([^>]+)/?>#isU', $content, $matches, PREG_SET_ORDER ) ;
		foreach ( $matches as $match ) {
			$attrs = LiteSpeed_Cache_Utility::parse_attr( $match[ 1 ] ) ;

			if ( empty( $attrs[ 'src' ] ) ) {
				continue ;
			}

			LiteSpeed_Cache_Log::debug2( 'Media found: ' . $attrs[ 'src' ] ) ;

			if ( ! empty( $attrs[ 'data-no-lazy' ] ) || ! empty( $attrs[ 'data-lazyloaded' ] ) || ! empty( $attrs[ 'data-src' ] ) || ! empty( $attrs[ 'data-srcset' ] ) ) {
				LiteSpeed_Cache_Log::debug2( 'Media bypassed' ) ;
				continue ;
			}

			// to avoid multiple replacement
			if ( in_array( $match[ 0 ], $html_list ) ) {
				continue ;
			}

			$html_list[] = $match[ 0 ] ;
		}

		return $html_list ;
	}

	/**
	 * Get the current instance object.
	 *
	 * @since 1.4
	 * @access public
	 * @return Current class instance.
	 */
	public static function get_instance()
	{
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self() ;
		}

		return self::$_instance ;
	}

}