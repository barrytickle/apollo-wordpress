<?php
/**
 * @package WordPress
 * @subpackage Timberland
 * @since Timberland 2.1.0
 */

use Twig\TwigFunction;
// use BarryTimberHelpers; // Commented out as it is not defined

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/theme/src/custom-functions.php';
use BarryTimberHelpers\BarryTimberHelpers;

BarryTimberHelpers::init();

// use function BarryTimberHelpers\has_class_name;

Timber\Timber::init();
Timber::$dirname    = array( 'views', 'blocks' );
Timber::$autoescape = false;


class Timberland extends Timber\Site {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
		add_filter( 'timber/context', array( $this, 'add_to_context' ) );
		add_filter( 'timber/twig', array( $this, 'add_to_twig' ) );
		add_filter( 'timber/twig', array( $this, 'add_twig_functions' ) );
		add_action( 'block_categories_all', array( $this, 'block_categories_all' ) );
		add_action( 'acf/init', array( $this, 'acf_register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
		add_action( 'acf/init', array( $this, 'register_acf_options_page' ) );


		add_filter( 'nav_menu_css_class', array( $this, 'add_custom_menu_item_class' ), 10, 4 );
		parent::__construct();
	} 


	public function check_url_match ($string){
		$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		if($_SERVER['REQUEST_URI'] === $string  || $url === $string) {
			return true;
		}
		return false;
	}

	public function add_twig_functions( $twig ) {
		$twig->addFunction( new TwigFunction( 'check_url_match', array( $this, 'check_url_match' ) ) );
		return $twig;
	}


	public function add_to_context( $context ) {
		global $post;
		$context['processed_content'] = wrap_non_acf_blocks($post->post_content);
		$context['site'] = $this;
		$menus = wp_get_nav_menus();
		$context['menus'] = [];
		

		$context['page'] = [];
		$context['page']['title'] = get_the_title();
		$context['page']['description'] = get_the_excerpt();
		$context['page']['url'] = get_permalink();
		$context['page']['image'] = array(
			'url'   => get_the_post_thumbnail_url($post->ID, 'full'),
			'sizes' => array(
				'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
				'medium'    => get_the_post_thumbnail_url($post->ID, 'medium'),
				'large'     => get_the_post_thumbnail_url($post->ID, 'large'),
				'full'      => get_the_post_thumbnail_url($post->ID, 'full'),
			),
			'alt'   => get_post_meta(get_post_thumbnail_id($post->ID), '_wp_attachment_image_alt', true),
		);
		$context['page']['type'] = 'website';
		$context['page']['site_name'] = get_bloginfo('name');
		$context['page']['locale'] = get_locale();
		$context['page']['author'] = get_the_author_meta('display_name', $post->post_author);
		$context['page']['date'] = get_the_date('c', $post->ID);
		$context['page']['modified'] = get_the_modified_date('c', $post->ID);
		$context['page']['canonical'] = get_permalink($post->ID);
		$context['page']['og'] = [];
		$context['page']['og']['title'] = get_the_title();

		$context['all_posts'] = Timber::get_posts(array(
			'posts_per_page' => -1
		));
		$context['pathname'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$context['acf_fields'] = get_fields($post->ID);

		$context['options'] = get_fields('options');
		foreach ($menus as $menu) {
			$context['menus'][$menu->slug] = Timber::get_menu($menu->term_id);
		}

		$context['header_cta'] = [];
		$header_menu = Timber::get_menu('header');
		if ($header_menu && !empty($header_menu->items)) {
			$context['header_cta'] = end($header_menu->items);
		} 

		// Require block functions files
		foreach ( glob( __DIR__ . '/blocks/*/functions.php' ) as $file ) {
			require_once $file;
		}

		return $context;
	}

	public function wrap_non_acf_blocks($content) {
		$pattern = '/<!-- wp:(?!acf\/)[\s\S]*?-->([\s\S]*?)<!-- \/wp:[\s\S]*?-->/';
		$replacement = '<div class="custom-container">$0</div>';
		return preg_replace($pattern, $replacement, $content);
	}

	public function add_to_twig( $twig ) {
		return $twig;
	}

	public function theme_supports() {
		add_theme_support( 'automatic-feed-links' );
		add_theme_support(
			'html5',
			array(
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
			)
		);
		add_theme_support( 'menus' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'editor-styles' );
	}

	public function register_acf_options_page() {
		if ( function_exists( 'acf_add_options_page' ) ) {
			acf_add_options_page( array(
				'page_title' => __( 'Site Settings', 'text-domain' ),
				'menu_title' => __( 'Site Settings', 'text-domain' ),
				'menu_slug'  => 'site-settings',
				'capability' => 'edit_posts',
				'redirect'   => false,
			) );
		}
	}


	public function enqueue_assets() {
		// Prevent dequeueing of critical scripts in admin
		if (is_admin()) {
			return;
		}
	
		wp_dequeue_style('wp-block-library');
		wp_dequeue_style('wp-block-library-theme');
		wp_dequeue_style('wc-block-style');
		wp_dequeue_script('jquery');
		wp_dequeue_style('global-styles');

		$vite_env = 'production';

		if ( file_exists( get_template_directory() . '/../config.json' ) ) {
			$config   = json_decode( file_get_contents( get_template_directory() . '/../config.json' ), true );
			$vite_env = $config['vite']['environment'] ?? 'production';
		}

		$dist_uri  = get_template_directory_uri() . '/assets/dist';
		$dist_path = get_template_directory() . '/assets/dist';
		$manifest  = null;

		if ( file_exists( $dist_path . '/.vite/manifest.json' ) ) {
			$manifest = json_decode( file_get_contents( $dist_path . '/.vite/manifest.json' ), true );
		}

		if ( is_array( $manifest ) ) {
			if ( $vite_env === 'production' || is_admin() ) {
				$js_file = 'theme/assets/main.js';
				wp_enqueue_style( 'main', $dist_uri . '/' . $manifest[ $js_file ]['css'][0] );
				$strategy = is_admin() ? 'async' : 'defer';
				$in_footer = is_admin() ? false : true;
				wp_enqueue_script(
					'main',
					$dist_uri . '/' . $manifest[ $js_file ]['file'],
					array(),
					'',
					array(
						'strategy'  => $strategy,
						'in_footer' => $in_footer,
					)
				);

				// wp_enqueue_style('prefix-editor-font', '//fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap');
				$editor_css_file = 'theme/assets/styles/editor-style.css';
				add_editor_style( $dist_uri . '/' . $manifest[ $editor_css_file ]['file'] );
			}
		}

		
		add_action('admin_enqueue_scripts', 'mytheme_enqueue_admin_media');
		

		if ( $vite_env === 'development' ) {
			function vite_head_module_hook() {
				echo '<script type="module" crossorigin src="http://localhost:3000/@vite/client"></script>';
				echo '<script type="module" crossorigin src="http://localhost:3000/theme/assets/main.js"></script>';
			}
			add_action( 'wp_head', 'vite_head_module_hook' );
		}
	}


	public function add_custom_menu_item_class( $classes, $item, $args, $depth ) {
		if ( isset( $item->classes ) && is_array( $item->classes ) ) {
			$custom_class = get_field( 'custom_class', $item );
			if ( $custom_class ) {
				$classes[] = $custom_class;
			}
		}
		return $classes;
	}

	public function block_categories_all( $categories ) {
		return array_merge(
			array(
				array(
					'slug'  => 'custom',
					'title' => __( 'Custom' ),
				),
			),
			$categories
		);
	}

	public function acf_register_blocks() {
		$blocks = array();

		foreach ( new DirectoryIterator( __DIR__ . '/blocks' ) as $dir ) {
			if ( $dir->isDot() ) {
				continue;
			}

			if ( file_exists( $dir->getPathname() . '/block.json' ) ) {
				$blocks[] = $dir->getPathname();
			}
		}

		asort( $blocks );

		foreach ( $blocks as $block ) {
			register_block_type( $block );
		}
	}
}

new Timberland();

/**
 * Don't edit this one
 */
function acf_block_render_callback( $block, $content ) {
	$context           = Timber::context();
	$context['post']   = Timber::get_post();
	$context['block']  = $block;
	$context['fields']  = get_fields();
    $block_name        = explode( '/', $block['name'] )[1];
    $template          = 'blocks/'. $block_name . '/index.twig';

	Timber::render( $template, $context );
}

// Remove ACF block wrapper div
function acf_should_wrap_innerblocks( $wrap, $name ) {
	return false;
}

add_filter( 'acf/blocks/wrap_frontend_innerblocks', 'acf_should_wrap_innerblocks', 10, 2 );



function custom_wp_title($title, $sep) {
	$path_parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
	$page_title = ucwords(str_replace('-', ' ', end($path_parts)));

	if (is_home()) {
		$title = $page_title ? $page_title . ' | ' . get_bloginfo('name') : get_bloginfo('name');;
	} elseif (is_singular('post')) {
		$title = get_the_title() . " $sep | Apollo Promotions | Looking to hire fresh new talent";
	}
	return $title;
}

add_filter('wp_title', 'custom_wp_title', 10, 2);
