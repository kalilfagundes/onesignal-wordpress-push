<?php
/*
Plugin Name: OneSignal Notifications
Description: Sends OneSignal push notifications via REST API whenever a post is published.
*/

// Intermediate function to get the recent post and call the notification sending function. It also makes the push notification be sent only once (without it the post_publish function would be trigger more than once and also the push notifications).
function GetRecentPost($post_id) {
    // Check if notification has already been sent for this post
    $notification_sent = get_post_meta($post_id, 'onesignal_notification_sent', true);
    
    if (!$notification_sent) {
        $recent_posts = wp_get_recent_posts(array(
            'numberposts' => 1, // Number of recent posts to display
            'post_status' => 'publish' // Show only published posts
        ));

        foreach ($recent_posts as $post_item) {
            send_notification_for_new_post($post_item['ID']);
            // Mark the post as having the notification sent
            update_post_meta($post_item['ID'], 'onesignal_notification_sent', true);
        }
    }
}

// Call after post publishing
add_action('publish_post', 'GetRecentPost');

// Function to send OneSignal push notification
function send_notification_for_new_post($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post_id) {
        // Get post details
        $title = sanitize_text_field(get_the_title($post_id));
        $decoded_title = html_entity_decode($title, ENT_COMPAT | ENT_QUOTES | ENT_HTML401, 'UTF-8');
        $url = get_permalink($post_id);

        // Notification data
        $notification_data = array(
            'app_id' => 'YOUR-APP-ID-HERE',
            'headings' => array(
                'en' => 'YOUR-BLOG-NAME-HERE', // Use blog name as defined by a variable instead of a direct string
            ),
            'contents' => array(
                'en' => $decoded_title,
            ),
            'url' => $url,
            'chrome_web_icon' => 'YOUR-ICON-URL-HERE',
            'firefox_icon' => 'YOUR-ICON-URL-HERE',
            'included_segments' => array('Total Subscriptions'), // Send to all subscribers
        );

        // Request headers and body
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic YOUR-REST-API-KEY-HERE',
        );

        $url = 'https://onesignal.com/api/v1/notifications';

        // Send the notification
        $response = wp_remote_post($url, array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($notification_data),
            'httpversion' => '1.0',
            'sslverify' => false,
            'timeout' => 60,
            'redirection' => 5,
            'blocking' => true,
        ));

        if (is_wp_error($response)) {
            error_log('Error sending request: ' . $response->get_error_message());
        } else {
            $body = wp_remote_retrieve_body($response);
            error_log('Server response: ' . $body);
        }
    }
}
