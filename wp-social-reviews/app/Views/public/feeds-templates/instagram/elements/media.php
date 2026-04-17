<?php defined('ABSPATH') or die; ?>
<div class="wpsr-ig-post-media <?php echo esc_attr("$animation_img_class $media_type"); ?>"
     id="wpsr-video-play-<?php echo esc_attr($index); ?>">

    <?php if ($media_type === 'VIDEO' && !empty($media_url) && strpos($media_url, '.mp4') !== false): ?>
        <video
               class="wpsr-ig-post-video"
               poster="<?php echo esc_url($thumbnail_url); ?>"
               <?php echo $template_meta["post_settings"]["display_mode"] === 'inline' ?
                   'controls loop webkit-playsinline playsinline' : ''; ?>>
            <source src="<?php echo esc_url($media_url); ?>" type="video/mp4">
        </video>
    <?php endif; ?>

    <?php if ($media_type === 'VIDEO' && empty($media_url)): ?>
        <img class="wpsr-ig-post-img"
             src="<?php echo esc_url($display_optimize_image === 'true' && $media_url ? $media_url : $thumbnail_url); ?>"
             alt="<?php echo isset($feed['caption']) ? esc_attr($feed['caption']) : ''; ?>"
             loading="lazy">
    <?php endif; ?>

    <?php if ($media_type === 'VIDEO' && !empty($media_url) && strpos($media_url, '.mp4') === false): ?>
        <img class="wpsr-ig-post-img"
             src="<?php echo esc_url($display_optimize_image === 'true' && $media_url ? $media_url : $thumbnail_url); ?>"
             alt="<?php echo isset($feed['caption']) ? esc_attr($feed['caption']) : ''; ?>"
             loading="lazy">
    <?php endif; ?>

    <?php if ($media_type === 'IMAGE' || $media_type === 'oembed'): ?>
        <img class="wpsr-ig-post-img <?php echo esc_attr($placeholder_img_class); ?>" 
             src="<?php echo esc_url($display_optimize_image === 'true' && $media_url ? $media_url : $default_media); ?>" 
             alt="<?php echo isset($feed['caption']) ? esc_attr($feed['caption']) : ''; ?>" 
             loading="lazy">
    <?php endif; ?>

    <?php if ($media_name !== 'IMAGE'): ?>
        <div class="wpsr-ig-post-type-icon wpsr-ig-post-type-<?php echo esc_attr($media_name); ?>"></div>
    <?php endif; ?>
</div>
