<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Comments Integration
 * Integrates Mostaager discussions with WordPress comments system
 */

// Register discussion taxonomy for categorizing comments by building
add_action('init', 'ms_register_discussion_taxonomy');

function ms_register_discussion_taxonomy() {
    if (taxonomy_exists('ms_discussion_category')) {
        return;
    }
    
    register_taxonomy('ms_discussion_category', 'comment', [
        'labels' => [
            'name' => 'تصنيفات المناقشات',
            'singular_name' => 'تصنيف المناقشة',
            'search_items' => 'بحث في التصنيفات',
            'all_items' => 'جميع التصنيفات',
            'parent_item' => 'التصنيف الأب',
            'parent_item_colon' => 'التصنيف الأب:',
            'edit_item' => 'تعديل التصنيف',
            'update_item' => 'تحديث التصنيف',
            'add_new_item' => 'إضافة تصنيف جديد',
            'new_item_name' => 'اسم التصنيف الجديد',
            'menu_name' => 'تصنيفات المناقشات',
        ],
        'hierarchical' => true,
        'show_ui' => false,
        'show_in_menu' => false,
        'query_var' => true,
        'rewrite' => false,
    ]);
    
    // Create default discussion categories
    $default_categories = [
        'general' => 'عام',
        'maintenance' => 'صيانة',
        'financial' => 'مالي',
        'complaints' => 'شكاوى',
        'suggestions' => 'اقتراحات',
    ];
    
    foreach ($default_categories as $slug => $name) {
        if (!term_exists($slug, 'ms_discussion_category')) {
            wp_insert_term($name, 'ms_discussion_category', ['slug' => $slug]);
        }
    }
}

// Override ms_create_discussion to use WordPress comments
if (!function_exists('ms_create_discussion')) {
    function ms_create_discussion($building_id, $user_id, $title, $content, $category = 'general') {
        // Create a virtual post for the discussion
        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'ms_discussion',
            'post_author' => $user_id,
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return false;
        }
        
        // Add building ID as post meta
        update_post_meta($post_id, 'ms_building_id', $building_id);
        
        // Add category
        wp_set_object_terms($post_id, $category, 'ms_discussion_category');
        
        // Add initial comment as the discussion content
        $comment_data = [
            'comment_post_ID' => $post_id,
            'comment_author' => get_the_author_meta('display_name', $user_id),
            'comment_author_email' => get_the_author_meta('user_email', $user_id),
            'comment_content' => $content,
            'comment_approved' => 1,
            'user_id' => $user_id,
        ];
        
        $comment_id = wp_insert_comment($comment_data);
        
        return [
            'post_id' => $post_id,
            'comment_id' => $comment_id,
        ];
    }
}

// Get discussions for a building
if (!function_exists('ms_get_building_discussions')) {
    function ms_get_building_discussions($building_id, $limit = 20) {
        $args = [
            'post_type' => 'ms_discussion',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'ms_building_id',
                    'value' => $building_id,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        return get_posts($args);
    }
}

// Get comments for a discussion
if (!function_exists('ms_get_discussion_comments')) {
    function ms_get_discussion_comments($post_id) {
        $args = [
            'post_id' => $post_id,
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'ASC',
        ];
        
        return get_comments($args);
    }
}

// Add comment to discussion
if (!function_exists('ms_add_discussion_comment')) {
    function ms_add_discussion_comment($post_id, $user_id, $content) {
        $comment_data = [
            'comment_post_ID' => $post_id,
            'comment_author' => get_the_author_meta('display_name', $user_id),
            'comment_author_email' => get_the_author_meta('user_email', $user_id),
            'comment_content' => $content,
            'comment_approved' => 1,
            'user_id' => $user_id,
        ];
        
        return wp_insert_comment($comment_data);
    }
}

// Sync old ms_discussion posts to WordPress comments
add_action('init', 'ms_sync_old_discussions');

function ms_sync_old_discussions() {
    if (get_option('ms_discussions_synced')) {
        return;
    }
    
    $old_discussions = get_posts([
        'post_type' => 'ms_discussion',
        'posts_per_page' => -1,
        'post_status' => 'any',
    ]);
    
    foreach ($old_discussions as $discussion) {
        // Check if it already has comments
        $comments = get_comments(['post_id' => $discussion->ID]);
        
        if (empty($comments)) {
            // Add initial comment
            $comment_data = [
                'comment_post_ID' => $discussion->ID,
                'comment_author' => get_the_author_meta('display_name', $discussion->post_author),
                'comment_author_email' => get_the_author_meta('user_email', $discussion->post_author),
                'comment_content' => $discussion->post_content,
                'comment_approved' => 1,
                'user_id' => $discussion->post_author,
                'comment_date' => $discussion->post_date,
            ];
            
            wp_insert_comment($comment_data);
        }
    }
    
    update_option('ms_discussions_synced', true);
}

// Add notification when new comment is added
add_action('wp_insert_comment', 'ms_notify_new_discussion_comment', 10, 2);

function ms_notify_new_discussion_comment($comment_id, $comment) {
    $post = get_post($comment->comment_post_ID);
    
    if ($post->post_type !== 'ms_discussion') {
        return;
    }
    
    $building_id = get_post_meta($post->ID, 'ms_building_id', true);
    if (!$building_id) {
        return;
    }
    
    // Notify building manager
    $manager_id = get_post_meta($building_id, 'manager_id', true);
    if ($manager_id && function_exists('ms_add_notification')) {
        $message = 'رد جديد في مناقشة: ' . $post->post_title;
        ms_add_notification($manager_id, $message, 'discussion', $post->ID, $building_id);
    }
    
    // Notify discussion author if not the same as commenter
    if ($comment->user_id && $comment->user_id != $post->post_author) {
        if (function_exists('ms_add_notification')) {
            $message = 'رد جديد في مناقشتك: ' . $post->post_title;
            ms_add_notification($post->post_author, $message, 'discussion', $post->ID, $building_id);
        }
    }
}

// Filter comments to show only building discussions in dashboard
add_filter('comments_clauses', 'ms_filter_building_discussions', 10, 2);

function ms_filter_building_discussions($clauses, $wp_comment_query) {
    if (!is_admin() || !isset($_GET['building_id'])) {
        return $clauses;
    }
    
    $building_id = intval($_GET['building_id']);
    
    // Get discussion post IDs for this building
    $discussion_posts = get_posts([
        'post_type' => 'ms_discussion',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'ms_building_id',
                'value' => $building_id,
                'compare' => '=',
            ],
        ],
    ]);
    
    if (empty($discussion_posts)) {
        $clauses['where'] .= ' AND 1=0';
    } else {
        $clauses['where'] .= ' AND comment_post_ID IN (' . implode(',', $discussion_posts) . ')';
    }
    
    return $clauses;
}
