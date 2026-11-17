<?php
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/seed.php';

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbDir = __DIR__;
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $dbFile = $dbDir . DIRECTORY_SEPARATOR . 'signage.db';
    $isNew = !file_exists($dbFile) || filesize($dbFile) === 0;

    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($isNew) {
        seed_database($pdo, false);
    } else {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('settings', 'slides')")->fetchAll(PDO::FETCH_COLUMN);
        if (count($tables) < 2) {
            seed_database($pdo, false);
        } else {
            $cols = $pdo->query("PRAGMA table_info(slides)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('action_label', $cols)) {
                $pdo->exec("ALTER TABLE slides ADD COLUMN action_label TEXT DEFAULT ''");
            }
            if (!in_array('button_label', $cols)) {
                $pdo->exec("ALTER TABLE slides ADD COLUMN button_label TEXT DEFAULT ''");
            }
            if (!in_array('video_url', $cols)) {
                $pdo->exec("ALTER TABLE slides ADD COLUMN video_url TEXT DEFAULT ''");
            }
        }
    }

    return $pdo;
}

function get_setting(string $key, $default = null)
{
    $db = get_db();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

function set_setting(string $key, $value): bool
{
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (:key, :value) ON CONFLICT(key) DO UPDATE SET value = :value");
    return $stmt->execute([':key' => $key, ':value' => (string) $value]);
}

function get_all_settings(): array
{
    $db = get_db();
    $rows = $db->query("SELECT key, value FROM settings")->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function get_bgm_playlist(): array
{
    $raw = get_setting('bgm_playlist', '');
    if (!empty($raw)) {
        $lines = preg_split('/[\r\n]+/', trim($raw));
        $urls = array_values(array_filter(array_map('trim', $lines)));
        if (!empty($urls)) {
            return $urls;
        }
    }

    $single = get_setting('background_audio_url', '');
    if (!empty($single)) {
        return [$single];
    }

    return [];
}

function get_slides(bool $activeOnly = true): array
{
    $db = get_db();
    $sql = "SELECT * FROM slides";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if (!empty($row['specs'])) {
            $decoded = json_decode($row['specs'], true);
            $row['specs'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['specs'] = [];
        }
    }
    unset($row);

    return $rows;
}

function get_slide(int $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM slides WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) return null;

    if (!empty($row['specs'])) {
        $decoded = json_decode($row['specs'], true);
        $row['specs'] = is_array($decoded) ? $decoded : [];
    } else {
        $row['specs'] = [];
    }
    return $row;
}

function save_slide(array $data, ?int $id = null): int
{
    $db = get_db();

    $specs = $data['specs'] ?? [];
    if (is_array($specs)) {
        $specsJson = json_encode(array_values(array_filter(array_map('trim', $specs))));
    } else {
        $lines = preg_split('/[\r\n,]+/', (string)$specs);
        $cleanLines = array_values(array_filter(array_map('trim', $lines)));
        $specsJson = json_encode($cleanLines);
    }

    $type = in_array($data['type'] ?? '', ['image', 'video', 'offline_web']) ? $data['type'] : 'image';
    $imagePath = ($type === 'image') ? trim($data['image'] ?? '') : '';
    $videoUrl = ($type === 'video') ? trim($data['video_url'] ?? '') : '';

    $params = [
        ':tag' => trim($data['tag'] ?? ''),
        ':title' => trim($data['title'] ?? ''),
        ':subtitle' => trim($data['subtitle'] ?? ''),
        ':badge' => trim($data['badge'] ?? ''),
        ':specs' => $specsJson,
        ':image' => $imagePath,
        ':video_url' => $videoUrl,
        ':accent' => trim($data['accent'] ?? '#3b82f6'),
        ':type' => $type,
        ':iframe_url' => trim($data['iframe_url'] ?? ''),
        ':action_label' => trim($data['action_label'] ?? ''),
        ':button_label' => trim($data['button_label'] ?? ''),
        ':sort_order' => (int) ($data['sort_order'] ?? 0),
        ':is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1
    ];

    if ($id && $id > 0) {
        $params[':id'] = $id;
        $sql = "UPDATE slides SET
                    tag = :tag,
                    title = :title,
                    subtitle = :subtitle,
                    badge = :badge,
                    specs = :specs,
                    image = :image,
                    video_url = :video_url,
                    accent = :accent,
                    type = :type,
                    iframe_url = :iframe_url,
                    action_label = :action_label,
                    button_label = :button_label,
                    sort_order = :sort_order,
                    is_active = :is_active
                WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $id;
    } else {
        if ($params[':sort_order'] === 0) {
            $maxOrder = (int) $db->query("SELECT MAX(sort_order) FROM slides")->fetchColumn();
            $params[':sort_order'] = $maxOrder + 1;
        }

        $sql = "INSERT INTO slides (
                    tag, title, subtitle, badge, specs, image, video_url, accent,
                    type, iframe_url, action_label, button_label, sort_order, is_active
                ) VALUES (
                    :tag, :title, :subtitle, :badge, :specs, :image, :video_url, :accent,
                    :type, :iframe_url, :action_label, :button_label, :sort_order, :is_active
                )";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $db->lastInsertId();
    }
}

