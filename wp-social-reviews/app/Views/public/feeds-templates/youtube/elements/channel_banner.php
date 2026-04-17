<?php defined('ABSPATH') or die;

if(!empty($custom_banner)) { ?>
<div class="wpsr-yt-header-banner">
    <img class="wpsr-yt-header-banner-desktop"
         src="<?php echo esc_url($custom_banner); ?>"
          loading="lazy">
</div>
<?php }