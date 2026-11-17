<?php
require_once __DIR__ . '/../db/db.php';
ks_guard_request('panel', 35, 10, 30);
ks_init_session();

$message = '';
$messageType = 'success';

function get_media_files(string $subDir): array
{
    $dir = realpath(__DIR__ . '/../asset/' . $subDir);
    if (!$dir || !is_dir($dir)) {
        return [];
    }
    $files = [];
    $scanned = scandir($dir);
    foreach ($scanned as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_file($fullPath)) {
            $files[] = [
                'name' => $item,
                'path' => 'asset/' . $subDir . '/' . $item,
                'size' => filesize($fullPath),
                'mtime' => filemtime($fullPath)
            ];
        }
    }
    usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $files;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ks_verify_csrf()) {
        $message = 'Invalid or expired security token. Please refresh and try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_slide') {
            $id = (int) ($_POST['id'] ?? 0);
            $specsRaw = $_POST['specs'] ?? '';
            $specsArray = [];
            if (!empty($specsRaw)) {
                $lines = preg_split('/[\r\n]+/', trim($specsRaw));
                $specsArray = array_values(array_filter(array_map('trim', $lines)));
            }

            $type = in_array($_POST['type'] ?? '', ['image', 'video', 'offline_web']) ? $_POST['type'] : 'image';
            $imageVal = ($type === 'image') ? trim($_POST['image'] ?? '') : '';
            $videoVal = ($type === 'video') ? trim($_POST['video_url'] ?? '') : '';

            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'subtitle' => trim($_POST['subtitle'] ?? ''),
                'tag' => trim($_POST['tag'] ?? ''),
                'badge' => trim($_POST['badge'] ?? ''),
                'specs' => $specsArray,
                'image' => $imageVal,
                'video_url' => $videoVal,
                'accent' => $_POST['accent'] ?? '#3b82f6',
                'type' => $type,
                'iframe_url' => $_POST['iframe_url'] ?? '',
                'action_label' => $_POST['action_label'] ?? '',
                'button_label' => $_POST['button_label'] ?? '',
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            save_slide($data, $id);
            $message = $id ? 'Content updated successfully!' : 'New content added successfully!';
        } elseif ($action === 'delete_slide') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                delete_slide($id);
                $message = 'Content deleted successfully!';
            }
        } elseif ($action === 'toggle_slide') {
            $id = (int) ($_POST['id'] ?? 0);
            $slide = get_slide($id);
            if ($slide) {
                $slide['is_active'] = $slide['is_active'] ? 0 : 1;
                save_slide($slide, $id);
                $message = $slide['is_active'] ? 'Content activated on display!' : 'Content hidden from display.';
            }
        } elseif ($action === 'upload_media') {
            $mediaType = $_POST['media_type'] ?? 'image';
            $subDir = in_array($mediaType, ['audio', 'video']) ? $mediaType : 'image';

            if ($mediaType === 'audio') {
                $allowed = ['mp3', 'wav', 'ogg', 'm4a'];
                $prefix = 'audio_';
            } elseif ($mediaType === 'video') {
                $allowed = ['mp4', 'webm', 'ogg', 'mov', 'mkv', 'avi'];
                $prefix = 'video_';
            } else {
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
                $prefix = 'img_';
            }

            if (!empty($_FILES['media_file']['name']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $destDir = __DIR__ . '/../asset/' . $subDir;
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

                    $existingFiles = is_dir($destDir) ? scandir($destDir) : [];
                    $maxSeq = 0;
                    foreach ($existingFiles as $f) {
                        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)/', $f, $m)) {
                            $seq = (int) $m[1];
                            if ($seq > $maxSeq) {
                                $maxSeq = $seq;
                            }
                        }
                    }

                    $dbSeq = (int) get_setting('seq_' . $subDir, 0);
                    $nextSeq = max($dbSeq + 1, $maxSeq + 1);
                    set_setting('seq_' . $subDir, (string) $nextSeq);

                    $cleanName = sprintf('%s%03d_%s.%s', $prefix, $nextSeq, date('Ymd_His'), $ext);
                    $destPath = $destDir . DIRECTORY_SEPARATOR . $cleanName;
                    if (file_exists($destPath)) {
                        $cleanName = sprintf('%s%03d_%s_%s.%s', $prefix, $nextSeq, date('Ymd_His'), substr(md5(uniqid()), 0, 4), $ext);
                        $destPath = $destDir . DIRECTORY_SEPARATOR . $cleanName;
                    }

                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $destPath)) {
                        $message = 'File uploaded successfully!';
                        if ($mediaType === 'audio' && isset($_POST['auto_add_playlist'])) {
                            $playlist = get_setting('bgm_playlist', '');
                            $lines = preg_split('/[\r\n]+/', trim($playlist));
                            $newPath = 'asset/audio/' . $cleanName;
                            if (!in_array($newPath, $lines)) {
                                $lines[] = $newPath;
                                set_setting('bgm_playlist', trim(implode("\n", array_filter($lines))));
                            }
                        }
                    } else {
                        $message = 'Failed to save uploaded file.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Unsupported file format.';
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'unlink_media') {
            $mediaType = $_POST['media_type'] ?? 'image';
            $subDir = in_array($mediaType, ['audio', 'video']) ? $mediaType : 'image';
            $filename = basename($_POST['filename'] ?? '');

            $protectedFiles = ['kiosign.png', 'logo.png', 'favicon.ico'];
            if (in_array(strtolower($filename), $protectedFiles)) {
                $message = 'System logo files are protected and cannot be deleted.';
                $messageType = 'error';
            } else {
                $filePath = __DIR__ . '/../asset/' . $subDir . '/' . $filename;
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                    $message = 'Media file deleted successfully!';

                    if ($mediaType === 'audio') {
                        $raw = get_setting('bgm_playlist', '');
                        $lines = preg_split('/[\r\n]+/', trim($raw));
                        $targetPath = 'asset/audio/' . $filename;
                        $filtered = array_filter($lines, fn($line) => trim($line) !== $targetPath);
                        set_setting('bgm_playlist', trim(implode("\n", $filtered)));
                    }
                } else {
                    $message = 'File not found.';
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'add_to_playlist') {
            $filename = basename($_POST['filename'] ?? '');
            $path = 'asset/audio/' . $filename;
            $playlist = get_setting('bgm_playlist', '');
            $lines = preg_split('/[\r\n]+/', trim($playlist));
            if (!in_array($path, $lines)) {
                $lines[] = $path;
                set_setting('bgm_playlist', trim(implode("\n", array_filter($lines))));
                $message = 'Track added to playlist!';
            } else {
                $message = 'Track is already in the playlist.';
                $messageType = 'error';
            }
        } elseif ($action === 'save_settings') {
            if (isset($_POST['has_bgm_form'])) {
                $rawMode = $_POST['bgm_mode'] ?? 'local';
                $bgmMode = ($rawMode === 'url') ? 'url' : 'local';
                set_setting('bgm_mode', $bgmMode);

                if ($bgmMode === 'url') {
                    $externalUrl = trim($_POST['background_audio_url_external'] ?? '');
                    set_setting('background_audio_url', $externalUrl);
                    set_setting('bgm_playlist', $externalUrl);
                } else {
                    $activeBgm = $_POST['active_bgm'] ?? [];
                    if (!is_array($activeBgm)) {
                        $activeBgm = [];
                    }
                    $cleanBgm = array_values(array_filter(array_map('trim', $activeBgm)));
                    set_setting('bgm_playlist', implode("\n", $cleanBgm));
                    set_setting('background_audio_url', !empty($cleanBgm) ? $cleanBgm[0] : '');
                }
            }

            $localeMaps = get_date_locale_maps();
            $postedLocale = $_POST['date_locale'] ?? 'en';
            $dateLocaleVal = array_key_exists($postedLocale, $localeMaps) ? $postedLocale : 'en';

            $fields = [
                'site_title' => $_POST['site_title'] ?? '',
                'header_badge' => $_POST['header_badge'] ?? '',
                'header_company' => $_POST['header_company'] ?? '',
                'header_logo_url' => trim($_POST['header_logo_url'] ?? ''),
                'slide_duration_seconds' => $_POST['slide_duration_seconds'] ?? '8',
                'auto_slide_enabled' => (isset($_POST['auto_slide_enabled']) && $_POST['auto_slide_enabled'] === '0') ? '0' : '1',
                'date_locale' => $dateLocaleVal,
                'default_audio_volume' => $_POST['default_audio_volume'] ?? '1.0',
                'default_device_id' => $_POST['default_device_id'] ?? 'SIGN-01'
            ];
            foreach ($fields as $k => $v) {
                set_setting($k, $v);
            }
            $message = 'Settings saved successfully!';
        } elseif ($action === 'reset_db') {
            reset_to_seed();
            $message = 'System reset to factory defaults.';
        }
    }
}

$allSettings = get_all_settings();
$slides = get_slides(false);

$imageFiles = get_media_files('image');
$audioFiles = get_media_files('audio');
$videoFiles = get_media_files('video');

$editSlide = null;
if (isset($_GET['edit'])) {
    $editSlide = get_slide((int) $_GET['edit']);
}

