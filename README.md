# DRM-X 5.0 LearnPress Plugin - WordPress Video DRM and Course Protection

[![DRM-X 5.0](https://img.shields.io/badge/DRM--X-5.0-155EEF)](https://www.drm-x.com/en/products/drm-x-5.0/features)
[![Plugin version](https://img.shields.io/badge/plugin-v1.0.0-2E8B57)](./drmx5-learnpress.php)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759B)](https://wordpress.org/)
[![LearnPress](https://img.shields.io/badge/LMS-LearnPress-FFB606)](https://wordpress.org/plugins/learnpress/)

**DRM-X 5.0 Integration for LearnPress** connects WordPress and LearnPress course enrollment with DRM-X digital rights management. Authorized learners can obtain licenses and play encrypted training videos through the ZJGet secure browser, while users without a valid LearnPress enrollment are denied.

This WordPress LMS DRM plugin is designed for online academies, paid courses, membership learning, professional certification, and corporate training that need video encryption, enrollment-based license delivery, dynamic watermarking, license revocation, and stronger anti-piracy protection.

> [Jump to the Chinese introduction](#中文简介).

## Why protect LearnPress videos with DRM-X?

LearnPress controls access to WordPress course pages. DRM-X 5.0 encrypts the media and requires a valid license before playback, helping protect copied video URLs and downloaded course files beyond normal page access. Depending on the DRM-X account and Rights settings, content owners can also use dynamic watermarks, device controls, license revocation, virtual-machine restrictions, and screen-recording protection.

Learn more about [DRM-X 5.0 digital content protection](https://www.drm-x.com/en/products/drm-x-5.0/features) and its [new security architecture](https://www.drm-x.com/en/products/drm-x-5.0/features/new-drm-security-architecture).

## Features

- Requires WordPress sign-in before issuing a DRM-X license.
- Accepts LearnPress course statuses `enrolled`, `finished`, and `completed` when the enrollment has not expired.
- Supports one or multiple WordPress course post IDs, for example `123-124-208`.
- Selects the first listed course for which the learner has a valid LearnPress enrollment.
- Automatically creates the matching DRM-X user when necessary.
- Blocks existing DRM-X users who are revoked or locked.
- Supports DRM-X 5.0 International (`5.drm-x.com`) and China (`5.drm-x.cn`).
- Uses the License Profile RightsID submitted through ZJGet POST data without updating DRM-X Rights.
- Supports a fixed RightsID and calls `UpdateRightWithDisableVirtualMachine` before licensing.
- Embeds protected video with `[zjget-player]URL[/zjget-player]`.
- Disables the player in WordPress editing and preview contexts.
- Includes English and Simplified Chinese interface text.

## Requirements

- WordPress 5.8 or later
- PHP 7.4 or later with the SOAP extension enabled
- LearnPress installed and activated
- HTTPS enabled on the WordPress website
- A DRM-X 5.0 account, License Profile, Rights, and user group
- ZJGet secure browser for DRM-X 5.0 protected content

## Download and documentation

- [Download DRM-X 5.0 LearnPress Plugin v1.0.0](https://github.com/Haihaisoft/drm-x5-learnpress-plugin/raw/refs/heads/main/dist/drmx5-learnpress-1.0.0.zip)
- [English PDF User Guide](https://github.com/Haihaisoft/drm-x5-learnpress-plugin/raw/refs/heads/main/docs/DRM-X-5.0-learnpress-integration-user-guide-v1.0.0.pdf)
- [中文 PDF 使用指南](https://github.com/Haihaisoft/drm-x5-learnpress-plugin/raw/refs/heads/main/docs/DRM-X-5.0-learnpress-integration-user-guide-zh-CN-v1.0.0.pdf)

## Installation

1. Install and activate LearnPress.
2. In WordPress, open **Plugins > Add New Plugin > Upload Plugin**.
3. Upload `drmx5-learnpress-1.0.0.zip` and activate it.
4. Open **Settings > DRM-X 5.0**.

For manual installation, copy the plugin to:

```text
wp-content/plugins/drmx5-learnpress/
```

## Plugin settings

| Setting | Description |
| --- | --- |
| `AdminEmail` | Administrator email address of the DRM-X 5.0 account. |
| `WebServiceAuthStr` | Web Service Authorization String configured in DRM-X 5.0. Keep it secret. |
| `GroupID` | DRM-X user group used for user creation and license requests. |
| Service area | International uses `5.drm-x.com`; China uses `5.drm-x.cn`. |
| RightsID mode | Read the License Profile RightsID from POST, or use a fixed RightsID and update it automatically. |
| Fixed RightsID | Required only when fixed mode is selected. |

### RightsID modes

**Read RightsID from ZJGet POST data:** ZJGet submits the RightsID configured through the DRM-X License Profile. The plugin uses that RightsID for license delivery and does not update the DRM-X permission.

**Fixed RightsID and automatic update:** the plugin calls `UpdateRightWithDisableVirtualMachine` before each license request. It uses the LearnPress enrollment start and course expiration times when available. If the start time is unavailable, the plugin falls back to the course publication time and then the request time. If no expiration is defined, the expiration is set to ten years after the request. `ExpirationAfterFirstUse` is `-1`.

## DRM-X License Profile setup

In **DRM-X 5.0 Account Settings > Website Integration Settings**, enter the same `WebServiceAuthStr` and set the custom integration URL to:

```text
https://your-wordpress-domain.example/wp-content/plugins/drmx5-learnpress/licstore5.php
```

The endpoint filename must remain `licstore5.php`, and the hostname must match the HTTPS hostname used for WordPress sign-in.

### LearnPress course ID

Open **LearnPress > Courses** and edit the course. If the WordPress edit URL contains:

```text
/wp-admin/post.php?post=123&action=edit
```

enter `123` in the DRM-X License Profile **Your Product ID** field. Use the numeric LearnPress course post ID, not a lesson ID, slug, product ID, or page URL.

Multiple courses use a standard hyphen:

```text
123-124-208
```

The plugin checks from left to right and uses the first course with a valid enrollment. Access to one listed course does not grant access to another course; the list applies only to the current DRM-X License Profile request.

## Embed an encrypted course video

Add a WordPress **Shortcode** block to LearnPress course or lesson content:

```text
[zjget-player]https://cdn.example.com/course/protected-video.mp4[/zjget-player]
```

- Use a complete URL beginning with `http://` or `https://`.
- Put only the raw URL inside the shortcode; do not use Markdown link syntax.
- Use only one ZJGet player on the same page.
- Player files load from `www.zjget.com` and do not need to be copied into the plugin.
- Editing and preview contexts show a placeholder instead of the player overlay.

## License workflow

1. Save the incoming DRM-X request and require WordPress sign-in.
2. Read `YourProductID` and select the first course with a valid LearnPress enrollment.
3. Create the DRM-X user when the WordPress username does not already exist there.
4. Call `CheckUserIsRevoked` and `GetUserDetails` for existing DRM-X users.
5. Apply the selected RightsID mode.
6. Request the DRM-X license and return it to ZJGet.

The same authorization check applies when a downloaded encrypted file requests a license. This plugin validates LearnPress course-level enrollment, not restrictions applied only to an individual lesson or page.

## Troubleshooting

| Problem | Check |
| --- | --- |
| License page asks the learner to sign in again | Use the same HTTPS domain for WordPress sign-in and `licstore5.php`. |
| Browser warns that submitted information is insecure | Enable HTTPS for WordPress and the DRM-X integration URL. |
| Learner is reported as unauthorized | Confirm `YourProductID` contains the numeric LearnPress course post ID and verify the enrollment status. |
| Shortcode appears instead of the player | Confirm LearnPress and this integration plugin are active and use a Shortcode block. |
| Player reports an invalid URL | Put one complete raw HTTP or HTTPS URL inside the shortcode. |
| WordPress cannot connect to DRM-X | Enable PHP SOAP and verify the credentials and service area. |

## Privacy and security

During DRM-X user creation or license delivery, the plugin may send the WordPress username, email, display name, IP address, and DRM client or device request data to the selected DRM-X service. Review applicable privacy requirements before deployment. Always use HTTPS and never publish `WebServiceAuthStr` or real account credentials.

## 中文简介

**DRM-X 5.0 LearnPress 集成插件**用于连接 WordPress、LearnPress、DRM-X 5.0 和 ZJGet 播放器。用户必须登录 WordPress，并拥有有效的 LearnPress 课程学习状态，才能获取加密视频许可证。

[查看完整中文集成使用指南（PDF）](https://github.com/Haihaisoft/drm-x5-learnpress-plugin/raw/refs/heads/main/docs/DRM-X-5.0-learnpress-integration-user-guide-zh-CN-v1.0.0.pdf)

主要功能：

- 验证 WordPress 登录和 LearnPress 课程加入状态。
- 接受尚未过期的 `enrolled`、`finished` 和 `completed` 课程状态。
- 支持 `123-124-208` 格式的多个课程 ID，并采用列表中第一个符合条件的课程。
- DRM-X 用户不存在时自动创建，被吊销或锁定时拒绝许可证。
- 支持中国版、国际版和两种 RightsID 模式。
- 使用 `[zjget-player]URL[/zjget-player]` 嵌入加密视频。

固定 RightsID 模式优先使用 LearnPress 的加入课程时间和课程截止时间。没有课程截止时间时，许可证截止日期设置为申请之日起十年，`ExpirationAfterFirstUse` 固定为 `-1`。

安装后进入 **设置 > DRM-X 5.0**，填写 `AdminEmail`、`WebServiceAuthStr`、`GroupID`、服务区域和 RightsID 模式。然后在 DRM-X 5.0 许可证模板的 **Your Product ID** 中填写 LearnPress 课程文章 ID。

## Support

- [DRM-X 5.0 product overview](https://www.drm-x.com/en)
- [DRM-X 5.0 features](https://www.drm-x.com/en/products/drm-x-5.0/features)
- [Haihaisoft](https://www.haihaisoft.com/)

Developed and maintained by [Haihaisoft](https://www.haihaisoft.com/).
