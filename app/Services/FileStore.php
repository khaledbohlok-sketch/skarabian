<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;

/**
 * Uploads: allowed types only (images, PDF; MP4 for horse videos), size limit, random file names,
 * stored outside the public folder. Images are re-encoded to compressed WebP (which also strips
 * metadata and any embedded payload) with a thumbnail for fast lists.
 */
final class FileStore
{
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    public const DOC_MIMES = ['application/pdf'];
    public const VIDEO_MIMES = ['video/mp4'];
    /** Only these categories may ever be shown on the public website (never documents, certificates, receipts...). */
    public const PUBLIC_CATEGORIES = ['photo', 'video'];

    /** owner type => [module, upload actions, categories, sensitive categories] */
    public const OWNERS = [
        'horse'    => ['horses', ['horses.edit', 'horse_notes.create', 'horse_health.create'], ['photo', 'video', 'document', 'certificate'], []],
        'embryo'   => ['embryos', ['embryos.edit'], ['photo', 'document'], []],
        'employee' => ['hr', ['hr.edit'], ['photo', 'document', 'contract', 'id_copy'], ['contract', 'id_copy']],
        'bill'     => ['finance', ['finance.create', 'finance.edit'], ['receipt', 'document'], []],
        'party'    => ['finance', ['finance.edit'], ['document'], []],
        'invoice'  => ['finance', ['finance.edit'], ['document'], []],
        'purchase_order' => ['finance', ['finance.edit'], ['document'], []],
        'news'     => ['cms', ['cms.edit', 'cms.create'], ['photo'], []],
        'cms'      => ['cms', ['cms.edit'], ['photo', 'video'], []],
        'show'     => ['horse_training', ['horse_training.edit', 'cms.edit'], ['photo', 'document'], []],
        'breeding' => ['horse_breeding', ['horse_breeding.edit', 'horse_breeding.create'], ['document', 'photo'], []],
        'document' => ['documents', ['documents.create'], ['document'], []],
    ];

    public static function canUpload(string $ownerType): bool
    {
        foreach (self::OWNERS[$ownerType][1] ?? [] as $perm) {
            [$m, $a] = explode('.', $perm);
            if (Auth::can($m, $a)) {
                return true;
            }
        }
        return false;
    }

    public static function canView(array $file): bool
    {
        if (!Auth::check()) {
            return false;
        }
        $o = self::OWNERS[$file['owner_type']] ?? null;
        if (!$o) {
            return Auth::isOwner();
        }
        if (!Auth::can($o[0], 'view') && !self::canUpload($file['owner_type'])) {
            return false;
        }
        if ($file['owner_type'] === 'horse' && !Auth::canSeeHorse((int) $file['owner_id'])) {
            return false;
        }
        if ($file['is_sensitive'] && !Auth::can($o[0], 'sensitive')) {
            return false;
        }
        // the files of a deleted record (in the Trash) are for the Owner only
        if (!Auth::isOwner() && self::ownerDeleted($file)) {
            return false;
        }
        return true;
    }

    /** owner type => its table (records that go to the Trash). */
    private const OWNER_TABLES = [
        'horse' => 'horses', 'embryo' => 'embryos', 'employee' => 'employees', 'bill' => 'bills', 'party' => 'parties', 'invoice' => 'invoices',
        'purchase_order' => 'purchase_orders', 'news' => 'news', 'show' => 'shows', 'breeding' => 'breeding_records', 'document' => 'media_documents',
    ];
    private static array $ownerDeleted = [];

    private static function ownerDeleted(array $file): bool
    {
        $table = self::OWNER_TABLES[$file['owner_type']] ?? null;
        if ($table === null) {
            return false;
        }
        return self::$ownerDeleted[$file['owner_type'] . ':' . $file['owner_id']]
            ??= (bool) DB::value("SELECT deleted_at IS NOT NULL FROM `$table` WHERE id = ?", [$file['owner_id']]);
    }

    /** Removes a file's stored copies (the file and its thumbnail) from the disk, once its row was deleted for good. */
    public static function removeStored(array $file): void
    {
        foreach ([(string) $file['stored_name'], (string) ($file['thumb_name'] ?? '')] as $name) {
            if ($name !== '' && !str_contains($name, '..') && is_file(self::path($name))
                && !DB::value('SELECT 1 FROM files WHERE stored_name = ? OR thumb_name = ? LIMIT 1', [$name, $name])) {
                @unlink(self::path($name));
            }
        }
    }

