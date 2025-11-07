<?php
/**
 * FileUpload Class
 * Handles file uploads and media processing
 */

class FileUpload {

    /**
     * Upload image file
     */
    public static function uploadImage($file, $directory = 'posts') {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        // Validate file type
        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
            return ['success' => false, 'message' => 'Invalid image type'];
        }

        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File too large'];
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $upload_path = UPLOAD_DIR . $directory . '/' . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Get image dimensions
            $image_info = getimagesize($upload_path);
            $width = $image_info[0];
            $height = $image_info[1];

            // Create thumbnail
            $thumbnail = self::createThumbnail($upload_path, $directory);

            return [
                'success' => true,
                'filename' => $filename,
                'url' => $directory . '/' . $filename,
                'full_path' => $upload_path,
                'thumbnail' => $thumbnail,
                'width' => $width,
                'height' => $height,
                'type' => 'image'
            ];
        }

        return ['success' => false, 'message' => 'Failed to upload file'];
    }

    /**
     * Upload video file
     */
    public static function uploadVideo($file, $directory = 'reels') {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        // Validate file type
        if (!in_array($file['type'], ALLOWED_VIDEO_TYPES)) {
            return ['success' => false, 'message' => 'Invalid video type'];
        }

        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File too large'];
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $upload_path = UPLOAD_DIR . $directory . '/' . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            return [
                'success' => true,
                'filename' => $filename,
                'url' => $directory . '/' . $filename,
                'full_path' => $upload_path,
                'type' => 'video'
            ];
        }

        return ['success' => false, 'message' => 'Failed to upload file'];
    }

    /**
     * Create thumbnail for image
     */
    private static function createThumbnail($source_path, $directory, $max_width = 640, $max_height = 640) {
        $image_info = getimagesize($source_path);
        $width = $image_info[0];
        $height = $image_info[1];
        $mime = $image_info['mime'];

        // Calculate thumbnail dimensions
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);

        // Create image resource based on type
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($source_path);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($source_path);
                break;
            default:
                return null;
        }

        if (!$source) {
            return null;
        }

        // Create thumbnail
        $thumbnail = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNG and GIF
        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $new_width, $new_height, $transparent);
        }

        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        // Save thumbnail
        $thumbnail_filename = 'thumb_' . basename($source_path);
        $thumbnail_path = UPLOAD_DIR . 'thumbnails/' . $thumbnail_filename;

        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($thumbnail, $thumbnail_path, 85);
                break;
            case 'image/png':
                imagepng($thumbnail, $thumbnail_path, 8);
                break;
            case 'image/gif':
                imagegif($thumbnail, $thumbnail_path);
                break;
            case 'image/webp':
                imagewebp($thumbnail, $thumbnail_path, 85);
                break;
        }

        imagedestroy($source);
        imagedestroy($thumbnail);

        return 'thumbnails/' . $thumbnail_filename;
    }

    /**
     * Delete file
     */
    public static function deleteFile($file_path) {
        $full_path = UPLOAD_DIR . $file_path;
        if (file_exists($full_path)) {
            return unlink($full_path);
        }
        return false;
    }

    /**
     * Validate image
     */
    public static function validateImage($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
            return false;
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return false;
        }

        // Verify it's actually an image
        $image_info = @getimagesize($file['tmp_name']);
        return $image_info !== false;
    }

    /**
     * Validate video
     */
    public static function validateVideo($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        if (!in_array($file['type'], ALLOWED_VIDEO_TYPES)) {
            return false;
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return false;
        }

        return true;
    }
}
