<?php
/**
 * Plugin Name: Twenty7 Degrees North Imgix Plugin
 * Description: Renders images via the Imgix CDN.
 * Author: David M. Coleman
 * Author URI: https://davidmc.io/
 * Version: 3.0.0
 */

/**
 * Requirements
 */
require ( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );
use Imgix\UrlBuilder; // Imgix API

// Disable scaled images
add_filter( 'big_image_size_threshold', '__return_false' );

// Check WordPress environment
if ( wp_get_environment_type() === 'local' ) {

	class Imgix {
		private $localOrigin;
		private $remoteOrigin;
		private $localSignKey;
		private $remoteSignKey;

		// If running locally, get avatar image with default functionality.
		public function get_avatar( $user_ID, $source = array(), $classes = array(), $alt = '', $scale = 2 ) {
			return get_avatar( $user_ID, $source[0]['w'], '', $alt, array( 'class' => implode( ' ', $classes ) ) );
		}

		// If running locally, get image with default functionality.
		public function get_image( $image_ID, $source = array(), $classes = array(), $alt = '', $scale = 2 ) {
			return wp_get_attachment_image( $image_ID, array( $source[0]['w'], $source[0]['h'] ), false, array( 'class' => implode( ' ', $classes ), 'alt' => $alt ) );
		}

		/**
		 * Remote images
		 */
		// Get image from remote source | uses responsive images with support for both WEBP and AVIF
		public function get_remote_image( $image_URL, $source = array(), $classes = array(), $alt = '', $scale = 2 ) {
			$alt = ( ! empty( $alt ) ? ' alt="' . $alt . '"' : '' );
			$html  = '<picture>';
			foreach ( $source as $media => $params ) {
				$params['fm'] = 'avif';
				$media = ( ! is_int( $media ) ? ' media="' . $media . '"' : '' );
				$html .= '<source type="image/avif"' . $media . ' srcset="' . implode( ', ', $this->get_remote_srcset( $image_URL, $params, $scale ) ) . '" />';
			}
			foreach ( $source as $media => $params ) {
				$params['fm'] = 'webp';
				$media = ( ! is_int( $media ) ? ' media="' . $media . '"' : '' );
				$html .= '<source type="image/webp"' . $media . ' srcset="' . implode( ', ', $this->get_remote_srcset( $image_URL, $params, $scale ) ) . '" />';
			}
			$html .= '<img class="' . implode( ' ', $classes ) . '" src="' . $this->get_remote_src( $image_URL, array_values($source)[0] ) . '"' . $alt . ' width="' . array_values($source)[0]['w'] . '" height="' . array_values($source)[0]['h'] . '" />';
			$html .= '</picture>';
			return $html;
		}
		
		// Get remote image source and convert to CDN
		public function get_remote_src( $image_URL, $params = array() ) {
			$builder = new UrlBuilder( $this->remoteOrigin );
			$builder->setSignKey( $this->remoteSignKey );
			$builder->setIncludeLibraryParam( false );
			return $builder->createURL( $image_URL, $params );
		}

		// Get remote image source-set and convert to CDN
		public function get_remote_srcset( $image_URL, $params = array(), $scale = 2 ) {
			$builder = new UrlBuilder( $this->remoteOrigin );
			$builder->setSignKey( $this->remoteSignKey );
			$builder->setIncludeLibraryParam( false );
			$srcset = array();
			$scales = range( 1, $scale, 1 );
			foreach ( $scales as $scale ) {
				$params['dpr'] = $scale;
				$srcset[] = $builder->createURL( $image_URL, $params ) . " {$scale}x";
			}
			return $srcset;
		}

		public function __construct() {
			$this->localOrigin = ( defined('IMGIX_DOMAIN_LOCAL') ? IMGIX_DOMAIN_LOCAL : NULL );
			$this->remoteOrigin = ( defined('IMGIX_DOMAIN_REMOTE') ? IMGIX_DOMAIN_REMOTE : NULL );
			$this->localSignKey = ( defined('IMGIX_SIGN_KEY_LOCAL') ? IMGIX_SIGN_KEY_LOCAL : NULL );
			$this->remoteSignKey = ( defined('IMGIX_SIGN_KEY_REMOTE') ? IMGIX_SIGN_KEY_REMOTE : NULL );
		}

	}

} else {

	class Imgix {
		private $localOrigin;
		private $remoteOrigin;
		private $localSignKey;
		private $remoteSignKey;

		/**
		 * Normal images
		 */
		// Get image from attachment ID | uses responsive images with support for both WEBP and AVIF
		public function get_image( $image_ID, $source = array(), $classes = array(), $alt = '', $scale = 2 ) {
			$alt = ( ! empty( $alt ) ? ' alt="' . $alt . '"' : '' );
			$html  = '<picture>';
			foreach ( $source as $media => $params ) {
				$params['fm'] = 'avif';
				$media = ( ! is_int( $media ) ? ' media="' . $media . '"' : '' );
				$html .= '<source type="image/avif"' . $media . ' srcset="' . implode( ', ', $this->get_srcset( $image_ID, $params, $scale ) ) . '" />';
			}
			foreach ( $source as $media => $params ) {
				$params['fm'] = 'webp';
				$media = ( ! is_int( $media ) ? ' media="' . $media . '"' : '' );
				$html .= '<source type="image/webp"' . $media . ' srcset="' . implode( ', ', $this->get_srcset( $image_ID, $params, $scale ) ) . '" />';
			}
			$html .= '<img class="' . implode( ' ', $classes ) . '" src="' . $this->get_src( $image_ID, array_values($source)[0] ) . '"' . $alt . ' width="' . array_values($source)[0]['w'] . '" height="' . array_values($source)[0]['h'] . '" />';
			$html .= '</picture>';
			return $html;
		}
		
		// Get image source from attachment ID
		public function get_src( $image_ID, $params = array(), $noParams = false ) {
			$builder = new UrlBuilder( $this->localOrigin );
			$builder->setSignKey( $this->localSignKey );
			$builder->setIncludeLibraryParam( false );
			if ( $noParams === false ) {
				$focalPoint = ( get_post_meta( $image_ID, 'acf_wpo_sweet_spot', true ) ? get_post_meta( $image_ID, 'acf_wpo_sweet_spot', true ) : array( 'x' => 50, 'y' => 50 ) );
                $params['fp-x'] = ( intval($focalPoint['x']) / 100.0 );
                $params['fp-y'] = ( intval($focalPoint['y']) / 100.0 );
                $params['crop'] = 'focalpoint';
				if ( get_field( 'image_trim', $image_ID ) )
					$params['trim'] = 'auto';
		
			} else {
				$params = array();
			}
		
			if ( get_field( 'show_watermark', $image_ID ) )
				$params['mark'] = 'text_tall.png';
		
			return $builder->createURL( str_replace( array( site_url( '/wp-content/uploads/' ), home_url( '/wp-content/uploads/' ) ), '', wp_get_attachment_image_url( $image_ID, 'full' ) ), $params );
		}
		
		// Get image source-set from attachment ID
		public function get_srcset( $image_ID, $params = array(), $scale = 2 ) {
			$builder = new UrlBuilder( $this->localOrigin );
			$builder->setSignKey( $this->localSignKey );
			$builder->setIncludeLibraryParam( false );
			$srcset = array();
			$scales = range( 1, $scale, 1 );
            $focalPoint = ( get_post_meta( $image_ID, 'acf_wpo_sweet_spot', true ) ? get_post_meta( $image_ID, 'acf_wpo_sweet_spot', true ) : array( 'x' => 50, 'y' => 50 ) );
            $params['fp-x'] = ( intval($focalPoint['x']) / 100.0 );
            $params['fp-y'] = ( intval($focalPoint['y']) / 100.0 );
            $params['crop'] = 'focalpoint';
			if ( get_field( 'image_trim', $image_ID ) )
				$params['trim'] = 'auto';
		
			if ( get_field( 'show_watermark', $image_ID ) )
				$params['mark'] = 'text_tall.png';
		
			foreach ( $scales as $scale ) {
				$params['dpr'] = $scale;
				$srcset[] = $builder->createURL( str_replace( array( site_url( '/wp-content/uploads/' ), home_url( '/wp-content/uploads/' ) ), '', wp_get_attachment_image_url( $image_ID, 'full' ) ), $params ) . " {$scale}x";
			}
			return $srcset;
		}
		
		/**
		 * Avatar images
		 */
		// Get avatar image from user ID | uses responsive images with support for both WEBP and AVIF
		public function get_avatar( $user_ID, $source = array(), $classes = array(), $alt = '', $scale = 2 ) {
			$classes[] = 'avatar';
			$alt = ( ! empty( $alt ) ? ' alt="' . $alt . '"' : '' );
			$html  = '<picture>';
			foreach ( $source as $media => $params ) {
				$params['fm'] = 'avif';
				$media = ( ! is_int( $media ) ? ' media="' . $media . '"' : '' );
				$html .= '<source type="image/avif"' . $media . ' srcset="' . implode( ', ', $this->get_avatar_srcset( $user_ID, $params, $scale ) ) . '" />';
			}
			foreach ( $source as $media => $params ) {
				$params['fm'] = 'webp';
				$media = ( ! is_int( $media ) ? ' media="' . $media . '"' : '' );
				$html .= '<source type="image/webp"' . $media . ' srcset="' . implode( ', ', $this->get_avatar_srcset( $user_ID, $params, $scale ) ) . '" />';
			}
			$html .= '<img class="' . implode( ' ', $classes ) . '" src="' . $this->get_avatar_src( $user_ID, array_values($source)[0] ) . '"' . $alt . ' width="' . array_values($source)[0]['w'] . '" height="' . array_values($source)[0]['h'] . '" />';
			$html .= '</picture>';
			return $html;
		}
		
		// Get avatar image source from user ID	
		public function get_avatar_src( $user_ID, $params = array(), $noParams = false ) {
			$builder = new UrlBuilder( $this->localOrigin );
			$builder->setSignKey( $this->localSignKey );
			$builder->setIncludeLibraryParam( false );
			return $builder->createURL( str_replace( array( site_url( '/wp-content/uploads/' ), home_url( '/wp-content/uploads/' ) ), '', get_user_meta( $user_ID, 'simple_local_avatar', true )['full'] ), $params );
		}
		
		// Get avatar image source-set from user ID	
		public function get_avatar_srcset( $user_ID, $params = array(), $scale = 2 ) {
			$builder = new UrlBuilder( $this->localOrigin );
			$builder->setSignKey( $this->localSignKey );
			$builder->setIncludeLibraryParam( false );
			$srcset = array();
			$scales = range( 1, $scale, 1 );
			foreach ( $scales as $scale ) {
				$params['dpr'] = $scale;
				$srcset[] = $builder->createURL( str_replace( array( site_url( '/wp-content/uploads/' ), home_url( '/wp-content/uploads/' ) ), '', get_user_meta( $user_ID, 'simple_local_avatar', true )['full'] ), $params ) . " {$scale}x";
			}
			return $srcset;
		}

		/**
		 * Remote images
		 */
		// Get image from remote source | uses responsive images with support for both WEBP and AVIF
		public function get_remote_image( $image_URL, $source = array(), $classes = array(), $alt = '', $scale = 2 ) {
			$alt = ( ! empty( $alt ) ? ' alt="' . $alt . '"' : '' );
			$html  = '<picture>';
			foreach ( $source as $media => $params ) {
				$params['fm'] = 'avif';
				$media = ( ! is_int( $media ) ? ' media="' . $media . '"' : '' );
				$html .= '<source type="image/avif"' . $media . ' srcset="' . implode( ', ', $this->get_remote_srcset( $image_URL, $params, $scale ) ) . '" />';
			}
			foreach ( $source as $media => $params ) {
				$params['fm'] = 'webp';
				$media = ( ! is_int( $media ) ? ' media="' . $media . '"' : '' );
				$html .= '<source type="image/webp"' . $media . ' srcset="' . implode( ', ', $this->get_remote_srcset( $image_URL, $params, $scale ) ) . '" />';
			}
			$html .= '<img class="' . implode( ' ', $classes ) . '" src="' . $this->get_remote_src( $image_URL, array_values($source)[0] ) . '"' . $alt . ' width="' . array_values($source)[0]['w'] . '" height="' . array_values($source)[0]['h'] . '" />';
			$html .= '</picture>';
			return $html;
		}
		
		// Get remote image source and convert to CDN
		public function get_remote_src( $image_URL, $params = array() ) {
			$builder = new UrlBuilder( $this->remoteOrigin );
			$builder->setSignKey( $this->remoteSignKey );
			$builder->setIncludeLibraryParam( false );
			return $builder->createURL( $image_URL, $params );
		}

		// Get remote image source-set and convert to CDN
		public function get_remote_srcset( $image_URL, $params = array(), $scale = 2 ) {
			$builder = new UrlBuilder( $this->remoteOrigin );
			$builder->setSignKey( $this->remoteSignKey );
			$builder->setIncludeLibraryParam( false );
			$srcset = array();
			$scales = range( 1, $scale, 1 );
			foreach ( $scales as $scale ) {
				$params['dpr'] = $scale;
				$srcset[] = $builder->createURL( $image_URL, $params ) . " {$scale}x";
			}
			return $srcset;
		}

		public function __construct() {
			$this->localOrigin = ( defined('IMGIX_DOMAIN_LOCAL') ? IMGIX_DOMAIN_LOCAL : NULL );
			$this->remoteOrigin = ( defined('IMGIX_DOMAIN_REMOTE') ? IMGIX_DOMAIN_REMOTE : NULL );
			$this->localSignKey = ( defined('IMGIX_SIGN_KEY_LOCAL') ? IMGIX_SIGN_KEY_LOCAL : NULL );
			$this->remoteSignKey = ( defined('IMGIX_SIGN_KEY_REMOTE') ? IMGIX_SIGN_KEY_REMOTE : NULL );
		}

	}

}