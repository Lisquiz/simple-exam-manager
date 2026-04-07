<?php
/*
Plugin Name: Simple Exam Manager
Description: A simple plugin to manage exams and questions with CSV import.
Version: 1.0.9
Author: Thiru with Grok
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/database.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/frontend.php';
require_once plugin_dir_path(__FILE__) . 'includes/csv-import.php';

register_activation_hook(__FILE__, 'sem_install');

add_action('admin_menu', 'sem_admin_menu');
add_action('admin_init', 'sem_handle_csv_upload');
add_shortcode('simple_exam', 'sem_display_exam');
add_shortcode('sem_rank_list', 'sem_display_rank_list');

function sem_enqueue_scripts() {
    wp_enqueue_script('sem-scripts', plugin_dir_url(__FILE__) . 'assets/js/scripts.js', ['jquery'], '1.0.9', true);
    wp_localize_script('sem-scripts', 'semAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sem_review_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'sem_enqueue_scripts');

add_action('wp_ajax_sem_mark_review', 'sem_mark_review_callback');
function sem_mark_review_callback() {
    check_ajax_referer('sem_review_nonce', 'nonce');
    global $wpdb;
    $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
    $question_id = isset($_POST['question_id']) ? (int) $_POST['question_id'] : 0;
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
    $user_id = get_current_user_id();

    if ($exam_id <= 0 || $question_id <= 0 || !in_array($action_type, ['mark', 'unmark']) || !$user_id) {
        error_log('Simple Exam Manager: Mark review failed - Invalid data');
        wp_send_json_error('Invalid request');
    }

    if ($action_type === 'mark') {
        $wpdb->insert(
            $wpdb->prefix . 'sem_reviews',
            ['exam_id' => $exam_id, 'question_id' => $question_id, 'user_id' => $user_id],
            ['%d', '%d', '%d']
        );
        if ($wpdb->last_error) {
            error_log('Simple Exam Manager: Mark review insert failed - ' . $wpdb->last_error);
            wp_send_json_error('Database error');
        }
    } else {
        $wpdb->delete(
            $wpdb->prefix . 'sem_reviews',
            ['exam_id' => $exam_id, 'question_id' => $question_id, 'user_id' => $user_id],
            ['%d', '%d', '%d']
        );
        if ($wpdb->last_error) {
            error_log('Simple Exam Manager: Unmark review delete failed - ' . $wpdb->last_error);
            wp_send_json_error('Database error');
        }
    }

    error_log('Simple Exam Manager: Mark review processed - Action: ' . $action_type . ', Exam ID: ' . $exam_id . ', Question ID: ' . $question_id);
    wp_send_json_success();
}