    /** Whether the file may be served on the public website (no login). */
    public static function isPublic(array $file): bool
    {
        if (!$file['is_public'] || $file['deleted_at'] !== null || $file['is_sensitive'] || !in_array($file['category'], self::PUBLIC_CATEGORIES, true)) {
            return false;
        }
        return match ($file['owner_type']) {
            'horse'  => (bool) DB::value('SELECT show_on_website FROM horses WHERE id = ? AND deleted_at IS NULL', [$file['owner_id']]),
            'employee' => (bool) DB::value('SELECT show_on_website FROM employees WHERE id = ? AND deleted_at IS NULL', [$file['owner_id']]) && $file['category'] === 'photo',
            'news', 'cms', 'show' => true,
            default  => false,
        };
    }

    public static function path(string $stored): string
    {
        return Config::storagePath('uploads/' . $stored);
    }

    /**
     * The picture shown for a file that has no thumbnail (a PDF, a video) or is not on the server any more: a page with
     * its type (PDF, MP4…) on a coloured band, greyed and dashed when the file is missing. 4:3, with the page in the
     * middle square, so it fits both the square list thumbnails and the 4:3 grid tiles.
     */
    public static function typeIcon(string $mime, string $name, bool $missing = false): string
    {
        $ext = strtoupper((string) pathinfo($name, PATHINFO_EXTENSION));
        [$label, $color] = match (true) {
            $mime === 'application/pdf' => ['PDF', '#D93025'],
            str_starts_with($mime, 'video/') => [$ext !== '' && strlen($ext) <= 4 ? $ext : 'VIDEO', '#6741D9'],
            str_starts_with($mime, 'image/') => [$ext !== '' && strlen($ext) <= 4 ? $ext : 'IMG', '#0B7285'],
            default => [preg_match('/^[A-Z0-9]{1,4}$/', $ext) ? $ext : 'FILE', '#495057'],
        };
        if ($missing) {
            $color = '#ADB5BD';
        }
        $line = $missing ? '#ADB5BD' : '#C3CAD7';
        $dash = $missing ? ' stroke-dasharray="5 4"' : '';
        $inside = str_starts_with($mime, 'video/')
            ? '<path d="M58 30l15 10-15 10z" fill="' . $line . '"/>'
            : '<path d="M50 34h20M50 41h28M50 48h28" stroke="#E1E5EC" stroke-width="3" stroke-linecap="round"/>';
        // no background of its own: the tile sits on its frame's colour (light or dark design)
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 96" width="128" height="96">'
            . '<path d="M47 12h24l14 14v54a4 4 0 0 1-4 4H47a4 4 0 0 1-4-4V16a4 4 0 0 1 4-4z" fill="#fff" stroke="' . $line . '" stroke-width="2"' . $dash . '/>'
            . '<path d="M71 12v10a4 4 0 0 0 4 4h10" fill="none" stroke="' . $line . '" stroke-width="2"' . $dash . '/>'
            . $inside
            . '<rect x="31" y="52" width="56" height="24" rx="5" fill="' . $color . '"/>'
            . '<text x="59" y="69.5" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="' . (strlen($label) > 3 ? 13 : 15) . '" font-weight="700" letter-spacing=".3" fill="#fff">' . htmlspecialchars($label, ENT_XML1) . '</text>'
            . '</svg>';
    }

