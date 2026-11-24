<?php
// Image upload: EXIF rotation, max 1600px webp, and a 520px phone copy in s/ (used by srcset_attr()).
function save_image(string $tmp, int $max = 1600): ?string {
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return null;
    $dir = 'uploads/' . date('Y/m');
    if (!is_dir(JB_ROOT . "/$dir")) mkdir(JB_ROOT . "/$dir", 0755, true);
    $name = bin2hex(random_bytes(8));
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string)file_get_contents($tmp));
        if ($src) {
            // v2.4: phone photos carry an EXIF rotation; turn the picture the right way up
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $o = (int)(@exif_read_data($tmp)['Orientation'] ?? 1);
                $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
                if ($deg) { $r = imagerotate($src, $deg, 0); if ($r) { imagedestroy($src); $src = $r; } }
            }
            $w = imagesx($src); $h = imagesy($src);
            $s = min(1, $max / max($w, $h));
            $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            if (function_exists('imagewebp')) { $rel = "$dir/$name.webp"; imagewebp($dst, JB_ROOT . "/$rel", 82); }
            else { $rel = "$dir/$name.jpg"; imagejpeg($dst, JB_ROOT . "/$rel", 85); }
            // v2.4: small copy for phones (uploads/Y/m/s/NAME), used by srcset_attr()
            if ($nw > 560) {
                $sw = 520; $sh = max(1, (int)round($nh * $sw / $nw));
                $sm = imagecreatetruecolor($sw, $sh); imagealphablending($sm, false); imagesavealpha($sm, true);
                imagecopyresampled($sm, $dst, 0, 0, 0, 0, $sw, $sh, $nw, $nh);
                if (!is_dir(JB_ROOT . "/$dir/s")) @mkdir(JB_ROOT . "/$dir/s", 0755, true);
                $sp = JB_ROOT . "/$dir/s/" . basename($rel);
                str_ends_with($rel, '.webp') ? imagewebp($sm, $sp, 80) : imagejpeg($sm, $sp, 82);
                imagedestroy($sm);
            }
            imagedestroy($src); imagedestroy($dst);
            return $rel;
        }
    }
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2]];
    $rel = "$dir/$name.$ext";
    return move_uploaded_file($tmp, JB_ROOT . "/$rel") || copy($tmp, JB_ROOT . "/$rel") ? $rel : null;
}
function upload_field(string $field): ?string { $f = $_FILES[$field] ?? null; return ($f && $f['error'] === UPLOAD_ERR_OK) ? save_image($f['tmp_name']) : null; }
