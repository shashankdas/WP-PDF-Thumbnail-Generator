<?php
/*
Plugin Name: PDF Thumbnail Generator from Khudra
Description: Generates thumbnails for uploaded PDFs using Imagick and saves the first page as a separate image in the media library with original PDF dimensions.
Version: 2.5
Plugin URI: http://khudra.asia/
Author: Khudra Corporation - Shashank
Author URI: http://khudra.asia/
*/

if (!defined("ABSPATH")) {
    exit();
}

class PDF_Thumbnail_Generator_Imagick
{
    public function __construct()
    {
        add_filter(
            "wp_generate_attachment_metadata",
            [$this, "generate_pdf_thumbnail"],
            10,
            2
        );
    }

    public function generate_pdf_thumbnail($metadata, $attachment_id)
    {
        $file_path = get_attached_file($attachment_id);
        $file_type = wp_check_filetype($file_path);

        // Only process PDF files
        if (strtolower($file_type["ext"]) !== "pdf") {
            return $metadata;
        }

        try {
            $imagick = new Imagick();
            $imagick->setResolution(150, 150); // Set the resolution for PDF rendering (good balance between quality and performance)
            $imagick->readImage($file_path . "[0]"); // Read only the first page of the PDF
            $imagick->setImageFormat("jpeg"); // Set the format to JPEG for the thumbnail
            $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality(90); // Quality level for compression

            // --- Fix for blacked-out white text areas --- //
            // Flatten the image onto a white background to resolve transparency issues
            $background = new Imagick();
            $background->newImage(
                $imagick->getImageWidth(),
                $imagick->getImageHeight(),
                new ImagickPixel("white") // Ensure a white background behind the PDF content
            );
            $background->compositeImage(
                $imagick,
                Imagick::COMPOSITE_OVER, // Composite the PDF image on top of the white background
                0,
                0
            );
            $background->setImageFormat("jpeg"); // Make sure to convert to JPEG after flattening
            $imagick = $background; // Use the flattened image for further operations

            // --- Ensure no color or transparency issues --- //
            // Force RGB colorspace to avoid grayscale/CYM issues in some PDFs
            $imagick->setImageColorspace(Imagick::COLORSPACE_RGB);
            $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE); // Remove any alpha channel (transparency)

            $upload_dir = wp_upload_dir();
            $thumb_base = basename($file_path, ".pdf") . "-thumb.jpg"; // Thumbnail file name
            $thumb_path = $upload_dir["path"] . "/" . $thumb_base;

            // Save the generated thumbnail image
            $imagick->writeImage($thumb_path);
            $imagick->clear();
            $imagick->destroy();

            // Add the generated thumbnail to the media library
            $attachment = [
                "post_mime_type" => "image/jpeg",
                "post_title" => sanitize_file_name($thumb_base),
                "post_content" => "",
                "post_status" => "inherit",
            ];
            $attach_id = wp_insert_attachment($attachment, $thumb_path);
            require_once ABSPATH . "wp-admin/includes/image.php";
            $attach_data = wp_generate_attachment_metadata(
                $attach_id,
                $thumb_path
            );
            wp_update_attachment_metadata($attach_id, $attach_data);

            // Update the original PDF's meta to link to the new thumbnail
            update_post_meta(
                $attachment_id,
                "_custom_pdf_thumb",
                wp_get_attachment_url($attach_id)
            );
        } catch (Exception $e) {
            error_log(
                "Imagick failed to generate PDF thumbnail: " . $e->getMessage()
            );
        }

        return $metadata;
    }
}

new PDF_Thumbnail_Generator_Imagick();