    /** Stores one uploaded file ($_FILES entry). Returns the new file id. */
    public static function store(array $upload, string $ownerType, int $ownerId, string $category, ?string $title = null, bool $public = false): int
    {
        if (!isset(self::OWNERS[$ownerType]) || !in_array($category, self::OWNERS[$ownerType][2], true)) {
            throw new \DomainException(__('files.bad_category'));
        }
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name']) && PHP_SAPI !== 'cli') {
            throw new \DomainException(__('files.upload_failed'));
        }
        $max = (int) Config::get('security.upload_max_mb', 15) * 1024 * 1024;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']) ?: '';
        $isVideo = in_array($mime, self::VIDEO_MIMES, true);
        if ($isVideo && $category !== 'video') {
            throw new \DomainException(__('files.bad_type'));
        }
        if ($upload['size'] > ($isVideo ? $max * 4 : $max)) {
            throw new \DomainException(__('files.too_big', ['mb' => (int) (($isVideo ? $max * 4 : $max) / 1048576)]));
        }
        if (!in_array($mime, array_merge(self::IMAGE_MIMES, self::DOC_MIMES, self::VIDEO_MIMES), true)) {
            throw new \DomainException(__('files.bad_type'));
        }
        if ($category === 'photo' && !in_array($mime, self::IMAGE_MIMES, true)) {
            throw new \DomainException(__('files.photo_only'));
        }
        $dir = date('Y/m');
        @mkdir(Config::storagePath('uploads/' . $dir), 0750, true);
        $base = $dir . '/' . bin2hex(random_bytes(16));
        $width = $height = null;
        $thumb = null;
        if (in_array($mime, self::IMAGE_MIMES, true)) {
            [$stored, $width, $height, $thumb] = self::processImage($upload['tmp_name'], $base);
            $mime = 'image/webp';
            $size = filesize(self::path($stored));
        } else {
            $ext = $isVideo ? '.mp4' : '.pdf';
            if ($mime === 'application/pdf' && !str_starts_with((string) file_get_contents($upload['tmp_name'], false, null, 0, 5), '%PDF-')) {
                throw new \DomainException(__('files.bad_type'));
            }
            $stored = $base . $ext;
            $ok = PHP_SAPI === 'cli' ? copy($upload['tmp_name'], self::path($stored)) : move_uploaded_file($upload['tmp_name'], self::path($stored));
            if (!$ok) {
                throw new \DomainException(__('files.upload_failed'));
            }
            $size = (int) $upload['size'];
        }
        $sensitive = in_array($category, self::OWNERS[$ownerType][3], true);
        $id = DB::insert('files', [
            'owner_type' => $ownerType, 'owner_id' => $ownerId, 'category' => $category,
            'title' => $title !== null ? mb_substr($title, 0, 200) : null,
            'original_name' => mb_substr(basename((string) $upload['name']), 0, 255), 'stored_name' => $stored, 'thumb_name' => $thumb,
            'mime' => $mime, 'size_bytes' => $size, 'width' => $width, 'height' => $height,
            'is_sensitive' => $sensitive ? 1 : 0, 'is_public' => ($public && !$sensitive && in_array($category, self::PUBLIC_CATEGORIES, true)) ? 1 : 0, 'uploaded_by' => Auth::id(),
        ]);
        Audit::log('upload', self::OWNERS[$ownerType][0], $ownerType, $ownerId, null, ['file_id' => $id, 'category' => $category, 'name' => $upload['name']], 'File uploaded');
        return $id;
    }

    /** @return array{0:string,1:int,2:int,3:string} stored name, width, height, thumb name */
    private static function processImage(string $tmp, string $base): array
    {
        $info = @getimagesize($tmp);
        if (!$info) {
            throw new \DomainException(__('files.bad_type'));
        }
        if ($info[0] * $info[1] > 50_000_000) {
            throw new \DomainException(__('files.too_many_pixels', ['mp' => 50]));
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
            IMAGETYPE_GIF  => @imagecreatefromgif($tmp),
            default        => false,
        };
        if (!$src) {
            throw new \DomainException(__('files.bad_type'));
        }
        // Phone photos: respect the camera orientation
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $o = (int) ($exif['Orientation'] ?? 1);
            $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot) {
                $src = imagerotate($src, $rot, 0);
            }
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $main = self::resize($src, 2000);
        $stored = $base . '.webp';
        imagewebp($main, self::path($stored), 82);
        $t = self::resize($src, 480);
        $thumb = $base . '_t.webp';
        imagewebp($t, self::path($thumb), 75);
        $mw = imagesx($main);
        $mh = imagesy($main);
        imagedestroy($src);
        return [$stored, $mw, $mh, $thumb];
    }

    private static function resize(\GdImage $src, int $max): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    public static function forOwner(string $type, int $id, ?array $categories = null): array
    {
        $params = ['ot' => $type, 'oid' => $id];
        $sql = 'SELECT * FROM files WHERE owner_type = :ot AND owner_id = :oid AND deleted_at IS NULL';
        if ($categories) {
            $sql .= ' AND category IN ' . DB::in($categories, 'c', $params);
        }
        $rows = DB::all($sql . ' ORDER BY sort_order, id DESC', $params);
        return array_values(array_filter($rows, [self::class, 'canView']));
    }
}
