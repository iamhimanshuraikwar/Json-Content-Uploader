<?php
/**
 * Plugin Name: Content Uploader - SEO Content AI
 * Plugin URI: https://seocontent.ai/
 * Description: Upload and manage SEO-optimized content with artificial intelligence. This plugin allows you to bulk upload AI-generated content directly into WordPress posts, complete with SEO meta data and category organization.
 * Version: 1.0 | <a href="https://seocontent.ai/affiliate-dashboard/" target="_blank">🎁 Claim 50,000 FREE Words</a>
 * Author: Himanshu Raikwar
 * Author URI: https://himanshuraikwar.com/
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SEO_Content_AI {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
        add_action('wp_ajax_upload_seo_content', array($this, 'handle_json_upload'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Content Uploader',
            'Content Uploader',
            'manage_options',
            'seo-content-ai',
            array($this, 'render_admin_page'),
            'dashicons-upload',
            30
        );
    }

    public function register_admin_assets($hook) {
        if ($hook != 'toplevel_page_seo-content-ai') {
            return;
        }

        wp_enqueue_style(
            'seo-content-ai-style',
            plugins_url('assets/css/admin-style.css', __FILE__)
        );

        wp_enqueue_script(
            'seo-content-ai-script',
            plugins_url('assets/js/admin-script.js', __FILE__),
            array('jquery'),
            '1.0',
            true
        );

        wp_localize_script('seo-content-ai-script', 'seoContentAIAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_content_ai_nonce')
        ));
    }

    public function render_admin_page() {
        ?>
        <div class="wrap seo-content-ai-wrap">
            <h1>Content Uploader - SEO Content AI</h1>
            <div class="notice notice-info is-dismissible">
            <p><strong>🎁 Special Offer:</strong> <a href="https://seocontent.ai/affiliate-dashboard/" target="_blank">Claim your FREE 50K Words (50 Articles) of AI-Generated Content</a> - Limited time offer!</p>
        </div>
            
            <div class="card">
                <h2>Upload AI-Generated Content</h2>
                <p>Upload your SEO-optimized content JSON file. The content will be processed and posts will be created automatically with AI-enhanced optimization.</p>
                
                <form id="content-upload-form" method="post" enctype="multipart/form-data">
                    <div class="file-input-wrapper">
                        <div class="file-input-content">
                            <div class="file-input-icon">
                                <span class="dashicons dashicons-upload"></span>
                            </div>
                            <h3>Drag and drop your JSON file here</h3>
                            <p>or click to browse</p>
                            <div class="file-name"></div>
                        </div>
                        <input type="file" 
                               id="json_file" 
                               name="json_file" 
                               accept=".json" 
                               required>
                    </div>
    
                    <div id="file-details" style="display: none;">
                        <div class="notice notice-info">
                            <p><strong>Articles Found: </strong><span id="article-count">0</span></p>
                        </div>
                    </div>
    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="post_status">Default Post Status</label>
                            </th>
                            <td>
                                <select id="post_status" name="post_status">
                                    <option value="draft">Draft</option>
                                    <option value="publish">Publish</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="post_category">Default Category</label>
                            </th>
                            <td>
                                <?php
                                wp_dropdown_categories(array(
                                    'show_option_none' => 'Select Category',
                                    'option_none_value' => '',
                                    'name' => 'post_category',
                                    'id' => 'post_category',
                                    'required' => true,
                                    'hierarchical' => true,
                                    'show_count' => true,
                                    'hide_empty' => false,
                                ));
                                ?>
                            </td>
                        </tr>
                    </table>
    
                    <?php wp_nonce_field('seo_content_ai_nonce', 'seo_content_ai_nonce'); ?>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            Process & Upload Content
                        </button>
                    </p>
                </form>
    
                <div id="upload-results" style="display: none;">
                    <h3>Upload Results</h3>
                    <div class="results-content"></div>
                </div>
            </div>
        </div>
    
        <!-- Loading Animation -->
        <div class="loader-wrapper">
            <div class="loader"></div>
            <div class="loader-content">
                <p>Processing content with AI...</p>
                <div class="progress-bar">
                    <div class="progress-bar-fill"></div>
                </div>
                <p class="progress-text">0% Complete</p>
            </div>
        </div>
        <?php
    }

    public function handle_json_upload() {
        if (!check_ajax_referer('seo_content_ai_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid security token');
            return;
        }
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
    
        if (!isset($_FILES['json_file'])) {
            wp_send_json_error('No file uploaded');
            return;
        }
    
        $file = $_FILES['json_file'];
        
        // Check if there were any upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Upload failed: ' . $this->get_upload_error_message($file['error']));
            return;
        }
    
        // Read file content
        $json_content = file_get_contents($file['tmp_name']);
        
        // Validate JSON content
        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON format: ' . json_last_error_msg());
            return;
        }
    
        if (!is_array($data)) {
            wp_send_json_error('Invalid JSON structure: Expected an array');
            return;
        }
    
        $results = array(
            'success' => array(),
            'errors' => array()
        );
    
        foreach ($data as $article) {
            try {
                // Validate required fields
                if (empty($article['H1'])) {
                    throw new Exception('Missing required H1 title');
                }
    
                $post_id = $this->create_article_post($article);
                if ($post_id) {
                    $results['success'][] = array(
                        'title' => $article['H1'],
                        'id' => $post_id,
                        'url' => get_edit_post_link($post_id, 'url')
                    );
                }
            } catch (Exception $e) {
                $results['errors'][] = array(
                    'title' => isset($article['H1']) ? $article['H1'] : 'Unknown Article',
                    'error' => $e->getMessage()
                );
            }
        }
    
        wp_send_json_success($results);
    }
    
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }
    
    private function create_article_post($article_data) {
        // Prepare main content
        $content = $this->format_article_content($article_data);
    
        // Prepare post data
        $post_arr = array(
            'post_title' => sanitize_text_field($article_data['H1']),
            'post_content' => wp_kses_post($content),
            'post_status' => isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id()
        );
    
        // Add category if selected
        if (!empty($_POST['post_category'])) {
            $post_arr['post_category'] = array(intval($_POST['post_category']));
        }
    
        // Insert post
        $post_id = wp_insert_post($post_arr, true);
    
        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }
    
        // Add meta data for SEO
        if (!empty($article_data['Title Tag'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($article_data['Title Tag']));
        }
        if (!empty($article_data['Meta Description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($article_data['Meta Description']));
        }
    
        return $post_id;
    }

    private function format_article_content($article_data) {
        $content = '';

        // Add main content
        if (!empty($article_data['H1_content'])) {
            $content .= wp_kses_post($article_data['H1_content']) . "\n\n";
        }

        // Add H2 sections
        for ($i = 1; $i <= 4; $i++) {
            $h2_key = "H2_{$i}";
            $content_key = "H2_{$i}_content";
            
            if (!empty($article_data[$h2_key]) && !empty($article_data[$content_key])) {
                $content .= '<h2>' . sanitize_text_field($article_data[$h2_key]) . '</h2>' . "\n";
                $content .= wp_kses_post($article_data[$content_key]) . "\n\n";
            }
        }

        return $content;
    }
}

// Initialize plugin
new WP_SEO_Content_AI();

// Activation hook
register_activation_hook(__FILE__, 'seo_content_ai_activate');
function seo_content_ai_activate() {
    // Create necessary database tables or options if needed
    add_option('seo_content_ai_version', '1.0');
    
    // Set default options
    $default_options = array(
        'post_status' => 'draft',
        'max_posts_per_batch' => 50,
        'enable_ai_enhancement' => true
    );
    add_option('seo_content_ai_settings', $default_options);
    
    // Clear any existing cached data
    delete_transient('seo_content_ai_cache');
    
    // Create upload directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $seo_content_dir = $upload_dir['basedir'] . '/seo-content-ai';
    if (!file_exists($seo_content_dir)) {
        wp_mkdir_p($seo_content_dir);
    }
    
    // Create an index.php file in the new directory for security
    if (!file_exists($seo_content_dir . '/index.php')) {
        $handle = @fopen($seo_content_dir . '/index.php', 'w');
        if ($handle) {
            fwrite($handle, "<?php\n// Silence is golden.");
            fclose($handle);
        }
    }
}