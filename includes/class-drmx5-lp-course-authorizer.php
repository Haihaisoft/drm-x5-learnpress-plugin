<?php

defined('ABSPATH') || exit;

/** Resolves YourProductID to LearnPress courses and verifies enrolment. */
class DRMX5_LP_Course_Authorizer {
    /**
     * Return the first listed LearnPress course the user may access.
     *
     * @param string $product_ids Hyphen-separated WordPress course post IDs.
     * @param int    $user_id WordPress user ID.
     * @return array|false Course information, or false.
     */
    public static function get_accessible_course($product_ids, $user_id) {
        if (!function_exists('learn_press_get_user')) {
            return false;
        }

        $lp_user = learn_press_get_user((int) $user_id);
        if (!$lp_user || !method_exists($lp_user, 'get_course_data')) {
            return false;
        }

        $tokens = preg_split('/\s*-\s*/', trim((string) $product_ids), -1, PREG_SPLIT_NO_EMPTY);
        foreach (array_unique($tokens) as $token) {
            if (!ctype_digit($token) || (int) $token <= 0) {
                continue;
            }
            $course_id = (int) $token;
            if (get_post_type($course_id) !== 'lp_course') {
                continue;
            }

            try {
                $course_data = $lp_user->get_course_data($course_id);
            } catch (Throwable $exception) {
                continue;
            }
            if (!$course_data || !method_exists($course_data, 'get_status')) {
                continue;
            }

            $status = strtolower((string) $course_data->get_status());
            $allowed_statuses = array('enrolled', 'finished', 'completed');
            if (!in_array($status, $allowed_statuses, true)) {
                continue;
            }

            $start = 0;
            if (method_exists($course_data, 'get_start_time')) {
                try {
                    $start = self::timestamp_from_value($course_data->get_start_time());
                } catch (Throwable $exception) {
                    $start = 0;
                }
            }
            if (!$start) {
                $start = (int) get_post_time('U', true, $course_id);
            }
            if (!$start) {
                $start = time();
            }

            $expiration = 0;
            if (method_exists($course_data, 'get_expiration_time')) {
                try {
                    $expiration = self::timestamp_from_value($course_data->get_expiration_time());
                } catch (Throwable $exception) {
                    $expiration = 0;
                }
            }
            if ($expiration && $expiration < time()) {
                continue;
            }

            return array(
                'id' => $course_id,
                'start' => $start,
                'end' => $expiration ?: strtotime('+10 years'),
            );
        }
        return false;
    }

    /** Convert LearnPress date objects and strings to a Unix timestamp. */
    private static function timestamp_from_value($value) {
        if (is_object($value) && method_exists($value, 'getTimestamp')) {
            return (int) $value->getTimestamp();
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value) && $value !== '') {
            $timestamp = strtotime($value);
            return $timestamp ? (int) $timestamp : 0;
        }
        return 0;
    }
}
