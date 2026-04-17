<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Chats\Helper as chatHelper;
use WPSocialReviews\Framework\Support\Arr;

$wpsr_image_url = chatHelper::getImageUrl($settings);
$wpsr_prefilled_platforms = chatHelper::getPrefilledPlatform();
$wpsr_whatsapp_bg_color = !empty(Arr::get($settings, 'styles.message_background_color')) ? 'background-color:' . Arr::get($settings, 'styles.message_background_color') . ';' : '';
$wpsr_input_text_color = !empty(Arr::get($settings, 'styles.message_text_color')) ? 'color:' . Arr::get($settings, 'styles.message_text_color') . ';' : '';
$wpsr_send_btn_bg_color = !empty(Arr::get($settings, 'styles.send_button_bg_color')) ? 'background-color:' . Arr::get($settings, 'styles.send_button_bg_color') . ';' : '';
$wpsr_send_btn_icon_color = !empty(Arr::get($settings, 'styles.send_button_icon_color')) ? 'fill:' . Arr::get($settings, 'styles.send_button_icon_color') . ';' : '';
$wpsr_phone_number = Arr::get($settings, 'channels.0.credential');
$wpsr_prefilled_placeholder_text = Arr::get($settings, 'chat_button.prefilled_placeholder_text', 'Type a message');
?>

<div class="wpsr-fm-chat-btn-wrapper">
    <div class="wpsr-fm-btn-icon">
            <?php if ( $settings['channels'] && sizeof($settings['channels']) === 1){
                $wpsr_is_url = chatHelper::isUrl($settings['channels'][0]['credential']);
                $wpsr_credential = $wpsr_is_url ? $settings['channels'][0]['credential'] : $settings['channels'][0]['webUrl'] . $settings['channels'][0]['credential'];
                if(strpos($wpsr_credential, 'mailto') !== false || strpos($wpsr_credential, 'tel') !== false){
                    $wpsr_credential = chatHelper::encodeCredentials($wpsr_credential);
                }
                $wpsr_credential = str_replace('=+', '=', $wpsr_credential);
                $wpsr_channel_name = Arr::get($settings, 'channels.0.name');
                $wpsr_has_prefilled_message = isset($settings['chat_button']['prefilled_message']) && $settings['chat_button']['prefilled_message'] === 'true';
                ?>
                
                <?php if (!(in_array($wpsr_channel_name, $wpsr_prefilled_platforms)) || !$wpsr_has_prefilled_message) { ?>
                    <a role="button"
                       data-chat-url="<?php echo esc_attr($wpsr_credential); ?>"
                       data-channel="<?php echo esc_attr($settings['channels'][0]['name']); ?>"
                       style="background-color:<?php echo esc_attr(Arr::get($settings, 'styles.channel_icon_bg_color', '')); ?>"
                       class="wpsr-fm-btn <?php echo esc_attr($settings['channels'][0]['name']); ?>"
                    >
                            <span><?php echo esc_html($settings['chat_button']['button_text']); ?></span>
                            <?php
                            if ($settings['chat_button']['display_icon'] === 'true') {
                                if (strpos($wpsr_credential, 'fluentform_modal')) {
                                    echo do_shortcode($wpsr_credential);
                                }
                                if (!strpos($wpsr_credential, 'fluentform_modal')) {
                                ?>
                                <img src="<?php echo esc_url($wpsr_image_url); ?>" alt="<?php echo esc_attr($settings['channels'][0]['name']); ?>" width="32" height="32">
                                <?php } ?>
                            <?php } ?>
                    </a>
                <?php } else { ?>
                    <div class="wpsr-prefilled-input-container" data-channel-name="<?php echo esc_attr($wpsr_channel_name); ?>" data-phone-number="<?php echo esc_attr($wpsr_phone_number); ?>" <?php echo ($wpsr_whatsapp_bg_color) ? 'style="' . esc_attr($wpsr_whatsapp_bg_color) . '"' : ''; ?>>
                        <div class="wpsr-prefilled-input-container-inner">
                            <input type="text" placeholder="<?php echo esc_html($wpsr_prefilled_placeholder_text); ?>" class="wpsr-prefilled-input" <?php echo ($wpsr_input_text_color) ? 'style="' . esc_attr($wpsr_input_text_color) . '"' : ''; ?>>
                        </div>
                        <button class="wpsr-prefilled-send-button" <?php echo ($wpsr_send_btn_bg_color) ? 'style="' . esc_attr($wpsr_send_btn_bg_color) . '"' : ''; ?>>
                            <svg <?php echo ($wpsr_send_btn_icon_color) ? 'style="' . esc_attr($wpsr_send_btn_icon_color) . '"' : ''; ?> viewBox="0 0 24 24" x="0px" y="0px" class="wpsr-prefilled-send-button-icon">
                                <title>send</title>
                                <path d="M1.101,21.757L23.8,12.028L1.101,2.3l0.011,7.912l13.623,1.816L1.112,13.845 L1.101,21.757z"></path>
                            </svg>
                        </button>
                    </div>
                <?php } ?>
            <?php } ?>
            <?php if (sizeof($settings['channels']) > 1){ ?>
            <span class="wpsr-fm-multiple-btn"><?php echo esc_html($settings['chat_button']['button_text']); ?></span>
            <div class="wpsr-channels <?php echo sizeof($settings['channels']) == 1 ? 'wpsr-social-channel' : ''; ?>">
                <?php
                $app->view->render('public.chat-templates.elements.channels-button', array(
                    'templateSettings'   => $templateSettings,
                    'settings'           => $settings
                ));
                ?>
            </div>
            <div class="wpsr-prefilled-input-container" style="display:none" <?php echo ($wpsr_whatsapp_bg_color) ? 'style="' . esc_attr($wpsr_whatsapp_bg_color) . '"' : ''; ?>>
                <div class="wpsr-prefilled-input-container-inner">
                    <input type="text" placeholder="<?php echo esc_html($wpsr_prefilled_placeholder_text); ?>" class="wpsr-prefilled-input" <?php echo ($wpsr_input_text_color) ? 'style="' . esc_attr($wpsr_input_text_color) . '"' : ''; ?>>
                </div>
                <button class="wpsr-prefilled-send-button" <?php echo ($wpsr_send_btn_bg_color) ? 'style="' . esc_attr($wpsr_send_btn_bg_color) . '"' : ''; ?>>
                    <svg <?php echo ($wpsr_send_btn_icon_color) ? 'style="' . esc_attr($wpsr_send_btn_icon_color) . '"' : ''; ?> viewBox="0 0 24 24" x="0px" y="0px" class="wpsr-prefilled-send-button-icon">
                        <title>send</title>
                        <path d="M1.101,21.757L23.8,12.028L1.101,2.3l0.011,7.912l13.623,1.816L1.112,13.845 L1.101,21.757z"></path>
                    </svg>
                </button>
            </div>
    <?php } ?>
    </div>
</div>