function delete_slide(int $id): bool
{
    $db = get_db();
    $stmt = $db->prepare("DELETE FROM slides WHERE id = :id");
    return $stmt->execute([':id' => $id]);
}

function reset_to_seed(): void
{
    $db = get_db();
    seed_database($db, true);
}

function get_version_hash(): string
{
    $db = get_db();
    $slides = $db->query("SELECT id, tag, title, subtitle, badge, specs, image, accent, type, iframe_url, action_label, button_label, sort_order, is_active FROM slides ORDER BY id ASC")->fetchAll();
    $settings = $db->query("SELECT key, value FROM settings ORDER BY key ASC")->fetchAll();
    return md5(json_encode([$slides, $settings]));
}

function get_kiosign_logo_svg(string $class = "w-10 h-10", bool $withBg = true): string
{
    $bg = $withBg ? '
    <rect width="100" height="100" rx="22" fill="#0c1322"/>
    <path d="M-10 28 Q30 45 60 18 T115 35" stroke="#16253b" stroke-width="2.5" fill="none" opacity="0.8"/>
    <path d="M-10 58 Q40 78 80 48 T120 68" stroke="#16253b" stroke-width="2.5" fill="none" opacity="0.8"/>
    <path d="M15 -10 Q50 38 42 78 T58 120" stroke="#16253b" stroke-width="2.5" fill="none" opacity="0.8"/>
    ' : '';

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" class="' . htmlspecialchars($class) . ' inline-block select-none shrink-0" fill="none">'
        . '<defs>'
        . '<filter id="ksShadow" x="-20%" y="-20%" width="140%" height="140%">'
        . '<feDropShadow dx="0" dy="2.5" stdDeviation="2.5" flood-color="#000000" flood-opacity="0.65"/>'
        . '</filter>'
        . '</defs>'
        . $bg
        . '<g filter="url(#ksShadow)">'
        . '<path d="M 22 22 L 34 22 L 34 43 L 53 22 L 67 22 L 44 50 L 67 78 L 53 78 L 34 57 L 34 78 L 22 78 Z" fill="#0d1b2e" stroke="#00c3ff" stroke-width="5" stroke-linejoin="round" stroke-linecap="round"/>'
        . '<path d="M 60 42 A 15 15 0 0 1 60 58" stroke="#fca03b" stroke-width="4.5" stroke-linecap="round" fill="none"/>'
        . '<path d="M 69 36 A 25 25 0 0 1 69 64" stroke="#fca03b" stroke-width="4.5" stroke-linecap="round" fill="none"/>'
        . '<path d="M 78 30 A 35 35 0 0 1 78 70" stroke="#fca03b" stroke-width="4.5" stroke-linecap="round" fill="none"/>'
        . '</g>'
        . '</svg>';
}

