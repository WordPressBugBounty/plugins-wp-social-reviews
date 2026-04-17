<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Chats\Helper as chatHelper;
use WPSocialReviews\Framework\Support\Arr;

$wpsr_channels = Arr::get($settings, 'channels', []);
$wpsr_prefilled_supported = ['whatsapp', 'sms'];

if(empty($wpsr_channels)) {
    return;
}

$wpsr_template = Arr::get($settings, 'template', '');
$wpsr_image_url = chatHelper::getImageUrl($settings);
$wpsr_prefilled_platforms = chatHelper::getPrefilledPlatform();
?>
<?php foreach ($wpsr_channels as $wpsr_key => $wpsr_channel) {
    $wpsr_is_url = chatHelper::isUrl($wpsr_channel['credential']);
    
    $wpsr_credential = $wpsr_is_url ? $wpsr_channel['credential'] : $wpsr_channel['webUrl'] . $wpsr_channel['credential'];
    $wpsr_image_url = count($settings['channels']) > 1 ? WPSOCIALREVIEWS_URL . 'assets/images/svg/' . $wpsr_channel['name'] . '.svg' : $wpsr_image_url;
    $wpsr_is_prefilled_supported = in_array($wpsr_channel['name'], $wpsr_prefilled_platforms);
    $wpsr_has_prefilled = ($wpsr_is_prefilled_supported && isset($settings['chat_button']['prefilled_message']) && $settings['chat_button']['prefilled_message'] === 'true') ? true : false;
    ?>
    <div class="wpsr-channel-item <?php echo esc_attr($wpsr_channel['name'] . $wpsr_key); ?>" data-channel-name="<?php echo esc_attr($wpsr_channel['name']); ?>">
        <?php if ($settings['layout_type'] === 'icons') {
            $wpsr_label = Arr::get($wpsr_channel, 'label');
            $wpsr_label = apply_filters('wpsocialreviews/' . $wpsr_channel['name'] . '_chat_channel_label', $wpsr_label);
            ?>
            <?php if ($wpsr_label != '') { ?>
                <span class="wpsr-channel-name">
                    <?php echo esc_html($wpsr_label); ?>
                </span>
            <?php } ?>
        <?php } ?>

        <?php
            if(strpos($wpsr_credential, 'fluentform_modal')){
                echo do_shortcode($wpsr_credential);
            }
            if(!strpos($wpsr_credential, 'fluentform_modal')){
                if(strpos($wpsr_credential, 'mailto') !== false || strpos($wpsr_credential, 'tel') !== false || strpos($wpsr_credential, '?users=') !== false){
                    $wpsr_credential = chatHelper::encodeCredentials($wpsr_credential);
                }
                $wpsr_credential = str_replace('=+', '=', $wpsr_credential);
            }
        ?>

        <a role="button"
           data-chat-url="<?php echo esc_attr($wpsr_credential); ?>"
           data-channel="<?php echo esc_attr($wpsr_channel['name']); ?>"
           data-form-id="<?php echo esc_attr($wpsr_credential); ?>"
           data-number="<?php echo esc_attr($wpsr_channel['credential']); ?>"
           data-prefilled="<?php echo esc_attr($wpsr_has_prefilled); ?>"
           data-all-ff-ids="<?php echo esc_attr(implode(',', array_column($wpsr_channels, 'credential'))); ?>"
           style="background-color:<?php echo esc_attr(Arr::get($settings, 'styles.channel_icon_bg_color', '')); ?>"
           class="wpsr-channel-btn <?php echo esc_attr($wpsr_channel['name']); ?> <?php echo esc_attr($wpsr_channel['name'].$wpsr_key); ?>"
        >
            <?php
                if ($settings['chat_button']['display_icon'] === 'true') {
                ?>
                    <img src="<?php echo esc_url($wpsr_image_url); ?>" alt="<?php echo esc_attr($wpsr_channel['name']); ?>" width="32" height="32">
                <?php
                }
            ?>
        </a>

        <?php
            $wpsr_show_button = Arr::get($settings, 'settings.show_label', 'false');

            if ($wpsr_channel['label'] != '' 
            && $settings['chat_button']['display_icon'] === 'true' 
            && $wpsr_show_button === 'true' 
            && $settings['layout_type'] !== 'icons') {
            ?>
               <p class="wpsr-channel-label"><?php echo esc_html($wpsr_channel['label']); ?></p>
             <?php
            }
        ?>

    </div>
<?php } ?>