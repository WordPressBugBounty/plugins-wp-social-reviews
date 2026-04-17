<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
$wpsr_close_button_color = Arr::get($settings, 'styles.close_button_color', '#1d2129');

if($settings['channels'] && (in_array('fluent_forms', $channel_name) || in_array('whatsapp', $channel_name) || in_array('sms', $channel_name))){ ?>
    <div class="wpsr-fm-chat-box">
        <?php if( Arr::get($settings, 'layout_type') !== 'icons' ) {?>
            <div class="wpsr-fm-chat-close" style="--wpsn-chat-close-btn-color: <?php echo ($wpsr_close_button_color) ? esc_attr($wpsr_close_button_color) : '#1d2129'; ?>"></div>
        <?php } ?>

        <?php
            $app->view->render('public.chat-templates.elements.fluent-form', array(
                'templateSettings' => $templateSettings,
                'settings' => $settings
            ));
        ?>

        
    <!-- Hidden Chat Container -->
    <div class="iconChatContainer" style="display: none;">
        <div class="wpsr-fm-chat-close" style="--wpsn-chat-close-btn-color: <?php echo ($wpsr_close_button_color) ? esc_attr($wpsr_close_button_color) : '#1d2129'; ?>"></div>
        
        <?php
        if (
            $settings['channels'] &&
            (in_array('whatsapp', $channel_name) || in_array('sms', $channel_name))
        ) {
            $app->view->render('public.chat-templates.elements.header', array(
                'settings' => $settings,
                'templateSettings' => $templateSettings,
            ));
        }

        if (
            $settings['channels'] &&
            (in_array('whatsapp', $channel_name) || in_array('sms', $channel_name))
        ) {
            echo '<div class="wpsr-fm-chat-room">';

            $app->view->render('public.chat-templates.elements.welcome-message', array(
                'templateSettings' => $templateSettings,
                'settings' => $settings
            ));

            $app->view->render('public.chat-templates.elements.channels', array(
                'templateSettings' => $templateSettings,
                'settings' => $settings,
                'app' => $app
            ));
            echo '</div>';
        }
        ?>
    </div>
    </div>
<?php }

$wpsr_btn_icons_class = sizeof($settings['channels']) > 1 ? 'wpsr-channels-icons' : 'wpsr-channels-icon';

echo '<div class="wpsr-channels '.esc_attr($wpsr_btn_icons_class).'">';
$app->view->render('public.chat-templates.elements.channels-button', array(
    'templateSettings'   => $templateSettings,
    'settings'           => $settings
));
echo '</div>';