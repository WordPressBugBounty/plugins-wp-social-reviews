<?php
defined('ABSPATH') or die;

    /* translators: %s: Human-readable time difference. */
    $wpsr_human_time_diff = sprintf(__('%s ago', 'wp-social-reviews'), human_time_diff(strtotime($review_time)));
?>
<span data-time="<?php echo esc_attr($wpsr_human_time_diff); ?>" class="wpsr-review-date">
     <?php
         $wpsr_date_format = get_option( 'date_format' );
         echo esc_html(date_i18n($wpsr_date_format, strtotime($review_time)));
     ?>
</span>