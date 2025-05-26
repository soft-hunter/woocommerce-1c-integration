<?php
if (!defined('WP_CLI')) {
  if (!current_user_can('shop_manager') && !current_user_can('administrator')) exit("No permissions\n");

  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $nonce = wp_create_nonce('wc1c_clean_action');
    ?>
    <form method="post">
      <?php wp_nonce_field('wc1c_clean_action', 'wc1c_clean_nonce'); ?>
      <input type="submit" value="Clean">
      <p><strong>Warning:</strong> This will permanently delete all 1C data from your site!</p>
    </form>
    <?php
  }

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify nonce for CSRF protection
    if (!isset($_POST['wc1c_clean_nonce']) || !wp_verify_nonce($_POST['wc1c_clean_nonce'], 'wc1c_clean_action')) {
      wp_die('Security check failed. Please try again.');
    }
    
    // Additional confirmation
    if (!isset($_POST['confirm_clean'])) {
      ?>
      <form method="post">
        <?php wp_nonce_field('wc1c_clean_action', 'wc1c_clean_nonce'); ?>
        <input type="hidden" name="confirm_clean" value="1">
        <p><strong>Are you sure you want to delete all 1C data?</strong></p>
        <input type="submit" value="Yes, Delete All Data" style="background: red; color: white;">
        <a href="?" style="margin-left: 10px;">Cancel</a>
      </form>
      <?php
      exit;
    }
  } else {
    exit;
  }
}

global $wpdb;

if (!isset($wpdb->termmeta)) exit("WooCommerce plugin is not active");

wc1c_disable_time_limit();

// if (is_dir(WC1C_DATA_DIR)) {
//   $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(WC1C_DATA_DIR, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
//   foreach ($iterator as $path => $item) {
//     if ($item->isDir()) {
//       rmdir($path) or wc1c_error(sprintf("Failed to remove directory %s", $path));
//     }
//     else {
//       unlink($path) or wc1c_error(sprintf("Failed to unlink file %s", $path));
//     }
//   }
// }
// else {
//   mkdir(WC1C_DATA_DIR) or wc1c_error(sprintf("Failed to make directory %s", WC1C_DATA_DIR));
// }

$rows = $wpdb->get_results("SELECT tm.term_id, taxonomy FROM $wpdb->termmeta tm JOIN $wpdb->term_taxonomy tt ON tm.term_id = tt.term_id WHERE meta_key = 'wc1c_guid'");
foreach ($rows as $row) {
  wp_delete_term($row->term_id, $row->taxonomy);
}

$attribute_ids = get_option('wc1c_guid_attributes', array());
foreach ($attribute_ids as $attribute_id) {
  wc1c_delete_woocommerce_attribute($attribute_id);
}
delete_transient('wc_attribute_taxonomies');

$option_names = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wc1c_%'");
foreach ($option_names as $option_name) {
  delete_option($option_name);
}

$post_ids = $wpdb->get_col("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wc1c_guid'");
foreach ($post_ids as $post_id) {
  $post_attachments = get_attached_media('image', $post_id);
  foreach ($post_attachments as $post_attachment) {
    wp_delete_attachment($post_attachment->ID, true);
  }

  wp_delete_post($post_id, true);
}

echo defined('WP_CLI') ? "\x07" : "Done";
