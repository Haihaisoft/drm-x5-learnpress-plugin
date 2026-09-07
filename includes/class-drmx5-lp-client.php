<?php

defined('ABSPATH') || exit;

/** Small wrapper around the DRM-X 5.0 SOAP service. */
class DRMX5_LP_Client {
    const WSDL_INTERNATIONAL = 'https://5.drm-x.com/haihaisoftlicenseservice.asmx?wsdl';
    const WSDL_CHINA = 'https://5.drm-x.cn/haihaisoftlicenseservice.asmx?wsdl';

    /** @var SoapClient */
    private $soap;
    private $admin_email;
    private $auth_string;
    private $group_id;

    public function __construct($admin_email, $auth_string, $group_id, $service_area = 'international') {
        if (!extension_loaded('soap')) {
            throw new RuntimeException(__('The PHP SOAP extension is not enabled on this WordPress server.', 'drmx5-learnpress'));
        }
        $this->admin_email = $admin_email;
        $this->auth_string = $auth_string;
        $this->group_id = $group_id;
        $wsdl = $service_area === 'china' ? self::WSDL_CHINA : self::WSDL_INTERNATIONAL;
        $this->soap = new SoapClient($wsdl, array(
            'trace' => false,
            'exceptions' => true,
            'connection_timeout' => 20,
            'cache_wsdl' => WSDL_CACHE_BOTH,
        ));
    }

    /** Create the DRM-X account when it does not exist. */
    public function ensure_user_exists(WP_User $user) {
        $response = $this->soap->__soapCall('CheckUserExists', array('parameters' => array(
            'UserName' => $user->user_login,
            'AdminEmail' => $this->admin_email,
            'WebServiceAuthStr' => $this->auth_string,
        )));
        $exists = isset($response->CheckUserExistsResult) ? trim((string) $response->CheckUserExistsResult) : '';
        if (strcasecmp($exists, 'True') === 0 || $exists === '1') {
            return true;
        }
        if (strcasecmp($exists, 'False') !== 0 && $exists !== '0') {
            throw new RuntimeException(sprintf(
                __('DRM-X could not check the user account. Service response: %s', 'drmx5-learnpress'),
                $exists
            ));
        }

        $full_name = trim($user->first_name . ' ' . $user->last_name);
        if ($full_name === '') {
            $full_name = $user->display_name ?: $user->user_login;
        }
        $response = $this->soap->__soapCall('AddNewUser', array('parameters' => array(
            'AdminEmail' => $this->admin_email,
            'WebServiceAuthStr' => $this->auth_string,
            'GroupID' => $this->group_id,
            'UserLoginName' => $user->user_login,
            'UserPassword' => 'N/A',
            'UserEmail' => $user->user_email ?: 'N/A',
            'UserFullName' => $full_name ?: 'N/A',
            'Title' => 'N/A',
            'Company' => 'N/A',
            'Address' => 'N/A',
            'City' => 'N/A',
            'Province' => 'N/A',
            'ZipCode' => 'N/A',
            'Phone' => 'N/A',
            'CompanyURL' => 'N/A',
            'SecurityQuestion' => 'N/A',
            'SecurityAnswer' => 'N/A',
            'IP' => self::remote_address(),
            'Money' => '0',
            'BindNumber' => '1',
            'IsApproved' => 'yes',
            'IsLockedOut' => 'no',
        )));
        $result = isset($response->AddNewUserResult) ? trim((string) $response->AddNewUserResult) : '';
        if ($result !== '1') {
            throw new RuntimeException(sprintf(
                __('DRM-X could not create the user account. Service response: %s', 'drmx5-learnpress'),
                $result
            ));
        }
        return false;
    }

    /** Return a localized reason when an existing DRM-X user is blocked. */
    public function get_user_block_reason($username) {
        $response = $this->soap->__soapCall('CheckUserIsRevoked', array('parameters' => array(
            'AdminEmail' => $this->admin_email,
            'WebServiceAuthStr' => $this->auth_string,
            'UserLoginName' => $username,
        )));
        if (!isset($response->CheckUserIsRevokedResult)) {
            throw new RuntimeException(__('DRM-X did not return a valid user revocation status.', 'drmx5-learnpress'));
        }
        if ($this->is_true($response->CheckUserIsRevokedResult)) {
            return __('Your account has been revoked. You cannot obtain a license. Please contact the site administrator.', 'drmx5-learnpress');
        }

        $response = $this->soap->__soapCall('GetUserDetails', array('parameters' => array(
            'AdminEmail' => $this->admin_email,
            'WebServiceAuthStr' => $this->auth_string,
            'UserLoginName' => $username,
        )));
        if (!isset($response->GetUserDetailsResult) || !is_object($response->GetUserDetailsResult) ||
            !property_exists($response->GetUserDetailsResult, 'IsLockedOut')) {
            throw new RuntimeException(__('DRM-X did not return valid user details.', 'drmx5-learnpress'));
        }
        if ($this->is_true($response->GetUserDetailsResult->IsLockedOut)) {
            return __('Your account has been locked. You cannot obtain a license. Please contact the site administrator.', 'drmx5-learnpress');
        }
        return '';
    }

