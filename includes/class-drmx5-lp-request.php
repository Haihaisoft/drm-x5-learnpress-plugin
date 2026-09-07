<?php

defined('ABSPATH') || exit;

/** Captures and validates values posted by DRM-X/ZJGet. */
class DRMX5_LP_Request {
    /** @var array */
    private $values;

    /** @var bool */
    private $rightsid_from_post;

    private function __construct(array $values, $rightsid_from_post) {
        $this->values = $values;
        $this->rightsid_from_post = (bool) $rightsid_from_post;
    }

    public static function is_new_request() {
        return isset($_POST['profileid']) || isset($_GET['profileid']);
    }

    public static function capture() {
        $source = array_merge(wp_unslash($_GET), wp_unslash($_POST));
        $fields = array(
            'profileid' => 255,
            'clientinfo' => 8192,
            'rightsid' => 255,
            'yourproductid' => 1024,
            'platform' => 255,
            'contenttype' => 255,
            'version' => 255,
            'return_url' => 2048,
            'mac' => 1024,
        );
        $values = array();
        foreach ($fields as $field => $maximum) {
            $value = isset($source[$field]) && is_scalar($source[$field]) ? (string) $source[$field] : '';
            // ClientInfo is an opaque DRM value and must not be altered by HTML sanitizers.
            $values[$field] = substr(trim(str_replace("\0", '', $value)), 0, $maximum);
        }
        return new self($values, isset($_POST['rightsid']));
    }

    public static function restore(array $data) {
        if (empty($data['values']) || !is_array($data['values'])) {
            return null;
        }
        return new self($data['values'], !empty($data['rightsid_from_post']));
    }

    public function export() {
        return array(
            'values' => $this->values,
            'rightsid_from_post' => $this->rightsid_from_post,
        );
    }

    public function get($name) {
        return isset($this->values[$name]) ? $this->values[$name] : '';
    }

    public function validate($rights_mode) {
        foreach (array('profileid', 'clientinfo', 'yourproductid') as $required) {
            if ($this->get($required) === '') {
                return __('The DRM-X request is missing a required parameter.', 'drmx5-learnpress');
            }
        }
        if ($rights_mode === 'post' && (!$this->rightsid_from_post || $this->get('rightsid') === '')) {
            return __('ZJGet did not POST a RightsID. Check the permission mode and DRM-X License Profile.', 'drmx5-learnpress');
        }
        if (preg_match('~^(?:javascript|data|vbscript):~i', $this->get('return_url'))) {
            return __('The DRM-X return URL is invalid.', 'drmx5-learnpress');
        }
        return '';
    }
}