$validTabsList = ['slides', 'media', 'settings', 'database'];
$activeTab = 'slides';

if (!empty($_POST['_active_tab']) && in_array($_POST['_active_tab'], $validTabsList)) {
    $activeTab = $_POST['_active_tab'];
} elseif (!empty($_COOKIE['kiosign_active_tab']) && in_array($_COOKIE['kiosign_active_tab'], $validTabsList)) {
    $activeTab = $_COOKIE['kiosign_active_tab'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosign • Digital Display Management Panel</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo get_kiosign_favicon_data_uri(); ?>">
    <script src="../asset/js/tailwind.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="bg-slate-100/70 text-slate-800 h-screen overflow-hidden flex flex-col md:flex-row antialiased selection:bg-blue-600 selection:text-white">

    <div id="toastContainer" class="fixed top-5 right-5 z-50 flex flex-col space-y-2 pointer-events-none max-w-sm w-full"></div>

    <div id="sidebarBackdrop" onclick="toggleSidebar(false)"
        class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm hidden md:hidden transition-opacity"></div>

    <aside id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-72 md:w-64 lg:w-72 h-full md:h-screen shrink-0 bg-white border-r border-slate-200/90 shadow-sm flex flex-col justify-between transition-transform duration-300 transform -translate-x-full md:translate-x-0 md:static overflow-y-auto">

        <div>
            <div class="h-16 px-5 flex items-center justify-between border-b border-slate-100 sticky top-0 bg-white/95 backdrop-blur z-10">
                <div class="flex items-center space-x-3">
                    <?php echo get_kiosign_logo_svg('w-10 h-10 rounded-xl shadow-md shadow-blue-500/10'); ?>
                    <div class="min-w-0">
                        <div class="flex items-center space-x-1.5">
                            <h1 class="text-sm font-extrabold text-slate-900 tracking-wide truncate">Kiosign</h1>
                            <span class="px-1.5 py-0.5 bg-blue-50 text-blue-700 text-[9px] font-bold rounded border border-blue-200 uppercase tracking-wider">Official</span>
                        </div>
                    </div>
                </div>

                <button type="button" onclick="toggleSidebar(false)" class="md:hidden text-slate-400 hover:text-slate-700 p-1 rounded-lg">
                    <?php echo get_icon_svg('close', 'w-5 h-5'); ?>
                </button>
            </div>

            <div class="px-3.5 pt-4 pb-1">
                <button type="button" onclick="openNewSlideModal()"
                    class="w-full flex items-center justify-center space-x-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-bold text-white shadow-md shadow-blue-500/25 hover:shadow-lg hover:shadow-blue-500/30 transition-all duration-150">
                    <?php echo get_icon_svg('plus', 'w-4 h-4 text-white'); ?>
                    <span>Add New Content</span>
                </button>
            </div>

            <nav class="p-3.5 space-y-1.5 text-xs font-semibold">
                <button type="button" onclick="switchTab('slides')" id="sideBtnSlides"
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all duration-150 group text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                    <div class="flex items-center space-x-3">
                        <?php echo get_icon_svg('monitor', 'w-4 h-4 text-slate-500 group-hover:text-slate-800'); ?>
                        <span>Content List</span>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200/60">
                        <?php echo count($slides); ?>
                    </span>
                </button>

                <button type="button" onclick="switchTab('media')" id="sideBtnMedia"
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all duration-150 group text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                    <div class="flex items-center space-x-3">
                        <?php echo get_icon_svg('folder', 'w-4 h-4 text-slate-500 group-hover:text-slate-800'); ?>
                        <span>Media Gallery</span>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200/60">
                        <?php echo count($imageFiles) + count($audioFiles) + count($videoFiles); ?>
                    </span>
                </button>

                <button type="button" onclick="switchTab('settings')" id="sideBtnSettings"
                    class="w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition-all duration-150 group text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                    <?php echo get_icon_svg('settings', 'w-4 h-4 text-slate-500 group-hover:text-slate-800'); ?>
                    <span>Display Settings</span>
                </button>

                <button type="button" onclick="switchTab('database')" id="sideBtnDatabase"
                    class="w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition-all duration-150 group text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                    <?php echo get_icon_svg('database', 'w-4 h-4 text-slate-500 group-hover:text-slate-800'); ?>
                    <span>System Information</span>
                </button>
            </nav>
        </div>

        <div class="p-3.5 border-t border-slate-100 space-y-2.5 bg-slate-50/50">
            <a href="../" target="_blank"
                class="w-full flex items-center justify-center space-x-2 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-xs font-bold text-white shadow-sm transition">
                <span>Open Display Screen</span>
                <?php echo get_icon_svg('external', 'w-3.5 h-3.5 text-slate-300'); ?>
            </a>

            <div class="px-1.5 pt-1 border-t border-slate-200/60 text-[10px] text-slate-500 space-y-0.5">
                <div class="flex justify-between items-center text-slate-600 font-semibold">
                    <span>System Status</span>
                    <span class="text-emerald-600 flex items-center space-x-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                        <span>Active</span>
                    </span>
                </div>
            </div>
        </div>

    </aside>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">

        <header class="md:hidden h-14 border-b border-slate-200 bg-white/95 backdrop-blur px-4 flex items-center justify-between shrink-0 z-30 shadow-xs">
            <div class="flex items-center space-x-2.5">
                <button type="button" onclick="toggleSidebar(true)" class="p-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <?php echo get_kiosign_logo_svg('w-7 h-7 rounded-lg'); ?>
                <span class="text-sm font-bold text-slate-900">Kiosign</span>
            </div>
            <a href="../" target="_blank" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs inline-flex items-center gap-1.5">
                <span>Display</span>
                <?php echo get_icon_svg('external', 'w-3 h-3 text-white'); ?>
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 w-full space-y-6">

            <section id="tabSlides" class="space-y-4 w-full">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Screen Content List</h2>
                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Active content will rotate automatically on the main display screen.</p>
                    </div>
                    <button type="button" onclick="openNewSlideModal()"
                        class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-bold text-white transition flex items-center space-x-1.5 shadow-md shadow-blue-500/20">
                        <?php echo get_icon_svg('plus', 'w-3.5 h-3.5 text-white'); ?>
                        <span>Add New Content</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-5 w-full">
                    <?php if (empty($slides)): ?>
                        <div class="col-span-full py-16 text-center rounded-2xl bg-white border border-dashed border-slate-300 shadow-sm flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                                <?php echo get_icon_svg('monitor', 'w-6 h-6 text-slate-400'); ?>
                            </div>
                            <h3 class="text-base font-bold text-slate-800">No Content Yet</h3>
                            <p class="text-xs text-slate-500 mt-1 mb-4">Add content to start displaying information on the screen.</p>
                            <button type="button" onclick="openNewSlideModal()" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-bold text-white shadow-sm inline-flex items-center gap-1.5">
                                <?php echo get_icon_svg('plus', 'w-3.5 h-3.5 text-white'); ?>
                                <span>Create Content</span>
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($slides as $s): ?>
                            <div class="bg-white border <?php echo $s['is_active'] ? 'border-slate-200/90' : 'border-rose-200 bg-rose-50/20 opacity-80'; ?> rounded-2xl overflow-hidden flex flex-col justify-between hover:shadow-xl hover:border-slate-300 transition-all duration-200 group shadow-sm">
                                
                                <div class="relative h-44 bg-slate-100 overflow-hidden border-b border-slate-100 flex items-center justify-center">
                                    <?php if ($s['type'] === 'offline_web'): ?>
                                        <div class="absolute inset-0 flex flex-col items-center justify-center p-4 bg-purple-50 text-purple-700">
                                            <?php echo get_icon_svg('globe', 'w-8 h-8 mb-1 text-purple-600'); ?>
                                            <span class="text-xs font-bold uppercase tracking-wider">Interactive Web Page</span>
                                            <p class="text-[11px] text-purple-600/90 mt-1 truncate max-w-full px-2"><?php echo htmlspecialchars($s['iframe_url'] ?: 'No URL'); ?></p>
                                        </div>
                                    <?php elseif ($s['type'] === 'video' && !empty($s['video_url'])): ?>
                                        <?php $vidSrc = (str_starts_with($s['video_url'], 'http://') || str_starts_with($s['video_url'], 'https://')) ? $s['video_url'] : '../' . $s['video_url']; ?>
                                        <video src="<?php echo htmlspecialchars($vidSrc); ?>" muted loop playsinline class="w-full h-full object-cover"></video>
                                        <div class="absolute inset-0 bg-black/25 pointer-events-none"></div>
                                        <span class="absolute bottom-2 right-2 px-2 py-0.5 rounded text-[10px] font-bold bg-black/75 text-white backdrop-blur inline-flex items-center gap-1">
                                            <?php echo get_icon_svg('video', 'w-3 h-3 text-white'); ?>
                                            <span>Video</span>
                                        </span>
                                    <?php elseif (!empty($s['image'])): ?>
                                        <?php $imgSrc = (str_starts_with($s['image'], 'http://') || str_starts_with($s['image'], 'https://')) ? $s['image'] : '../' . $s['image']; ?>
                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="absolute inset-0 flex items-center justify-center text-slate-400 text-xs font-bold">No Media</div>
                                    <?php endif; ?>

                                    <div class="absolute top-3 left-3 flex items-center space-x-1.5">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-slate-900/80 text-white backdrop-blur shadow-sm">
                                            Order: <?php echo (int) $s['sort_order']; ?>
                                        </span>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase shadow-sm <?php echo $s['is_active'] ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'; ?>">
                                            <?php echo $s['is_active'] ? 'Live' : 'Hidden'; ?>
                                        </span>
                                    </div>

                                    <div class="absolute top-3 right-3">
                                        <div class="w-4 h-4 rounded-full border border-white shadow-sm" style="background-color: <?php echo htmlspecialchars($s['accent'] ?? '#3b82f6'); ?>;"></div>
                                    </div>
                                </div>

                                <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between space-y-3">
                                    <div>
                                        <p class="text-[11px] font-bold text-blue-600 uppercase tracking-wider mb-1 truncate"><?php echo htmlspecialchars($s['tag'] ?: 'General Information'); ?></p>
                                        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-blue-600 transition leading-snug"><?php echo htmlspecialchars($s['title']); ?></h3>
                                        <p class="text-xs text-slate-600 line-clamp-2 mt-1 leading-relaxed"><?php echo htmlspecialchars($s['subtitle']); ?></p>
                                    </div>

                                    <?php if (!empty($s['specs'])): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($s['specs'] as $spec): ?>
                                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200/60">
                                                    <?php echo htmlspecialchars($spec); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-[11px]">
                                        <form method="POST" class="inline">
                                            <?php echo ks_csrf_field(); ?>
                                            <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                                            <input type="hidden" name="action" value="toggle_slide">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" class="font-bold whitespace-nowrap inline-flex items-center gap-1 <?php echo $s['is_active'] ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700'; ?>">
                                                <?php echo $s['is_active'] ? get_icon_svg('pause', 'w-3.5 h-3.5') . ' <span>Hide</span>' : get_icon_svg('play', 'w-3.5 h-3.5') . ' <span>Show</span>'; ?>
                                            </button>
                                        </form>

                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button type="button" onclick='openEditSlideModal(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES, "UTF-8"); ?>)'
                                                class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition shadow-xs inline-flex items-center gap-1 whitespace-nowrap">
                                                <?php echo get_icon_svg('pencil', 'w-3 h-3 text-slate-600'); ?>
                                                <span>Edit</span>
                                            </button>
                                            <form method="POST" onsubmit="return handleFormConfirm(event, this, 'Delete Content', 'Are you sure you want to permanently delete this content?', 'Yes, Delete')" class="inline">
                                                <?php echo ks_csrf_field(); ?>
                                                <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                                                <input type="hidden" name="action" value="delete_slide">
                                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                <button type="submit" class="px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold transition shadow-xs inline-flex items-center gap-1 whitespace-nowrap">
                                                    <?php echo get_icon_svg('trash', 'w-3 h-3 text-rose-600'); ?>
                                                    <span>Delete</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section id="tabMedia" class="hidden space-y-8">
                
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 space-y-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 flex items-center space-x-2">
                                <?php echo get_icon_svg('image', 'w-5 h-5 text-blue-600'); ?>
                                <span>Image Files Gallery</span>
                            </h2>
                            <p class="text-xs text-slate-500">Collection of images and posters available for display content.</p>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="flex items-center space-x-2">
                            <?php echo ks_csrf_field(); ?>
                            <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                            <input type="hidden" name="action" value="upload_media">
                            <input type="hidden" name="media_type" value="image">
                            <input type="file" name="media_file" required accept="image/*"
                                class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                            <button type="submit" class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-xs font-bold text-white transition shadow-sm inline-flex items-center gap-1.5">
                                <?php echo get_icon_svg('upload', 'w-3.5 h-3.5 text-white'); ?>
                                <span>Upload Image</span>
                            </button>
                        </form>
                    </div>

                    <?php if (empty($imageFiles)): ?>
                        <div class="text-center py-8 text-slate-400 text-xs">No image files saved yet.</div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                            <?php foreach ($imageFiles as $img): ?>
                                <div class="bg-white border border-slate-200/90 rounded-xl overflow-hidden group flex flex-col justify-between hover:shadow-md hover:border-slate-300 transition">
                                    <div class="relative h-28 bg-slate-100 overflow-hidden border-b border-slate-100">
                                        <img src="../<?php echo htmlspecialchars($img['path']); ?>" alt="" class="w-full h-full object-cover">
                                        <span class="absolute bottom-1 right-1 px-1.5 py-0.5 rounded text-[9px] bg-slate-900/80 font-mono text-white">
                                            <?php echo round($img['size']/1024, 0); ?> KB
                                        </span>
                                    </div>
                                    <div class="p-2.5 flex-1 flex flex-col justify-between space-y-2">
                                        <p class="text-[11px] font-bold text-slate-800 truncate" title="<?php echo htmlspecialchars($img['name']); ?>">
                                            <?php echo htmlspecialchars($img['name']); ?>
                                        </p>
                                        <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-[10px]">
                                            <button type="button" onclick="useImageInModal('<?php echo htmlspecialchars($img['path']); ?>')"
                                                class="text-blue-600 hover:text-blue-700 font-bold inline-flex items-center gap-1">
                                                <?php echo get_icon_svg('plus', 'w-3 h-3 text-blue-600'); ?>
                                                <span>Use</span>
                                            </button>
                                            <form method="POST" onsubmit="return handleFormConfirm(event, this, 'Delete Image File', 'Are you sure you want to delete this image file?', 'Yes, Delete')" class="inline">
                                                <?php echo ks_csrf_field(); ?>
                                                <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                                                <input type="hidden" name="action" value="unlink_media">
                                                <input type="hidden" name="media_type" value="image">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($img['name']); ?>">
                                                <button type="submit" class="text-rose-600 hover:text-rose-700 font-bold inline-flex items-center gap-1">
                                                    <?php echo get_icon_svg('trash', 'w-3 h-3 text-rose-600'); ?>
                                                    <span>Delete</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 space-y-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 flex items-center space-x-2">
                                <?php echo get_icon_svg('video', 'w-5 h-5 text-purple-600'); ?>
                                <span>Video Files Gallery</span>
                            </h2>
                            <p class="text-xs text-slate-500">Collection of promotional videos and background loops (MP4/WebM).</p>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="flex items-center space-x-2">
                            <?php echo ks_csrf_field(); ?>
                            <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                            <input type="hidden" name="action" value="upload_media">
                            <input type="hidden" name="media_type" value="video">
                            <input type="file" name="media_file" required accept="video/*"
                                class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                            <button type="submit" class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-xs font-bold text-white transition shadow-sm inline-flex items-center gap-1.5">
                                <?php echo get_icon_svg('upload', 'w-3.5 h-3.5 text-white'); ?>
                                <span>Upload Video</span>
                            </button>
                        </form>
                    </div>

                    <?php if (empty($videoFiles)): ?>
                        <div class="text-center py-8 text-slate-400 text-xs">No video files saved yet.</div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <?php foreach ($videoFiles as $vf): ?>
                                <div class="bg-white border border-slate-200/90 rounded-xl overflow-hidden group flex flex-col justify-between hover:shadow-md hover:border-slate-300 transition">
                                    <div class="relative h-32 bg-black overflow-hidden flex items-center justify-center">
                                        <video preload="none" muted class="w-full h-full object-cover">
                                            <source src="../<?php echo htmlspecialchars($vf['path']); ?>" type="video/mp4">
                                        </video>
                                        <div class="absolute inset-0 bg-black/25 flex items-center justify-center pointer-events-none">
                                            <div class="w-10 h-10 rounded-full bg-black/60 text-white flex items-center justify-center backdrop-blur-xs">
                                                <?php echo get_icon_svg('play', 'w-5 h-5 ml-0.5 text-white'); ?>
                                            </div>
                                        </div>
                                        <span class="absolute bottom-1 right-1 px-1.5 py-0.5 rounded text-[9px] bg-black/80 font-mono text-white">
                                            <?php echo round($vf['size'] / (1024*1024), 1); ?> MB
                                        </span>
                                    </div>
                                    <div class="p-2.5 flex-1 flex flex-col justify-between space-y-2">
                                        <p class="text-[11px] font-bold text-slate-800 truncate" title="<?php echo htmlspecialchars($vf['name']); ?>">
                                            <?php echo htmlspecialchars($vf['name']); ?>
                                        </p>
                                        <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-[10px]">
                                            <button type="button" onclick="useVideoInModal('<?php echo htmlspecialchars($vf['path']); ?>')"
                                                class="text-blue-600 hover:text-blue-700 font-bold inline-flex items-center gap-1">
                                                <?php echo get_icon_svg('plus', 'w-3 h-3 text-blue-600'); ?>
                                                <span>Use</span>
                                            </button>
                                            <form method="POST" onsubmit="return handleFormConfirm(event, this, 'Delete Video File', 'Are you sure you want to delete this video file?', 'Yes, Delete')" class="inline">
                                                <?php echo ks_csrf_field(); ?>
                                                <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                                                <input type="hidden" name="action" value="unlink_media">
                                                <input type="hidden" name="media_type" value="video">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($vf['name']); ?>">
                                                <button type="submit" class="text-rose-600 hover:text-rose-700 font-bold inline-flex items-center gap-1">
                                                    <?php echo get_icon_svg('trash', 'w-3 h-3 text-rose-600'); ?>
                                                    <span>Delete</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 space-y-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 flex items-center space-x-2">
                                <?php echo get_icon_svg('audio', 'w-5 h-5 text-emerald-600'); ?>
                                <span>Background Music Library</span>
                            </h2>
                            <p class="text-xs text-slate-500">Manage local audio files played as background music on the display.</p>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                            <?php echo ks_csrf_field(); ?>
                            <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                            <input type="hidden" name="action" value="upload_media">
                            <input type="hidden" name="media_type" value="audio">
                            <input type="file" name="media_file" required accept="audio/*"
                                class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                            <label class="flex items-center space-x-1 text-xs text-slate-600 cursor-pointer">
                                <input type="checkbox" name="auto_add_playlist" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-0">
                                <span>Playlist</span>
                            </label>
                            <button type="submit" class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-xs font-bold text-white transition shadow-sm inline-flex items-center gap-1.5">
                                <?php echo get_icon_svg('upload', 'w-3.5 h-3.5 text-white'); ?>
                                <span>Upload Audio</span>
                            </button>
                        </form>
                    </div>

                    <div class="space-y-3">
                        <?php if (empty($audioFiles)): ?>
                            <div class="text-center py-8 text-slate-400 text-xs">No audio files saved yet.</div>
                        <?php else: ?>
                            <?php foreach ($audioFiles as $af): ?>
                                <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/80 flex flex-wrap items-center justify-between gap-3 hover:border-slate-300 hover:shadow-xs transition">
                                    <div class="flex items-center space-x-3 min-w-0">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">
                                            <?php echo get_icon_svg('audio', 'w-4 h-4 text-emerald-600'); ?>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-slate-900 truncate"><?php echo htmlspecialchars($af['name']); ?></p>
                                            <p class="text-[11px] text-slate-500"><?php echo round($af['size']/1024, 0); ?> KB</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-3">
                                        <audio controls preload="none" class="h-8 max-w-[200px] sm:max-w-[240px]">
                                            <source src="../<?php echo htmlspecialchars($af['path']); ?>" type="audio/mpeg">
                                        </audio>

                                        <form method="POST" class="inline">
                                            <?php echo ks_csrf_field(); ?>
                                            <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                                            <input type="hidden" name="action" value="add_to_playlist">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($af['name']); ?>">
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold transition border border-emerald-200">
                                                + Queue
                                            </button>
                                        </form>

                                        <form method="POST" onsubmit="return handleFormConfirm(event, this, 'Delete Audio File', 'Are you sure you want to delete this audio file?', 'Yes, Delete')" class="inline">
                                            <?php echo ks_csrf_field(); ?>
                                            <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                                            <input type="hidden" name="action" value="unlink_media">
                                            <input type="hidden" name="media_type" value="audio">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($af['name']); ?>">
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold transition border border-rose-200">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </section>

            <section id="tabSettings" class="hidden w-full bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 space-y-6 shadow-sm">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-xl font-bold text-slate-900">Display &amp; Audio Settings</h2>
                    <p class="text-xs text-slate-500">Customize organization branding, slide rotation timing, and background music.</p>
                </div>

                <form method="POST" class="space-y-6">
                    <?php echo ks_csrf_field(); ?>
                    <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Display Application Title</label>
                            <input type="text" name="site_title" value="<?php echo htmlspecialchars($allSettings['site_title'] ?? ''); ?>"
                                placeholder="Display screen title"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Top-Left Status Badge</label>
                            <input type="text" name="header_badge" value="<?php echo htmlspecialchars($allSettings['header_badge'] ?? ''); ?>"
                                placeholder="Status label"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Organization / Company Name</label>
                        <input type="text" name="header_company" value="<?php echo htmlspecialchars($allSettings['header_company'] ?? ''); ?>"
                            placeholder="Organization or company name"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                    </div>

                    <div class="space-y-3 p-5 rounded-2xl bg-slate-50/80 border border-slate-200/80 shadow-xs">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 uppercase">Display Screen Logo</label>
                                <p class="text-[11px] text-slate-500">Choose a custom organization logo shown at the top-left corner of the display screen.</p>
                            </div>
                            <div class="flex items-center space-x-3 text-xs font-medium">
                                <label class="flex items-center space-x-1.5 cursor-pointer">
                                    <input type="radio" id="logoModeGallery" name="logo_source_mode" value="gallery" onchange="toggleLogoSource('gallery')" checked class="text-blue-600 border-slate-300">
                                    <span class="text-slate-700">Select from Gallery</span>
                                </label>
                                <label class="flex items-center space-x-1.5 cursor-pointer">
                                    <input type="radio" id="logoModeUrl" name="logo_source_mode" value="url" onchange="toggleLogoSource('url')" class="text-blue-600 border-slate-300">
                                    <span class="text-slate-700">External Image URL</span>
                                </label>
                            </div>
                        </div>

                        <div id="logoSourceGallery" class="space-y-2">
                            <select id="logoGallerySelect" onchange="selectInstansiLogo(this.value)"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-xs text-slate-900 focus:outline-none shadow-xs">
                                <option value="">-- Use Default Kiosign Logo --</option>
                                <?php foreach ($imageFiles as $img): ?>
                                    <option value="<?php echo htmlspecialchars($img['path']); ?>" <?php echo ($allSettings['header_logo_url'] ?? '') === $img['path'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($img['name']); ?> (<?php echo round($img['size']/1024, 0); ?> KB)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="logoSourceUrl" class="hidden space-y-2">
                            <input type="text" id="logoUrlInput" oninput="updateInstansiLogoPreview(this.value)"
                                value="<?php echo htmlspecialchars($allSettings['header_logo_url'] ?? ''); ?>"
                                placeholder="Image file link (URL)"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-xs text-slate-900 focus:outline-none shadow-xs">
                        </div>

                        <input type="hidden" name="header_logo_url" id="finalHeaderLogoUrl" value="<?php echo htmlspecialchars($allSettings['header_logo_url'] ?? ''); ?>">

                        <div class="flex items-center justify-between p-3.5 rounded-xl bg-white border border-slate-200 shadow-xs">
                            <div class="flex items-center space-x-3.5 min-w-0">
                                <div id="instansiLogoPreviewBox" class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-200 p-1 flex items-center justify-center shrink-0">
                                    <?php if (!empty($allSettings['header_logo_url'])): ?>
                                        <?php $logoSrc = (str_starts_with($allSettings['header_logo_url'], 'http://') || str_starts_with($allSettings['header_logo_url'], 'https://')) ? $allSettings['header_logo_url'] : '../' . $allSettings['header_logo_url']; ?>
                                        <img id="instansiLogoPreviewEl" src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Logo Preview" class="w-full h-full object-contain">
                                    <?php else: ?>
                                        <div id="instansiLogoDefaultEl"><?php echo get_kiosign_logo_svg('w-10 h-10'); ?></div>
                                        <img id="instansiLogoPreviewEl" src="" alt="Logo Preview" class="w-full h-full object-contain hidden">
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[11px] text-slate-500 font-medium">Active Screen Logo:</p>
                                    <p id="instansiLogoPathText" class="text-xs font-bold text-slate-900 truncate mt-0.5">
                                        <?php echo !empty($allSettings['header_logo_url']) ? htmlspecialchars($allSettings['header_logo_url']) : 'Default (Kiosign Built-in Logo)'; ?>
                                    </p>
                                </div>
                            </div>
                            <button type="button" onclick="resetToDefaultLogo()" class="text-xs text-amber-600 hover:text-amber-700 font-bold shrink-0 ml-2">
                                Reset to Default
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Slide Duration (Sec)</label>
                            <input type="number" name="slide_duration_seconds" min="2" max="120"
                                value="<?php echo htmlspecialchars($allSettings['slide_duration_seconds'] ?? '8'); ?>" placeholder="8"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Auto-Slide Rotation</label>
                            <?php $autoSlideVal = $allSettings['auto_slide_enabled'] ?? '1'; ?>
                            <select name="auto_slide_enabled"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                                <option value="1" <?php echo $autoSlideVal !== '0' ? 'selected' : ''; ?>>Active (Auto-Play)</option>
                                <option value="0" <?php echo $autoSlideVal === '0' ? 'selected' : ''; ?>>Paused (Hold Slide)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Audio Volume (0.0 - 1.0)</label>
                            <input type="number" step="0.1" min="0" max="1" name="default_audio_volume"
                                value="<?php echo htmlspecialchars($allSettings['default_audio_volume'] ?? '1.0'); ?>" placeholder="1.0"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Screen ID</label>
                            <input type="text" name="default_device_id"
                                value="<?php echo htmlspecialchars($allSettings['default_device_id'] ?? 'SIGN-01'); ?>" placeholder="Device ID"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Day &amp; Month Language (Display Clock)</label>
                        <?php
                        $currentDateLocale = $allSettings['date_locale'] ?? 'en';
                        $dateLocaleMaps = get_date_locale_maps();
                        ?>
                        <select name="date_locale"
                            class="w-full sm:max-w-md px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                            <?php foreach ($dateLocaleMaps as $code => $info): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $currentDateLocale === $code ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($info['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">Changes only the day and month names displayed on the main screen clock.</p>
                    </div>

                    <div class="space-y-4 p-5 rounded-2xl bg-slate-50/80 border border-slate-200/80 shadow-xs">
                        <input type="hidden" name="has_bgm_form" value="1">
                        
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 pb-3">
                            <div>
                                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Background Music (BGM)</h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Choose your audio source: select multiple local tracks or a single external URL.
                                </p>
                            </div>

                            <?php $currentBgmMode = ($allSettings['bgm_mode'] ?? 'local') === 'local' ? 'local' : 'url'; ?>
                            <div class="flex items-center space-x-3 text-xs font-medium">
                                <label class="flex items-center space-x-1.5 cursor-pointer">
                                    <input type="radio" id="bgmModeLocal" name="bgm_mode" value="local" onchange="toggleBgmMode('local')" <?php echo $currentBgmMode === 'local' ? 'checked' : ''; ?> class="text-blue-600 border-slate-300">
                                    <span class="text-slate-700">Local Audio Files (Multi-Select)</span>
                                </label>
                                <label class="flex items-center space-x-1.5 cursor-pointer">
                                    <input type="radio" id="bgmModeUrl" name="bgm_mode" value="url" onchange="toggleBgmMode('url')" <?php echo $currentBgmMode === 'url' ? 'checked' : ''; ?> class="text-blue-600 border-slate-300">
                                    <span class="text-slate-700">External URL (Single Link)</span>
                                </label>
                            </div>
                        </div>

                        <div id="bgmBlockLocal" class="<?php echo $currentBgmMode === 'url' ? 'hidden' : ''; ?> space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-[11px] text-slate-500">Select tracks to enable. Only checked tracks will play and loop continuously.</p>
                                <?php if (!empty($audioFiles)): ?>
                                    <div class="flex items-center space-x-2 text-xs">
                                        <button type="button" onclick="toggleAllBgm(true)" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium shadow-xs transition">Select All</button>
                                        <button type="button" onclick="toggleAllBgm(false)" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium shadow-xs transition">Clear All</button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (empty($audioFiles)): ?>
                                <div class="py-8 text-center rounded-xl bg-white border border-dashed border-slate-300 flex flex-col items-center justify-center">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center mb-2">
                                        <?php echo get_icon_svg('audio', 'w-5 h-5 text-slate-400'); ?>
                                    </div>
                                    <p class="text-xs font-bold text-slate-800">No music files in local storage yet</p>
                                    <p class="text-[11px] text-slate-500 mt-0.5 mb-3">Upload audio tracks in the Media Gallery tab to select them here.</p>
                                    <button type="button" onclick="switchTab('media')" class="px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-xs font-bold text-white shadow-xs transition">
                                        Open Media Gallery
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php $activeBgmList = get_bgm_playlist(); ?>
                                <div class="overflow-x-auto bg-white rounded-xl border border-slate-200 shadow-xs">
                                    <table class="w-full text-left text-xs">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-600 uppercase font-bold">
                                                <th class="py-2.5 px-3 w-14 text-center">Active</th>
                                                <th class="py-2.5 px-3">Track Filename</th>
                                                <th class="py-2.5 px-3 w-28">Size</th>
                                                <th class="py-2.5 px-3 w-48 text-center">Audio Preview</th>
                                                <th class="py-2.5 px-3 w-24 text-right">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 font-medium">
                                            <?php foreach ($audioFiles as $idx => $af): ?>
                                                <?php $isActive = in_array($af['path'], $activeBgmList); ?>
                                                <tr class="hover:bg-slate-50/80 transition">
                                                    <td class="py-3 px-3 text-center">
                                                        <input type="checkbox" name="active_bgm[]" value="<?php echo htmlspecialchars($af['path']); ?>"
                                                            data-status-id="bgm_status_<?php echo $idx; ?>"
                                                            <?php echo $isActive ? 'checked' : ''; ?>
                                                            onchange="updateBgmRowStatus(this, 'bgm_status_<?php echo $idx; ?>')"
                                                            class="bgm-checkbox w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-0 cursor-pointer">
                                                    </td>
                                                    <td class="py-3 px-3">
                                                        <div class="font-bold text-slate-900 truncate max-w-xs sm:max-w-md" title="<?php echo htmlspecialchars($af['name']); ?>">
                                                            <?php echo htmlspecialchars($af['name']); ?>
                                                        </div>
                                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                            <?php echo htmlspecialchars($af['path']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-3 text-slate-500 font-mono">
                                                        <?php echo round($af['size'] / (1024 * 1024), 2); ?> MB
                                                    </td>
                                                    <td class="py-3 px-3 text-center">
                                                        <audio controls preload="none" class="h-7 w-44 inline-block">
                                                            <source src="../<?php echo htmlspecialchars($af['path']); ?>" type="audio/mpeg">
                                                        </audio>
                                                    </td>
                                                    <td class="py-3 px-3 text-right">
                                                        <span id="bgm_status_<?php echo $idx; ?>" class="px-2 py-0.5 rounded text-[10px] font-bold border <?php echo $isActive ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'; ?>">
                                                            <?php echo $isActive ? 'Playing' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="bgmBlockUrl" class="<?php echo $currentBgmMode === 'url' ? '' : 'hidden'; ?> space-y-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">External Audio URL *Single Link Only</label>
                            <input type="text" name="background_audio_url_external" id="bgmExternalUrlInput"
                                value="<?php echo htmlspecialchars($allSettings['background_audio_url'] ?? ''); ?>"
                                placeholder="https://example.com/audio/bgm-audio.mp3"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                            <p class="text-[11px] text-slate-500">
                                Only one external audio link is permitted. The display will play and loop this audio track continuously.
                            </p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-sm font-bold text-white transition shadow-md shadow-blue-500/25">
                            Save Settings
                        </button>
                    </div>
                </form>
            </section>

            <section id="tabDatabase" class="hidden w-full bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 space-y-6 shadow-sm">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-xl font-bold text-slate-900 flex items-center space-x-2">
                        <?php echo get_icon_svg('database', 'w-5 h-5 text-slate-700'); ?>
                        <span>System Information</span>
                    </h2>
                    <p class="text-xs text-slate-500">Summary of application status and stored data.</p>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="flex justify-between py-2.5 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Application:</span>
                        <span class="font-bold text-slate-900">Kiosign (Digital Signage)</span>
                    </div>
                    <div class="flex justify-between py-2.5 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">License:</span>
                        <span class="font-bold text-slate-900">Gautavaris DigiSoft HUB</span>
                    </div>
                    <div class="flex justify-between py-2.5 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Storage:</span>
                        <span class="font-bold text-slate-900">Local</span>
                    </div>
                    <div class="flex justify-between py-2.5 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">System Status:</span>
                        <span class="font-bold text-emerald-600 inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse"></span> Active &amp; Ready</span>
                    </div>
                    <div class="flex justify-between py-2.5 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Total Image Files:</span>
                        <span class="font-bold text-slate-900"><?php echo count($imageFiles); ?> files</span>
                    </div>
                    <div class="flex justify-between py-2.5 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Total Video Files:</span>
                        <span class="font-bold text-slate-900"><?php echo count($videoFiles); ?> files</span>
                    </div>
                    <div class="flex justify-between py-2.5 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Total Audio Files:</span>
                        <span class="font-bold text-slate-900"><?php echo count($audioFiles); ?> files</span>
                    </div>
                    <div class="flex justify-between py-2.5 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Total Content:</span>
                        <span class="font-bold text-slate-900"><?php echo count($slides); ?> items</span>
                    </div>
                </div>

                <div class="p-5 rounded-2xl bg-rose-50/70 border border-rose-200/80 space-y-3 shadow-xs">
                    <div class="flex items-center space-x-2 text-rose-700 font-bold text-sm">
                        <?php echo get_icon_svg('alert', 'w-4 h-4 text-rose-600'); ?>
                        <span>Reset Settings</span>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Restore all configurations and display content back to factory defaults.
                    </p>
                    <form method="POST" onsubmit="return handleFormConfirm(event, this, 'Reset Settings', 'All configurations and content will be restored to factory defaults. Continue?', 'Yes, Reset')">
                        <?php echo ks_csrf_field(); ?>
                        <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                        <input type="hidden" name="action" value="reset_db">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-xs font-bold text-white transition shadow-md shadow-rose-600/20">
                            Reset Settings
                        </button>
                    </form>
                </div>
            </section>

        </main>
    </div>

    <div id="slideModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5 overflow-y-auto" onclick="handleModalBackdropClick(event)">
        <div class="relative w-full max-w-2xl bg-white border border-slate-200/90 rounded-2xl shadow-2xl p-6 sm:p-7 space-y-5 my-auto max-h-[92vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 id="modalTitle" class="text-lg font-bold text-slate-900">Add New Content</h2>
                    <p id="modalSubtitle" class="text-xs text-slate-500">Configure text details, accent theme, and media selection (image or video only).</p>
                </div>
                <button type="button" onclick="closeSlideModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 p-1.5 rounded-lg transition" title="Close">
                    <?php echo get_icon_svg('close', 'w-5 h-5'); ?>
                </button>
            </div>

            <form method="POST" id="slideModalForm" class="space-y-4">
                <?php echo ks_csrf_field(); ?>
                <input type="hidden" name="_active_tab" class="active-tab-field" value="<?php echo htmlspecialchars($activeTab); ?>">
                <input type="hidden" name="action" value="save_slide">
                <input type="hidden" name="id" id="modalSlideId" value="0">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Content Title *</label>
                        <input type="text" name="title" id="modalTitleInput" required
                            placeholder="Content title"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category / Tag</label>
                        <input type="text" name="tag" id="modalTagInput"
                            placeholder="Content category or tag"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description</label>
                    <textarea name="subtitle" id="modalSubtitleInput" rows="3" placeholder="Detailed content description"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Badge Label</label>
                        <input type="text" name="badge" id="modalBadgeInput"
                            placeholder="Status or badge label"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Theme Color</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" id="accentPicker" value="#3b82f6"
                                onchange="document.getElementById('accentText').value = this.value"
                                class="w-10 h-10 rounded-xl bg-white border border-slate-300 cursor-pointer p-0.5 shadow-xs">
                            <input type="text" id="accentText" name="accent" value="#3b82f6"
                                placeholder="#3b82f6"
                                onchange="document.getElementById('accentPicker').value = this.value"
                                class="flex-1 px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Media Format</label>
                        <select name="type" id="slideTypeSelect" onchange="toggleMediaType(this.value)"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                            <option value="offline_web">Interactive Web Page</option>
                        </select>
                    </div>
                </div>

                <div id="imageInputGroup" class="space-y-3 p-4 rounded-xl bg-slate-50/80 border border-slate-200/80 shadow-xs">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-2.5">
                        <label class="block text-xs font-bold text-slate-800 uppercase">Image Source</label>
                        <div class="flex items-center space-x-4 text-xs font-medium">
                            <label class="flex items-center space-x-1.5 cursor-pointer">
                                <input type="radio" id="radioGallery" name="image_source_mode" value="gallery" onchange="toggleImageSource('gallery')" checked class="text-blue-600 border-slate-300">
                                <span class="text-slate-700">Select from Gallery</span>
                            </label>
                            <label class="flex items-center space-x-1.5 cursor-pointer">
                                <input type="radio" id="radioUrl" name="image_source_mode" value="url" onchange="toggleImageSource('url')" class="text-blue-600 border-slate-300">
                                <span class="text-slate-700">External Image URL</span>
                            </label>
                        </div>
                    </div>

                    <div id="imageSourceGallery" class="space-y-2">
                        <select id="gallerySelect" onchange="selectGalleryImage(this.value)"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-xs text-slate-900 focus:outline-none shadow-xs">
                            <option value="">-- Select Image File --</option>
                            <?php foreach ($imageFiles as $img): ?>
                                <option value="<?php echo htmlspecialchars($img['path']); ?>">
                                    <?php echo htmlspecialchars($img['name']); ?> (<?php echo round($img['size']/1024, 0); ?> KB)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[11px] text-slate-500">Choose from uploaded images in the local gallery.</p>
                    </div>

                    <div id="imageSourceUrl" class="hidden space-y-2">
                        <input type="text" id="imageUrlInput" oninput="updateImagePreview(this.value)"
                            placeholder="Image link (https://... or http://...)"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-xs text-slate-900 focus:outline-none shadow-xs">
                        <p class="text-[11px] text-slate-500">Supports local paths or external image URLs.</p>
                    </div>

                    <input type="hidden" name="image" id="finalImagePathInput" value="">

                    <div id="imagePreviewBox" class="flex items-center space-x-3.5 p-3 rounded-xl bg-white border border-slate-200 shadow-xs hidden">
                        <img id="imagePreviewEl" src="" alt="Preview" class="w-16 h-16 object-cover rounded-lg border border-slate-200 bg-slate-100">
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] text-slate-500 font-medium">Selected Image:</p>
                            <p id="imagePreviewText" class="text-xs font-bold text-slate-900 truncate mt-0.5"></p>
                        </div>
                    </div>
                </div>

                <div id="videoInputGroup" class="space-y-3 p-4 rounded-xl bg-slate-50/80 border border-slate-200/80 shadow-xs hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-2.5">
                        <label class="block text-xs font-bold text-slate-800 uppercase">Video Source</label>
                        <div class="flex items-center space-x-4 text-xs font-medium">
                            <label class="flex items-center space-x-1.5 cursor-pointer">
                                <input type="radio" id="radioVideoGallery" name="video_source_mode" value="gallery" onchange="toggleVideoSource('gallery')" checked class="text-blue-600 border-slate-300">
                                <span class="text-slate-700">Select from Gallery</span>
                            </label>
                            <label class="flex items-center space-x-1.5 cursor-pointer">
                                <input type="radio" id="radioVideoUrl" name="video_source_mode" value="url" onchange="toggleVideoSource('url')" class="text-blue-600 border-slate-300">
                                <span class="text-slate-700">External Video URL</span>
                            </label>
                        </div>
                    </div>

                    <div id="videoSourceGallery" class="space-y-2">
                        <select id="videoGallerySelect" onchange="selectGalleryVideo(this.value)"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-xs text-slate-900 focus:outline-none shadow-xs">
                            <option value="">-- Select Video File --</option>
                            <?php foreach ($videoFiles as $vf): ?>
                                <option value="<?php echo htmlspecialchars($vf['path']); ?>">
                                    <?php echo htmlspecialchars($vf['name']); ?> (<?php echo round($vf['size'] / (1024*1024), 1); ?> MB)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[11px] text-slate-500">Choose from uploaded video files in the local gallery.</p>
                    </div>

                    <div id="videoSourceUrl" class="hidden space-y-2">
                        <input type="text" id="videoUrlInput" oninput="updateVideoPreview(this.value)"
                            placeholder="Video file link (https://.../video.mp4)"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-xs text-slate-900 focus:outline-none shadow-xs">
                        <p class="text-[11px] text-slate-500">Use a direct video stream URL (MP4 / WebM).</p>
                    </div>

                    <input type="hidden" name="video_url" id="finalVideoPathInput" value="">

                    <div id="videoPreviewBox" class="p-3 rounded-xl bg-white border border-slate-200 shadow-xs hidden space-y-2">
                        <p class="text-[11px] text-slate-500 font-medium">Selected Video Preview:</p>
                        <video id="videoPreviewEl" controls class="w-full max-h-48 rounded-lg bg-black object-contain"></video>
                        <p id="videoPreviewText" class="text-xs font-bold text-slate-900 truncate"></p>
                    </div>
                </div>

                <div id="iframeInputGroup" class="hidden">
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Web Page URL</label>
                    <input type="text" name="iframe_url" id="modalIframeUrlInput"
                        placeholder="Interactive web page URL"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Action Title</label>
                        <input type="text" name="action_label" id="modalActionLabelInput"
                            placeholder="Action label"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Button Text</label>
                        <input type="text" name="button_label" id="modalButtonLabelInput"
                            placeholder="Action button text"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Highlights (One per line)</label>
                    <textarea name="specs" id="modalSpecsInput" rows="3" placeholder="Feature or highlight points (one per line)"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 font-mono shadow-xs"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center pt-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Display Order</label>
                        <input type="number" name="sort_order" id="modalSortOrderInput" value="0" placeholder="0"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none text-sm text-slate-900 shadow-xs">
                    </div>

                    <div class="pt-5 flex items-center space-x-2">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="is_active" id="modalIsActiveCheckbox" value="1" checked
                                class="w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-0">
                            <span class="text-sm font-bold text-slate-800">Show on Main Display</span>
                        </label>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                    <button type="button" onclick="closeSlideModal()"
                        class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 transition">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn"
                        class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-sm font-bold text-white transition shadow-md shadow-blue-500/25">
                        Save Content
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="confirmModal" class="fixed inset-0 z-[60] hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4" onclick="if(event.target === this) closeConfirmModal()">
        <div class="w-full max-w-md bg-white border border-slate-200/90 rounded-2xl shadow-2xl p-6 space-y-5 animate-fade-in">
            <div class="flex items-start space-x-4">
                <div class="w-11 h-11 rounded-2xl bg-rose-50 border border-rose-200/80 text-rose-600 flex items-center justify-center shrink-0">
                    <?php echo get_icon_svg('alert', 'w-5 h-5 text-rose-600'); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 id="confirmModalTitle" class="text-base font-bold text-slate-900">Confirm Action</h3>
                    <p id="confirmModalMessage" class="text-xs text-slate-600 mt-1 leading-relaxed">Are you sure you want to proceed?</p>
                </div>
                <button type="button" onclick="closeConfirmModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 p-1 rounded-lg transition" title="Close">
                    <?php echo get_icon_svg('close', 'w-4 h-4'); ?>
                </button>
            </div>
            <div class="flex items-center justify-end space-x-2.5 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeConfirmModal()"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 transition">
                    Cancel
                </button>
                <button type="button" id="confirmModalSubmitBtn" onclick="executeConfirmAction()"
                    class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-xs font-bold text-white transition shadow-md shadow-rose-600/20">
                    Yes, Continue
                </button>
            </div>
        </div>
    </div>

    <script>
        const validTabs = ['slides', 'media', 'settings', 'database'];
        let pendingConfirmForm = null;

        function handleFormConfirm(event, formEl, title, message, confirmLabel = 'Yes, Continue') {
            if (event) event.preventDefault();
            pendingConfirmForm = formEl;
            document.getElementById('confirmModalTitle').textContent = title || 'Confirm Action';
            document.getElementById('confirmModalMessage').textContent = message || 'Are you sure you want to proceed?';
            document.getElementById('confirmModalSubmitBtn').textContent = confirmLabel;
            document.getElementById('confirmModal').classList.remove('hidden');
            return false;
        }

        function closeConfirmModal() {
            pendingConfirmForm = null;
            document.getElementById('confirmModal').classList.add('hidden');
        }

        function executeConfirmAction() {
            if (pendingConfirmForm) {
                const formToSubmit = pendingConfirmForm;
                pendingConfirmForm = null;
                document.getElementById('confirmModal').classList.add('hidden');
                formToSubmit.submit();
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const cm = document.getElementById('confirmModal');
                if (cm && !cm.classList.contains('hidden')) {
                    closeConfirmModal();
                }
            }
        });

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `p-4 rounded-xl border flex items-center justify-between transition-all duration-300 shadow-xl translate-x-8 opacity-0 pointer-events-auto ${
                type === 'error'
                    ? 'bg-white border-rose-200 text-rose-700'
                    : 'bg-white border-slate-200 text-slate-800'
            }`;

            const iconSvg = type === 'error'
                ? '<svg class="w-4 h-4 text-rose-500 shrink-0 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>'
                : '<svg class="w-4 h-4 text-emerald-500 shrink-0 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

            toast.innerHTML = `
                <div class="flex items-center space-x-3 mr-3">
                    <span class="shrink-0">${iconSvg}</span>
                    <span class="text-xs font-semibold leading-relaxed">${message}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg transition" title="Tutup">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
                </button>
            `;

            container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.remove('translate-x-8', 'opacity-0');
                toast.classList.add('translate-x-0', 'opacity-100');
            });

            setTimeout(() => {
                toast.classList.remove('translate-x-0', 'opacity-100');
                toast.classList.add('translate-x-8', 'opacity-0');
                setTimeout(() => toast.remove(), 350);
            }, 3600);
        }

        function switchTab(tab, updateStorage = true) {
            if (!validTabs.includes(tab)) tab = 'slides';

            validTabs.forEach(t => {
                const el = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
                const sideBtn = document.getElementById('sideBtn' + t.charAt(0).toUpperCase() + t.slice(1));
                if (el) el.classList.add('hidden');
                if (sideBtn) {
                    sideBtn.classList.remove('bg-blue-50', 'text-blue-700', 'font-bold', 'border', 'border-blue-200/70', 'shadow-xs');
                    sideBtn.classList.add('text-slate-600', 'hover:bg-slate-100');
                }
            });

            const activeEl = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
            const activeSideBtn = document.getElementById('sideBtn' + tab.charAt(0).toUpperCase() + tab.slice(1));
            if (activeEl) activeEl.classList.remove('hidden');
            if (activeSideBtn) {
                activeSideBtn.classList.remove('text-slate-600', 'hover:bg-slate-100');
                activeSideBtn.classList.add('bg-blue-50', 'text-blue-700', 'font-bold', 'border', 'border-blue-200/70', 'shadow-xs');
            }

            sessionStorage.setItem('kiosign_last_tab', tab);
            localStorage.setItem('kiosign_last_tab', tab);
            document.cookie = "kiosign_active_tab=" + encodeURIComponent(tab) + "; path=/; max-age=2592000; SameSite=Lax";

            document.querySelectorAll('.active-tab-field').forEach(input => {
                input.value = tab;
            });

            if (updateStorage) {
                history.replaceState(null, '', '#' + tab);
            }

            toggleSidebar(false);
        }

        function toggleSidebar(open) {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (!sidebar || !backdrop) return;

            if (open) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        function toggleMediaType(type) {
            const imageGroup = document.getElementById('imageInputGroup');
            const videoGroup = document.getElementById('videoInputGroup');
            const iframeGroup = document.getElementById('iframeInputGroup');

            if (type === 'video') {
                videoGroup.classList.remove('hidden');
                imageGroup.classList.add('hidden');
                iframeGroup.classList.add('hidden');
                document.getElementById('finalImagePathInput').value = '';
            } else if (type === 'offline_web') {
                iframeGroup.classList.remove('hidden');
                imageGroup.classList.add('hidden');
                videoGroup.classList.add('hidden');
                document.getElementById('finalImagePathInput').value = '';
                document.getElementById('finalVideoPathInput').value = '';
            } else {
                imageGroup.classList.remove('hidden');
                videoGroup.classList.add('hidden');
                iframeGroup.classList.add('hidden');
                document.getElementById('finalVideoPathInput').value = '';
            }
        }

        function openNewSlideModal() {
            document.getElementById('modalTitle').textContent = 'Add New Content';
            document.getElementById('modalSubtitle').textContent = 'Configure text details, accent theme, and media selection (image or video only).';
            document.getElementById('modalSubmitBtn').textContent = 'Save New Content';
            document.getElementById('modalSlideId').value = '0';
            document.getElementById('modalTitleInput').value = '';
            document.getElementById('modalTagInput').value = '';
            document.getElementById('modalSubtitleInput').value = '';
            document.getElementById('modalBadgeInput').value = '';
            document.getElementById('accentPicker').value = '#3b82f6';
            document.getElementById('accentText').value = '#3b82f6';
            
            document.getElementById('slideTypeSelect').value = 'image';
            toggleMediaType('image');
            
            document.getElementById('modalIframeUrlInput').value = '';
            document.getElementById('modalActionLabelInput').value = '';
            document.getElementById('modalButtonLabelInput').value = '';
            document.getElementById('modalSpecsInput').value = '';
            document.getElementById('modalSortOrderInput').value = '0';
            document.getElementById('modalIsActiveCheckbox').checked = true;

            document.getElementById('radioGallery').checked = true;
            toggleImageSource('gallery');
            document.getElementById('gallerySelect').value = '';
            document.getElementById('imageUrlInput').value = '';
            updateImagePreview('');

            document.getElementById('radioVideoGallery').checked = true;
            toggleVideoSource('gallery');
            document.getElementById('videoGallerySelect').value = '';
            document.getElementById('videoUrlInput').value = '';
            updateVideoPreview('');

            const modal = document.getElementById('slideModal');
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('modalTitleInput').focus(), 60);
        }

        function openEditSlideModal(slide) {
            if (!slide) return;
            document.getElementById('modalTitle').textContent = 'Edit Content: ' + (slide.title || '');
            document.getElementById('modalSubtitle').textContent = 'Update content configuration and display media.';
            document.getElementById('modalSubmitBtn').textContent = 'Save Changes';
            document.getElementById('modalSlideId').value = slide.id || '0';
            document.getElementById('modalTitleInput').value = slide.title || '';
            document.getElementById('modalTagInput').value = slide.tag || '';
            document.getElementById('modalSubtitleInput').value = slide.subtitle || '';
            document.getElementById('modalBadgeInput').value = slide.badge || '';
            
            const accent = slide.accent || '#3b82f6';
            document.getElementById('accentPicker').value = accent;
            document.getElementById('accentText').value = accent;
            
            const type = slide.type || 'image';
            document.getElementById('slideTypeSelect').value = type;
            toggleMediaType(type);
            
            document.getElementById('modalIframeUrlInput').value = slide.iframe_url || '';
            document.getElementById('modalActionLabelInput').value = slide.action_label || '';
            document.getElementById('modalButtonLabelInput').value = slide.button_label || '';

            let specsStr = '';
            if (slide.specs) {
                if (Array.isArray(slide.specs)) {
                    specsStr = slide.specs.join('\n');
                } else if (typeof slide.specs === 'string') {
                    try {
                        const parsed = JSON.parse(slide.specs);
                        if (Array.isArray(parsed)) {
                            specsStr = parsed.join('\n');
                        } else {
                            specsStr = slide.specs;
                        }
                    } catch(e) {
                        specsStr = slide.specs;
                    }
                }
            }
            document.getElementById('modalSpecsInput').value = specsStr;
            document.getElementById('modalSortOrderInput').value = slide.sort_order || 0;
            document.getElementById('modalIsActiveCheckbox').checked = parseInt(slide.is_active, 10) === 1;

            const imgPath = slide.image || '';
            if (imgPath.startsWith('http://') || imgPath.startsWith('https://')) {
                document.getElementById('radioUrl').checked = true;
                toggleImageSource('url');
                document.getElementById('imageUrlInput').value = imgPath;
            } else {
                document.getElementById('radioGallery').checked = true;
                toggleImageSource('gallery');
                document.getElementById('gallerySelect').value = imgPath;
            }
            updateImagePreview(imgPath);

            const vidPath = slide.video_url || '';
            if (vidPath.startsWith('http://') || vidPath.startsWith('https://')) {
                document.getElementById('radioVideoUrl').checked = true;
                toggleVideoSource('url');
                document.getElementById('videoUrlInput').value = vidPath;
            } else {
                document.getElementById('radioVideoGallery').checked = true;
                toggleVideoSource('gallery');
                document.getElementById('videoGallerySelect').value = vidPath;
            }
            updateVideoPreview(vidPath);

            const modal = document.getElementById('slideModal');
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('modalTitleInput').focus(), 60);
        }

        function closeSlideModal() {
            const modal = document.getElementById('slideModal');
            if (modal) modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function handleModalBackdropClick(e) {
            if (e.target && e.target.id === 'slideModal') {
                closeSlideModal();
            }
        }

        function useImageInModal(path) {
            switchTab('slides');
            openNewSlideModal();
            document.getElementById('slideTypeSelect').value = 'image';
            toggleMediaType('image');
            document.getElementById('radioGallery').checked = true;
            toggleImageSource('gallery');
            document.getElementById('gallerySelect').value = path;
            selectGalleryImage(path);
            showToast('Image selected in content form', 'success');
        }

        function useVideoInModal(path) {
            switchTab('slides');
            openNewSlideModal();
            document.getElementById('slideTypeSelect').value = 'video';
            toggleMediaType('video');
            document.getElementById('radioVideoGallery').checked = true;
            toggleVideoSource('gallery');
            document.getElementById('videoGallerySelect').value = path;
            selectGalleryVideo(path);
            showToast('Video selected in content form', 'success');
        }

        function toggleImageSource(type) {
            const galleryBlock = document.getElementById('imageSourceGallery');
            const urlBlock = document.getElementById('imageSourceUrl');
            const gallerySelect = document.getElementById('gallerySelect');
            const urlInput = document.getElementById('imageUrlInput');
            const finalInput = document.getElementById('finalImagePathInput');

            if (type === 'url') {
                galleryBlock.classList.add('hidden');
                urlBlock.classList.remove('hidden');
                finalInput.value = urlInput.value.trim();
                updateImagePreview(finalInput.value);
            } else {
                urlBlock.classList.add('hidden');
                galleryBlock.classList.remove('hidden');
                finalInput.value = gallerySelect.value;
                updateImagePreview(finalInput.value);
            }
        }

        function selectGalleryImage(path) {
            document.getElementById('finalImagePathInput').value = path;
            updateImagePreview(path);
        }

        function updateImagePreview(path) {
            const box = document.getElementById('imagePreviewBox');
            const img = document.getElementById('imagePreviewEl');
            const txt = document.getElementById('imagePreviewText');
            const finalInput = document.getElementById('finalImagePathInput');

            finalInput.value = (path || '').trim();

            if (!finalInput.value) {
                box.classList.add('hidden');
                return;
            }

            const src = (finalInput.value.startsWith('http://') || finalInput.value.startsWith('https://')) ? finalInput.value : ('../' + finalInput.value);
            img.src = src;
            txt.textContent = finalInput.value;
            box.classList.remove('hidden');
        }

        function toggleVideoSource(type) {
            const galleryBlock = document.getElementById('videoSourceGallery');
            const urlBlock = document.getElementById('videoSourceUrl');
            const gallerySelect = document.getElementById('videoGallerySelect');
            const urlInput = document.getElementById('videoUrlInput');
            const finalInput = document.getElementById('finalVideoPathInput');

            if (type === 'url') {
                galleryBlock.classList.add('hidden');
                urlBlock.classList.remove('hidden');
                finalInput.value = urlInput.value.trim();
                updateVideoPreview(finalInput.value);
            } else {
                urlBlock.classList.add('hidden');
                galleryBlock.classList.remove('hidden');
                finalInput.value = gallerySelect.value;
                updateVideoPreview(finalInput.value);
            }
        }

        function selectGalleryVideo(path) {
            document.getElementById('finalVideoPathInput').value = path;
            updateVideoPreview(path);
        }

        function updateVideoPreview(path) {
            const box = document.getElementById('videoPreviewBox');
            const vid = document.getElementById('videoPreviewEl');
            const txt = document.getElementById('videoPreviewText');
            const finalInput = document.getElementById('finalVideoPathInput');

            finalInput.value = (path || '').trim();

            if (!finalInput.value) {
                box.classList.add('hidden');
                if (vid) vid.removeAttribute('src');
                return;
            }

            const src = (finalInput.value.startsWith('http://') || finalInput.value.startsWith('https://')) ? finalInput.value : ('../' + finalInput.value);
            if (vid) vid.src = src;
            if (txt) txt.textContent = finalInput.value;
            box.classList.remove('hidden');
        }

        function toggleBgmMode(mode) {
            const localBlock = document.getElementById('bgmBlockLocal');
            const urlBlock = document.getElementById('bgmBlockUrl');

            if (mode === 'url') {
                localBlock.classList.add('hidden');
                urlBlock.classList.remove('hidden');
            } else {
                urlBlock.classList.add('hidden');
                localBlock.classList.remove('hidden');
            }
        }

        function toggleAllBgm(check) {
            document.querySelectorAll('.bgm-checkbox').forEach(cb => {
                cb.checked = check;
                const statusId = cb.getAttribute('data-status-id');
                if (statusId) {
                    updateBgmRowStatus(cb, statusId);
                }
            });
        }

        function updateBgmRowStatus(cb, statusId) {
            const el = document.getElementById(statusId);
            if (!el) return;
            if (cb.checked) {
                el.textContent = 'Playing';
                el.className = 'px-2 py-0.5 rounded text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200';
            } else {
                el.textContent = 'Inactive';
                el.className = 'px-2 py-0.5 rounded text-[10px] font-bold border bg-slate-100 text-slate-500 border-slate-200';
            }
        }

        function toggleLogoSource(mode) {
            const galleryBlock = document.getElementById('logoSourceGallery');
            const urlBlock = document.getElementById('logoSourceUrl');
            const gallerySelect = document.getElementById('logoGallerySelect');
            const urlInput = document.getElementById('logoUrlInput');

            if (mode === 'url') {
                galleryBlock.classList.add('hidden');
                urlBlock.classList.remove('hidden');
                updateInstansiLogoPreview(urlInput.value);
            } else {
                urlBlock.classList.add('hidden');
                galleryBlock.classList.remove('hidden');
                updateInstansiLogoPreview(gallerySelect.value);
            }
        }

        function selectInstansiLogo(path) {
            updateInstansiLogoPreview(path);
        }

        function updateInstansiLogoPreview(path) {
            const finalInput = document.getElementById('finalHeaderLogoUrl');
            const img = document.getElementById('instansiLogoPreviewEl');
            const defEl = document.getElementById('instansiLogoDefaultEl');
            const pathText = document.getElementById('instansiLogoPathText');

            finalInput.value = (path || '').trim();

            if (!finalInput.value) {
                if (img) img.classList.add('hidden');
                if (defEl) defEl.classList.remove('hidden');
                pathText.textContent = 'Default (Kiosign Built-in Logo)';
                return;
            }

            const src = (finalInput.value.startsWith('http://') || finalInput.value.startsWith('https://')) ? finalInput.value : ('../' + finalInput.value);
            if (defEl) defEl.classList.add('hidden');
            if (img) {
                img.src = src;
                img.classList.remove('hidden');
            }
            pathText.textContent = finalInput.value;
        }

        function resetToDefaultLogo() {
            document.getElementById('logoGallerySelect').value = '';
            document.getElementById('logoUrlInput').value = '';
            document.getElementById('logoModeGallery').checked = true;
            toggleLogoSource('gallery');
            updateInstansiLogoPreview('');
            showToast('Logo reset to default', 'success');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeSlideModal();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const savedSessionTab = sessionStorage.getItem('kiosign_last_tab') || localStorage.getItem('kiosign_last_tab');

            if (savedSessionTab && validTabs.includes(savedSessionTab)) {
                switchTab(savedSessionTab, true);
            } else if ('<?php echo $activeTab; ?>' && validTabs.includes('<?php echo $activeTab; ?>')) {
                switchTab('<?php echo $activeTab; ?>', true);
            } else {
                switchTab('slides', true);
            }

            <?php if ($editSlide): ?>
                openEditSlideModal(<?php echo json_encode($editSlide, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
            <?php endif; ?>

            const currentLogo = "<?php echo addslashes($allSettings['header_logo_url'] ?? ''); ?>";
            if (currentLogo.startsWith('http://') || currentLogo.startsWith('https://')) {
                document.getElementById('logoModeUrl').checked = true;
                toggleLogoSource('url');
            } else {
                document.getElementById('logoModeGallery').checked = true;
                toggleLogoSource('gallery');
            }

            <?php if (!empty($message)): ?>
                showToast("<?php echo addslashes($message); ?>", "<?php echo $messageType; ?>");
            <?php endif; ?>
        });
    </script>
</body>

</html>