    private function is_true($value) {
        if ($value === true || $value === 1) {
            return true;
        }
        return in_array(strtolower(trim((string) $value)), array('true', '1', 'yes'), true);
    }

    public function update_rights($rights_id, $watermark, $course_start, $course_end) {
        $utc = new DateTimeZone('UTC');
        $begin_date = wp_date('Y/m/d', (int) $course_start, $utc);
        $expiration_date = wp_date('Y/m/d', (int) $course_end, $utc);
        $response = $this->soap->__soapCall('UpdateRightWithDisableVirtualMachine', array('parameters' => array(
            'AdminEmail' => $this->admin_email,
            'WebServiceAuthStr' => $this->auth_string,
            'RightsID' => $rights_id,
            'Description' => 'WordPress LearnPress DRM-X 5.0',
            'PlayCount' => '-1',
            'BeginDate' => $begin_date,
            'ExpirationDate' => $expiration_date,
            'ExpirationAfterFirstUse' => '-1',
            'RightsPrice' => '0',
            'AllowPrint' => 'False',
            'AllowClipBoard' => 'False',
            'AllowDoc' => 'False',
            'EnableWatermark' => 'True',
            'WatermarkText' => $watermark,
            'WatermarkArea' => '1,2,3,4,5,',
            'RandomChangeArea' => 'True',
            'RandomFrquency' => '12',
            'EnableBlacklist' => 'True',
            'EnableWhitelist' => 'True',
            'ExpireTimeUnit' => 'Day',
            'PreviewTime' => 3,
            'PreviewTimeUnit' => 'Day',
            'PreviewPage' => 3,
            'DisableVirtualMachine' => 'True',
            'require_admin_permission' => 'False',
            'bind_1st_screen' => 'False',
            'enable_wm_3rd' => 1,
            'wm_3rd_random_change_angle' => 1,
            'wm_3rd_textalpha' => 90,
            'wm_3rd_rows' => 4,
            'wm_3rd_columns' => 3,
            'wm_3rd_fixed_angle' => 0,
            'wm_3rd_textsize' => 14,
            'wm_3rd_textcolor' => 16744576,
            'wm_3rd_angle_change_interval' => 10,
            'wm_3rd_text' => $watermark,
            'enable_wm_2nd' => 0,
            'wm_2nd_text' => $watermark,
            'wm_2nd_speed' => 1,
            'wm_2nd_textsize' => 22,
            'wm_2nd_textcolor' => 16744576,
            'wm_2nd_textalpha' => 90,
        )));
        $result = isset($response->UpdateRightWithDisableVirtualMachineResult)
            ? trim((string) $response->UpdateRightWithDisableVirtualMachineResult) : '';
        if ($result !== '1') {
            throw new RuntimeException(sprintf(
                __('DRM-X could not update the permission. Service response: %s', 'drmx5-learnpress'),
                $result
            ));
        }
    }

    public function get_license(DRMX5_LP_Request $request, WP_User $user, $rights_id) {
        $full_name = trim($user->first_name . ' ' . $user->last_name);
        if ($full_name === '') {
            $full_name = $user->display_name ?: $user->user_login;
        }
        $response = $this->soap->__soapCall('getLicenseRemoteToTableWithVersionWithMac', array('parameters' => array(
            'AdminEmail' => $this->admin_email,
            'WebServiceAuthStr' => $this->auth_string,
            'ProfileID' => $request->get('profileid'),
            'ClientInfo' => $request->get('clientinfo'),
            'RightsID' => $rights_id,
            'UserLoginName' => $user->user_login,
            'UserFullName' => $full_name,
            'GroupID' => $this->group_id,
            'Message' => 'N/A',
            'IP' => self::remote_address(),
            'Platform' => $request->get('platform'),
            'ContentType' => $request->get('contenttype'),
            'Version' => $request->get('version'),
            'Mac' => $request->get('mac'),
        )));
        return array(
            isset($response->getLicenseRemoteToTableWithVersionWithMacResult)
                ? (string) $response->getLicenseRemoteToTableWithVersionWithMacResult : '',
            isset($response->Message) ? (string) $response->Message : '',
        );
    }

    private static function remote_address() {
        return isset($_SERVER['REMOTE_ADDR']) ? substr(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])), 0, 45) : 'N/A';
    }
}

