<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Support\Arr;

$wpsr_app   = App::getInstance();

$wpsr_data_params = '';
$wpsr_popup_delay = '';
$wpsr_display_popup = '';

$wpsr_channel_name = array_column($settings['channels'], 'name');

$wpsr_classes                            = array();
$wpsr_classes['has_pro']                 = defined('WPSOCIALREVIEWS_PRO') ? 'wpsr_pro_active' : '';
$wpsr_classes['chat_single']             = sizeof($settings['channels']) > 1 ? 'wpsr_has_multiple_chat_channel' : '';
$wpsr_classes['btn-position']            = $settings['settings']['chat_bubble_position'] ? 'wpsr-fm-bubble-position-' . $settings['settings']['chat_bubble_position'] : '';
$wpsr_classes['template']                = $settings['template'] ? 'wpsr-fm-chat-' . $settings['template'] : '';
$wpsr_classes['layout']                  = $settings['layout_type'] === 'icons' ? 'wpsr-chat-icons-layout' : '';
$wpsr_classes['fuent_forms']             = in_array('fluent_forms', $wpsr_channel_name) ? 'wpsr-has-fluent-forms-widget' : '';
$wpsr_classes['ff_modal']                = sizeof($settings['channels']) === 1 && strpos($settings['channels'][0]['credential'], 'fluentform_modal') ? 'wpsr_has_ff_modal' : '';

if (isset($settings['settings']['day_time_schedule']) && $settings['settings']['day_time_schedule'] === 'true') {
    $wpsr_data_params = apply_filters('wpsocialreviews/display_user_online_status', $settings['settings']);
}

if(Arr::get($settings, 'settings.display_greeting') === 'true'){
    $wpsr_display_popup = Arr::get($settings, 'settings.display_greeting');
    $wpsr_display_popup = 'data-chat-display-popup='.$wpsr_display_popup.'';
    $wpsr_popup_delay = Arr::get($settings, 'settings.popup_delay');
    $wpsr_popup_delay = 'data-chat-popup-delay='.$wpsr_popup_delay.'';
}

$wpsr_popup_target = Arr::get($settings, 'settings.popup_target', 'false');
$wpsr_popup_target_data   = 'data-popup-target='.$wpsr_popup_target.'';
$wpsr_chats_params_data   = !empty($wpsr_data_params) && is_array($wpsr_data_params) ? ' data-chats-params="' . htmlspecialchars(json_encode($wpsr_data_params), ENT_QUOTES, 'UTF-8') . '"' : '';
?>
<div data-chats-side="front"
     id="wpsr-chat-widget-<?php echo esc_attr($template_id); ?>"
     class="wpsr-fm-chat-wrapper <?php echo esc_attr(implode(' ', $wpsr_classes)); ?>"
     style="--wpsn-chat-channel-icon-bg-color: <?php echo esc_attr(Arr::get($settings, 'styles.channel_icon_bg_color', '#EA4335')); ?>"
     <?php
     echo esc_attr($wpsr_popup_delay) .' '.
         esc_attr($wpsr_display_popup).' '.
         esc_attr($wpsr_popup_target_data);

     // Output the JSON params directly since it's already properly encoded
     echo $wpsr_chats_params_data; // phpcs:ignore ?>
>
    <?php
        if(Arr::get($settings, 'layout_type', 'chat_box')){
            $wpsr_app->view->render('public.chat-templates.elements.'.$settings['layout_type'].'-layout', array(
                'app'                => $wpsr_app,
                'templateSettings'   => $templateSettings,
                'settings'           => $settings,
                'channel_name'       => $wpsr_channel_name
            ));
        }

        if($settings['layout_type'] === 'chat_box' || sizeof($settings['channels']) > 1){
            $wpsr_app->view->render('public.chat-templates.elements.bubble-icon', array(
                'templateSettings'   => $templateSettings,
                'settings'           => $settings
            ));
        }
    ?>
</div>

