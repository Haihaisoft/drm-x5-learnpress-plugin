<?php
/**
 * Plugin Name: DRM-X 5.0 Integration for LearnPress
 * Plugin URI: https://www.drm-x.com/
 * Description: Integrates DRM-X 5.0 protected content and the ZJGet player with WordPress and LearnPress.
 * Version: 1.0.0
 * Author: Haihaisoft
 * Author URI: https://www.haihaisoft.com/
 * Text Domain: drmx5-learnpress
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: learnpress
 * License: GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

define('DRMX5_LP_VERSION', '1.0.0');
define('DRMX5_LP_FILE', __FILE__);
define('DRMX5_LP_DIR', plugin_dir_path(__FILE__));
define('DRMX5_LP_URL', plugin_dir_url(__FILE__));

require_once DRMX5_LP_DIR . 'includes/class-drmx5-lp-request.php';
require_once DRMX5_LP_DIR . 'includes/class-drmx5-lp-course-authorizer.php';
require_once DRMX5_LP_DIR . 'includes/class-drmx5-lp-client.php';
require_once DRMX5_LP_DIR . 'includes/class-drmx5-lp-license-controller.php';
require_once DRMX5_LP_DIR . 'includes/class-drmx5-lp-settings.php';

/** Load translations after WordPress has selected the current locale. */
function drmx5_lp_load_textdomain() {
    load_plugin_textdomain('drmx5-learnpress', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'drmx5_lp_load_textdomain', 1);

/** Register the ZJGet player shortcode. */
function drmx5_lp_register_shortcode() {
    add_shortcode('zjget-player', 'drmx5_lp_player_shortcode');
}
add_action('init', 'drmx5_lp_register_shortcode');

/**
 * Render [zjget-player]https://example.com/video.mp4[/zjget-player].
 *
 * @param array|string $atts Shortcode attributes.
 * @param string|null  $content Shortcode content.
 * @return string
 */
function drmx5_lp_player_shortcode($atts = array(), $content = null) {
    unset($atts);
    $content = html_entity_decode((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (!preg_match('~https?://[^\s<>"\']+~iu', $content, $matches)) {
        return '<span class="drmx5-lp-error">' .
            esc_html__('The ZJGet player URL must start with http:// or https://.', 'drmx5-learnpress') .
            '</span>';
    }

    $url = esc_url_raw($matches[0], array('http', 'https'));
    if (!$url) {
        return '<span class="drmx5-lp-error">' .
            esc_html__('The ZJGet player URL must start with http:// or https://.', 'drmx5-learnpress') .
            '</span>';
    }

    $editing = is_admin() || is_customize_preview() ||
        (defined('REST_REQUEST') && REST_REQUEST) ||
        isset($_GET['elementor-preview']) || isset($_GET['fl_builder']);
    $editing = (bool) apply_filters('drmx5_learnpress_disable_player', $editing);

    if ($editing) {
        return '<div class="drmx5-lp-editing-placeholder" style="padding:12px;border:1px solid #ccd0d4;background:#f6f7f7">' .
            '<strong>' . esc_html__('ZJGet protected video (player disabled while editing)', 'drmx5-learnpress') . '</strong>' .
            '<br><small>' . esc_html($url) . '</small></div>';
    }

    static $rendered = false;
    if ($rendered) {
        return '<span class="drmx5-lp-error">' .
            esc_html__('Only one ZJGet player can be displayed on the same page.', 'drmx5-learnpress') .
            '</span>';
    }
    $rendered = true;

    wp_enqueue_script(
        'drmx5-lp-embed-zjget',
        'https://www.zjget.com/assets/embed_js/embed_zjget.js',
        array(),
        null,
        true
    );
    wp_enqueue_script(
        'drmx5-lp-videojs',
        'https://www.zjget.com/assets/videojs-8.23.3/video.min.js',
        array('drmx5-lp-embed-zjget'),
        null,
        true
    );
    wp_enqueue_script(
        'drmx5-lp-zjget',
        'https://www.zjget.com/assets/embed_js/zjget.js',
        array('drmx5-lp-videojs'),
        null,
        true
    );

    return '<div id="ZJGet_Video_URL" style="display:none">' . esc_html($url) . '</div>';
}

/** Show a dependency notice without breaking the WordPress admin area. */
function drmx5_lp_dependency_notice() {
    if (!current_user_can('activate_plugins') || function_exists('learn_press_get_user')) {
        return;
    }
    echo '<div class="notice notice-error"><p>' .
        esc_html__('DRM-X 5.0 Integration requires LearnPress to be installed and activated.', 'drmx5-learnpress') .
        '</p></div>';
}
add_action('admin_notices', 'drmx5_lp_dependency_notice');

DRMX5_LP_Settings::init();

