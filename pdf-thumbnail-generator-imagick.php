<?php
/*
Plugin Name: PDF Thumbnail Generator with Imagick
Description: Generates 1000x1173px thumbnails for uploaded PDFs using Imagick and saves both the default thumbnail and a separate image file in the media library.
Version: 1.1
Author: Shashank Verma
*/

if (!defined('ABSPATH')) exit;

class PDF_Thumbnail_Generator_Imagick {

    public function __construct() {
        add_filter('wp_generate_attachment_metadata', [$this, 'generate_pdf_thumbnail'], 10, 2);
    }

    public function generate_pdf_thumbnail($metadata, $attachment_id) {
        $file_path = get_attached_file($attachment_id);
        $file_type = wp_check_filetype($file_path);

        if (strtolower($file_type['ext']) !== 'pdf') {
            return $metadata;
        }

        try {
            $imagick = new Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage($file_path . '[0]');
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality(90);
            $imagick->resizeImage(1000, 1173, Imagick::FILTER_LANCZOS, 1);

            $upload_dir = wp_upload_dir();
            $thumb_base = basename($file_path, '.pdf') . '-thumb.jpg';
            $thumb_path = $upload_dir['path'] . '/' . $thumb_base;

            // Save the thumbnail image file
            $imagick->writeImage($thumb_path);
            $imagick->clear();
            $imagick->destroy();

            // Add the custom thumbnail to media library
            $attachment = [
                'post_mime_type' => 'image/jpeg',
                'post_title'     => sanitize_file_name($thumb_base),
                'post_content'   => '',
                'post_status'    => 'inherit'
            ];
            $attach_id = wp_insert_attachment($attachment, $thumb_path);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $thumb_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            update_post_meta($attachment_id, '_custom_pdf_thumb', wp_get_attachment_url($attach_id));

        } catch (Exception $e) {
            error_log('Imagick failed to generate thumbnail: ' . $e->getMessage());
        }

        return $metadata;
    }
}

new PDF_Thumbnail_Generator_Imagick();
