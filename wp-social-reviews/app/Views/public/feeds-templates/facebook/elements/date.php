<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
?>
<span class="wpsr-fb-feed-time">
  <?php
      $wpsr_created_time = Arr::get($feed, 'created_time');
      echo esc_html($wpsr_created_time);
  ?>
</span>