function get_kiosign_favicon_data_uri(): string
{
    $svg = get_kiosign_logo_svg('w-full h-full', true);
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

function get_icon_svg(string $name, string $class = 'w-4 h-4'): string
{
    $attrs = 'class="' . htmlspecialchars($class) . ' inline-block shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    $fillAttrs = 'class="' . htmlspecialchars($class) . ' inline-block shrink-0" fill="currentColor" viewBox="0 0 24 24"';
    
    switch ($name) {
        case 'monitor':
        case 'screen':
        case 'slides':
            return '<svg ' . $attrs . '><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>';
            
        case 'folder':
        case 'media':
            return '<svg ' . $attrs . '><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>';
            
        case 'settings':
        case 'gear':
            return '<svg ' . $attrs . '><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
            
        case 'database':
            return '<svg ' . $attrs . '><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg>';
            
        case 'plus':
            return '<svg ' . $attrs . '><path d="M12 5v14"/><path d="M5 12h14"/></svg>';
            
        case 'external':
            return '<svg ' . $attrs . '><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>';
            
        case 'image':
        case 'photo':
            return '<svg ' . $attrs . '><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';
            
        case 'video':
        case 'film':
            return '<svg ' . $attrs . '><polygon points="23 7 16 12 23 17 23 7"/><rect width="15" height="14" x="1" y="5" rx="2" ry="2"/></svg>';
            
        case 'audio':
        case 'music':
            return '<svg ' . $attrs . '><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
            
        case 'globe':
        case 'web':
            return '<svg ' . $attrs . '><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
            
        case 'pencil':
        case 'edit':
            return '<svg ' . $attrs . '><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>';
            
        case 'trash':
            return '<svg ' . $attrs . '><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>';
            
        case 'play':
            return '<svg ' . $fillAttrs . '><polygon points="6 3 20 12 6 21 6 3"/></svg>';
            
        case 'pause':
            return '<svg ' . $fillAttrs . '><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>';
            
        case 'close':
        case 'times':
            return '<svg ' . $attrs . '><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>';
            
        case 'check':
            return '<svg ' . $attrs . '><polyline points="20 6 9 17 4 12"/></svg>';
            
        case 'alert':
        case 'warning':
            return '<svg ' . $attrs . '><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>';
            
        case 'upload':
        case 'cloud-upload':
            return '<svg ' . $attrs . '><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m16 16-4-4-4 4"/></svg>';
            
        default:
            return '<svg ' . $attrs . '><circle cx="12" cy="12" r="10"/></svg>';
    }
}

function get_date_locale_maps(): array
{
    return [
        'en' => [
            'label' => 'English (Sunday, January)',
            'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'months' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
        ],
        'id' => [
            'label' => 'Indonesian (Minggu, Januari)',
            'days' => ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
            'months' => ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
        ],
        'ms' => [
            'label' => 'Malay (Ahad, Januari)',
            'days' => ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'],
            'months' => ['Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember']
        ],
        'ar' => [
            'label' => 'Arabic (الأحد، يناير)',
            'days' => ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
            'months' => ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']
        ],
        'ja' => [
            'label' => 'Japanese (日曜日, 1月)',
            'days' => ['日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日'],
            'months' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月']
        ],
        'ko' => [
            'label' => 'Korean (일요일, 1월)',
            'days' => ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'],
            'months' => ['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월']
        ],
        'zh' => [
            'label' => 'Chinese (星期日, 一月)',
            'days' => ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'],
            'months' => ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月']
        ],
        'fr' => [
            'label' => 'French (Dimanche, Janvier)',
            'days' => ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
            'months' => ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']
        ],
        'de' => [
            'label' => 'German (Sonntag, Januar)',
            'days' => ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
            'months' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']
        ],
        'es' => [
            'label' => 'Spanish (Domingo, Enero)',
            'days' => ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
            'months' => ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
        ],
        'nl' => [
            'label' => 'Dutch (Zondag, Januari)',
            'days' => ['Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag'],
            'months' => ['Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni', 'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December']
        ]
    ];
}
