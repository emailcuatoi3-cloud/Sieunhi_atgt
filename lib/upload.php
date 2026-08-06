<?php
declare(strict_types=1);

function validate_review_image(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Tải ảnh lên chưa thành công, thử lại nhé!', 'ext' => null];
    }
    if ((int)$file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Ảnh to quá (tối đa 5MB) 🙈', 'ext' => null];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extByMime[$mime])) {
        return ['ok' => false, 'error' => 'Chỉ nhận ảnh JPEG, PNG hoặc WebP nhé!', 'ext' => null];
    }
    return ['ok' => true, 'error' => null, 'ext' => $extByMime[$mime]];
}
