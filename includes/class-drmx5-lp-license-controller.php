<?php

defined('ABSPATH') || exit;

/** Coordinates login, LearnPress authorization and DRM-X license delivery. */
class DRMX5_LP_License_Controller {
    public static function handle() {
        nocache_headers();
        $settings = wp_parse_args(get_option('drmx5_lp_settings', array()), DRMX5_LP_Settings::defaults());
        $rights_mode = $settings['rights_mode'] === 'fixed' ? 'fixed' : 'post';
        $resume_token = isset($_GET['drmx5_resume']) ? sanitize_key(wp_unslash($_GET['drmx5_resume'])) : '';

        if (DRMX5_LP_Request::is_new_request()) {
            $request = DRMX5_LP_Request::capture();
        } elseif ($resume_token !== '') {
            $saved = get_transient('drmx5_lp_request_' . $resume_token);
            $request = is_array($saved) ? DRMX5_LP_Request::restore($saved) : null;
        } else {
            $request = null;
        }

        if (!$request) {
            self::error_page(__('No pending DRM-X license request was found. Please open the protected content again.', 'drmx5-learnpress'));
        }

        if (!is_user_logged_in()) {
            // The token is placed in a URL and later normalized with sanitize_key().
            // UUID hexadecimal characters remain unchanged during that round trip.
            $token = str_replace('-', '', wp_generate_uuid4());
            set_transient('drmx5_lp_request_' . $token, $request->export(), 10 * MINUTE_IN_SECONDS);
            $resume_url = add_query_arg('drmx5_resume', rawurlencode($token), self::endpoint_url());
            wp_safe_redirect(wp_login_url($resume_url));
            exit;
        }

        $validation_error = $request->validate($rights_mode);
        if ($validation_error !== '') {
            self::error_page($validation_error);
        }
        if (empty($settings['admin_email']) || empty($settings['webservice_auth']) || empty($settings['group_id'])) {
            self::error_page(__('DRM-X AdminEmail, WebServiceAuthStr or GroupID has not been configured by the site administrator.', 'drmx5-learnpress'));
        }
        if (!function_exists('learn_press_get_user')) {
            self::error_page(__('LearnPress is not installed or activated.', 'drmx5-learnpress'));
        }

        $user = wp_get_current_user();
        $course = DRMX5_LP_Course_Authorizer::get_accessible_course($request->get('yourproductid'), $user->ID);
        if (!$course) {
            self::error_page(__('Your account is not actively enrolled in any course assigned to this protected content.', 'drmx5-learnpress'));
        }

        if ($rights_mode === 'fixed') {
            $rights_id = trim((string) $settings['fixed_rights_id']);
            if ($rights_id === '') {
                self::error_page(__('Fixed RightsID mode is enabled, but no Fixed RightsID has been configured.', 'drmx5-learnpress'));
            }
            if (empty($course['start']) || empty($course['end']) || $course['end'] < $course['start']) {
                self::error_page(__('The matched LearnPress course dates are invalid and cannot be used to update the DRM-X permission.', 'drmx5-learnpress'));
            }
        } else {
            $rights_id = $request->get('rightsid');
        }

        try {
            $client = new DRMX5_LP_Client(
                $settings['admin_email'],
                $settings['webservice_auth'],
                $settings['group_id'],
                $settings['service_area']
            );
            $already_exists = $client->ensure_user_exists($user);
            if ($already_exists) {
                $block_reason = $client->get_user_block_reason($user->user_login);
                if ($block_reason !== '') {
                    self::error_page($block_reason);
                }
            }

            if ($rights_mode === 'fixed') {
                $watermark = $user->user_email ?: $user->user_login;
                $client->update_rights($rights_id, $watermark, $course['start'], $course['end']);
            }
            list($license, $message) = $client->get_license($request, $user, $rights_id);
        } catch (Throwable $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('DRM-X 5.0 LearnPress: ' . $exception->getMessage());
            }
            self::error_page(__('The DRM-X service request failed. Please contact the site administrator.', 'drmx5-learnpress'));
        }

        if (stripos($license, 'license_div_drm-x5') === false) {
            $details = trim($message . ' ' . wp_strip_all_tags($license));
            self::error_page(sprintf(__('DRM-X rejected the license request: %s', 'drmx5-learnpress'), $details));
        }

        if ($resume_token !== '') {
            delete_transient('drmx5_lp_request_' . $resume_token);
        }
        self::success_page($license, $message, $request->get('return_url'));
    }

    public static function endpoint_url() {
        return plugins_url('licstore5.php', DRMX5_LP_FILE);
    }

    private static function error_page($message) {
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        $title = __('Unable to obtain DRM-X license', 'drmx5-learnpress');
        echo '<!doctype html><html><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>' . self::styles() . '</head><body>';
        echo '<main class="drmx5-card"><h1>' . esc_html($title) . '</h1><p>' . esc_html($message) . '</p></main>';
        echo '</body></html>';
        exit;
    }

    private static function success_page($license, $message, $return_url) {
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        $return_json = wp_json_encode($return_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<!doctype html><html><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>DRM-X 5.0</title>' . self::styles() . '</head><body>';
        echo $license; // Trusted license HTML returned by the configured DRM-X service.
        echo '<main class="drmx5-card" id="drmx5-content"><p>' . esc_html($message) . '</p>';
        echo '<button type="button" id="drmx5-open">' . esc_html__('Open protected content', 'drmx5-learnpress') . '</button></main>';
        echo '<script>(function(){var u=' . $return_json . ';var b=document.getElementById("drmx5-open");';
        echo 'b.addEventListener("click",function(){if(u){window.location.href=u;}});';
        echo 'if(u==="ios_x"){document.getElementById("drmx5-content").style.display="none";}else if(u){b.click();}}());</script>';
        echo '</body></html>';
        exit;
    }

    private static function styles() {
        return '<style>body{font-family:Arial,sans-serif;background:#f5f7fa;margin:0;padding:32px}' .
            '.drmx5-card{max-width:620px;margin:10vh auto;background:#fff;padding:32px;border-radius:12px;' .
            'box-shadow:0 4px 20px rgba(0,0,0,.08);text-align:center}.drmx5-card h1{font-size:22px;color:#c62828}' .
            '.drmx5-card p{line-height:1.6;color:#333}.drmx5-card button{border:0;border-radius:6px;background:#1677ff;' .
            'color:#fff;padding:12px 28px;cursor:pointer}</style>';
    }
}
