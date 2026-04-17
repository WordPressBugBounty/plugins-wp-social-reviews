<?php defined('ABSPATH') or die;

if ($caption && isset($template_meta['template']) && $template_meta['template'] === 'template2') { ?>
    <div class="wpsr-ig-post-caption">
        <p class="wpsr-ig-post-caption-text">
            <?php
            $wpsr_caption = preg_replace(
                "/#([\p{L}\p{N}_]+)/u",
                '<a class="wpsr-ig-post-caption-tags" href="https://www.instagram.com/explore/tags/$1" target="_blank">#$1</a>',
                nl2br($caption)
            );
            echo wp_kses_post($wpsr_caption);
            ?>
        </p>
    </div>
<?php } elseif ($caption) { ?>
    <div class="wpsr-ig-post-caption">
        <p class="wpsr-ig-post-caption-text">
            <?php echo isset($feed['caption']) ? wp_kses_post(nl2br($caption)) : ''; ?>
        </p>
    </div>
<?php }