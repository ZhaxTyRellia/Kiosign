<?php
require_once __DIR__ . '/db/db.php';
ks_guard_request('web', 45, 10, 30);

$siteTitle            = get_setting('site_title') ?: 'Kiosign • Digital Information Display';
$headerBadge          = get_setting('header_badge') ?: 'LIVE';
$headerCompany        = get_setting('header_company') ?: '';
$headerLogoUrl        = get_setting('header_logo_url', '');
$slideDurationSeconds = (int) get_setting('slide_duration_seconds', 8);
$autoSlideEnabled     = get_setting('auto_slide_enabled', '1') !== '0';
$dateLocale           = get_setting('date_locale', 'en');
$backgroundAudioUrl   = get_setting('background_audio_url');
$defaultAudioVolume   = (float) get_setting('default_audio_volume', 1.0);
$defaultDeviceId      = get_setting('default_device_id');

$deviceId = isset($_GET['id']) && trim($_GET['id']) !== '' ? trim($_GET['id']) : $defaultDeviceId;

$slides = get_slides(true);
$currentVersionHash = get_version_hash();
$bgmPlaylist = get_bgm_playlist();
$firstTrack = !empty($bgmPlaylist) ? $bgmPlaylist[0] : '';

$localeMaps = get_date_locale_maps();
$activeLocaleMap = $localeMaps[$dateLocale] ?? $localeMaps['en'];
$initialClock  = date('H:i:s');
$initialDate   = $activeLocaleMap['days'][(int)date('w')] . ', ' . date('d') . ' ' . $activeLocaleMap['months'][(int)date('n') - 1] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle"><?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo get_kiosign_favicon_data_uri(); ?>">
    <script src="asset/js/tailwind.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="asset/css/style.css">
</head>

<body class="select-none text-white">

    <div id="syncToast" class="pointer-events-none fixed top-5 left-1/2 -translate-x-1/2 z-50 px-4 py-1.5 rounded-full bg-emerald-500/90 text-white text-xs font-bold shadow-xl backdrop-blur transition-all duration-300 opacity-0 -translate-y-2 flex items-center space-x-1.5">
        <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
        <span>Content Updated</span>
    </div>

    <main id="signageStage" class="signage-stage">

        <header class="absolute top-0 left-0 right-0 z-30 flex items-start justify-between px-[clamp(1rem,3vw,3rem)] pt-[clamp(1rem,2.5vh,2.5rem)] pb-[clamp(1.5rem,4vh,3.5rem)] bg-gradient-to-b from-black/90 via-black/50 to-transparent">
            <div class="flex items-center space-x-3 sm:space-x-4">
                <div id="headerLogoContainer" class="w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center shrink-0">
                    <?php if (!empty($headerLogoUrl)): ?>
                        <img id="headerLogoImg" src="<?php echo htmlspecialchars($headerLogoUrl); ?>" alt="Organization Logo" class="w-full h-full object-contain rounded-2xl shadow-xl shadow-black/50 p-1 bg-black/40 backdrop-blur border border-white/10">
                    <?php else: ?>
                        <?php echo get_kiosign_logo_svg('w-10 h-10 sm:w-12 sm:h-12 shadow-xl shadow-black/50 rounded-2xl'); ?>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col space-y-0.5">
                    <div class="flex items-center space-x-2">
                        <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_12px_#34d399]"></div>
                        <span id="headerBadgeText" class="text-[clamp(0.65rem,1vw,0.85rem)] font-extrabold uppercase tracking-[0.2em] text-emerald-400">
                            <?php echo htmlspecialchars($headerBadge); ?>
                        </span>
                    </div>
                    <span id="headerCompanyText" class="text-[clamp(0.6rem,0.9vw,0.75rem)] font-semibold uppercase tracking-widest text-slate-300">
                        <?php echo htmlspecialchars($headerCompany); ?>
                    </span>
                </div>
            </div>

            <div class="text-right bg-black/35 backdrop-blur-md border border-white/10 rounded-2xl px-[clamp(0.75rem,1.5vw,1.25rem)] py-[clamp(0.4rem,1vh,0.75rem)]">
                <div id="liveClock" class="text-[clamp(1.1rem,2.2vw,1.75rem)] font-extrabold tracking-tight text-white font-mono leading-none mb-1">
                    <?php echo $initialClock; ?>
                </div>
                <div id="liveDate" class="text-[clamp(0.6rem,0.85vw,0.75rem)] uppercase tracking-wider text-slate-300 font-semibold">
                    <?php echo $initialDate; ?>
                </div>
            </div>
        </header>

        <div id="slider" class="relative w-full h-full overflow-hidden cursor-grab active:cursor-grabbing">
            <?php if (empty($slides)): ?>
                <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-white/10 flex items-center justify-center mb-4">
                        <?php echo get_icon_svg('monitor', 'w-8 h-8 text-slate-300'); ?>
                    </div>
                    <h2 class="text-2xl font-bold mb-2">No Active Content</h2>
                    <p class="text-slate-400 max-w-md mb-6">Please open the Admin Panel to add or activate display content.</p>
                    <a href="panel/" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 font-bold transition">Open Admin Panel</a>
                </div>
            <?php else: ?>
                <?php foreach ($slides as $index => $slide): ?>
                    <?php
                    $isOfflineWeb = ($slide['type'] ?? 'image') === 'offline_web';
                    $isVideo      = ($slide['type'] ?? 'image') === 'video';
                    $accentColor  = !empty($slide['accent']) ? $slide['accent'] : '#3b82f6';
                    $actionLabel  = !empty($slide['action_label']) ? $slide['action_label'] : ($isOfflineWeb ? 'Interactive Portal' : 'Information Center');
                    $buttonLabel  = !empty($slide['button_label']) ? $slide['button_label'] : ($isOfflineWeb ? 'Open Web Page →' : 'Learn More');
                    $iframeUrl    = htmlspecialchars($slide['iframe_url'] ?? '', ENT_QUOTES);
                    $videoUrl     = htmlspecialchars($slide['video_url'] ?? '', ENT_QUOTES);
                    $slideTitle   = htmlspecialchars($slide['title'] ?? '', ENT_QUOTES);
                    ?>
                    <div class="slide-item absolute inset-0 w-full h-full <?php echo $index === 0 ? 'active' : ''; ?>"
                        data-index="<?php echo $index; ?>">

                        <?php if ($isOfflineWeb): ?>
                            <div class="absolute inset-0 bg-[#070510] flex flex-col pt-[clamp(4.5rem,10vh,7rem)] pb-[clamp(5.5rem,14vh,9rem)] px-[clamp(1rem,3vw,3rem)]">
                                <div class="relative z-30 flex items-center justify-between px-4 py-2.5 rounded-t-2xl bg-[#120d24] border border-b-0 border-purple-500/30">
                                    <div class="flex items-center space-x-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                                        <span class="ml-2 text-[11px] font-mono text-purple-200/90 bg-black/40 px-2.5 py-0.5 rounded border border-white/10">
                                            web://<?php echo $iframeUrl; ?>
                                        </span>
                                    </div>
                                    <button type="button" onclick="openOfflineWebModal('<?php echo $iframeUrl; ?>', '<?php echo $slideTitle; ?>')"
                                        class="text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-lg bg-purple-500/25 hover:bg-purple-500/40 text-purple-200 border border-purple-400/40 transition cursor-pointer inline-flex items-center gap-1">
                                        <span>Fullscreen</span>
                                        <?php echo get_icon_svg('external', 'w-3 h-3'); ?>
                                    </button>
                                </div>

                                <div class="relative z-20 w-full h-[46%] rounded-b-2xl overflow-hidden border border-purple-500/30 shadow-[0_0_50px_rgba(192,132,252,0.2)] bg-black">
                                    <iframe src="<?php echo $iframeUrl; ?>" title="<?php echo $slideTitle; ?>" class="w-full h-full border-0" loading="lazy"></iframe>
                                </div>
                            </div>

                            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-[52%] bg-gradient-to-t from-black via-black/95 to-transparent z-20"></div>
                        <?php elseif ($isVideo && !empty($slide['video_url'])): ?>
                            <div class="absolute inset-0 bg-black"></div>
                            <video src="<?php echo $videoUrl; ?>"
                                autoplay loop muted playsinline
                                class="absolute inset-0 w-full h-full object-cover object-center pointer-events-none">
                            </video>
                            <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-transparent to-black/90"></div>
                            <div class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-black via-black/80 to-transparent"></div>
                        <?php else: ?>
                            <div class="absolute inset-0" style="background: radial-gradient(circle at 50% 30%, <?php echo htmlspecialchars($accentColor); ?>33 0%, #05070f 75%);"></div>

                            <?php if (!empty($slide['image'])): ?>
                                <img src="<?php echo htmlspecialchars($slide['image']); ?>"
                                    alt="<?php echo $slideTitle; ?>"
                                    draggable="false"
                                    class="absolute inset-0 w-full h-full object-cover object-center pointer-events-none">
                            <?php endif; ?>

                            <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-transparent to-black/95"></div>
                            <div class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-black via-black/80 to-transparent"></div>
                        <?php endif; ?>

                        <div class="pointer-events-none relative z-30 flex flex-col justify-end h-full px-[clamp(1rem,3vw,3rem)] pb-[clamp(4.25rem,9vh,6.5rem)] max-w-6xl">
                            <div class="w-12 sm:w-16 h-1.5 rounded-full mb-[clamp(0.5rem,1.5vh,1rem)]"
                                style="background-color: <?php echo htmlspecialchars($accentColor); ?>; box-shadow: 0 0 20px <?php echo htmlspecialchars($accentColor); ?>;">
                            </div>

                            <div class="flex flex-wrap items-center gap-2 mb-[clamp(0.4rem,1.2vh,0.75rem)]">
                                <?php if (!empty($slide['tag'])): ?>
                                    <span class="inline-block px-3 py-1 text-[clamp(0.625rem,0.9vw,0.75rem)] font-extrabold uppercase tracking-wider rounded-lg border bg-black/60 backdrop-blur-md"
                                        style="color: <?php echo htmlspecialchars($accentColor); ?>; border-color: <?php echo htmlspecialchars($accentColor); ?>66;">
                                        <?php echo htmlspecialchars($slide['tag']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($slide['badge'])): ?>
                                    <span class="inline-flex items-center px-3 py-1 text-[clamp(0.625rem,0.9vw,0.75rem)] font-bold rounded-lg bg-white/15 border border-white/15 backdrop-blur-md text-white">
                                        <?php echo htmlspecialchars($slide['badge']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h1 class="text-[clamp(1.5rem,4vw,3.5rem)] font-extrabold tracking-tight leading-[1.1] text-white drop-shadow-lg mb-[clamp(0.4rem,1.2vh,0.75rem)]">
                                <?php echo htmlspecialchars($slide['title'] ?? ''); ?>
                            </h1>

                            <?php if (!empty($slide['subtitle'])): ?>
                                <p class="text-[clamp(0.8rem,1.4vw,1.125rem)] text-slate-200/90 font-normal leading-relaxed mb-[clamp(0.6rem,1.8vh,1rem)] drop-shadow max-w-4xl">
                                    <?php echo htmlspecialchars($slide['subtitle']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($slide['specs']) && is_array($slide['specs'])): ?>
                                <div class="flex flex-wrap gap-2 mb-[clamp(0.6rem,1.8vh,1rem)]">
                                    <?php foreach ($slide['specs'] as $spec): ?>
                                        <span class="text-[clamp(0.625rem,0.9vw,0.75rem)] font-semibold px-2.5 py-1 rounded-md bg-white/10 border border-white/15 text-slate-100 backdrop-blur-sm">
                                            <?php echo htmlspecialchars($spec); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="pointer-events-auto flex items-center justify-between gap-3 p-[clamp(0.65rem,1.5vh,1rem)] rounded-2xl bg-white/[0.07] border border-white/15 backdrop-blur-md">
                                <div class="flex items-center space-x-3 min-w-0">
                                    <div class="w-9 h-9 sm:w-10 sm:h-10 shrink-0 rounded-xl flex items-center justify-center font-extrabold text-xs sm:text-sm"
                                        style="background-color: <?php echo htmlspecialchars($accentColor); ?>22; color: <?php echo htmlspecialchars($accentColor); ?>; border: 1px solid <?php echo htmlspecialchars($accentColor); ?>55;">
                                        0<?php echo $index + 1; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[clamp(0.65rem,1vw,0.8rem)] font-bold uppercase tracking-wider text-white truncate">
                                            <?php echo htmlspecialchars($actionLabel); ?>
                                        </div>
                                        <div class="text-[clamp(0.65rem,0.95vw,0.75rem)] text-slate-300 truncate">
                                            <?php echo htmlspecialchars($slide['badge'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($isOfflineWeb): ?>
                                    <button type="button" onclick="openOfflineWebModal('<?php echo $iframeUrl; ?>', '<?php echo $slideTitle; ?>')"
                                        class="shrink-0 text-[clamp(0.625rem,0.9vw,0.75rem)] font-extrabold uppercase tracking-wider px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white shadow-lg transition cursor-pointer">
                                        <?php echo htmlspecialchars($buttonLabel); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="shrink-0 text-[clamp(0.625rem,0.9vw,0.75rem)] font-bold uppercase tracking-widest px-3 py-1.5 rounded-lg bg-white/10 text-slate-200">
                                        <?php echo htmlspecialchars($buttonLabel); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <footer class="absolute bottom-0 left-0 right-0 z-30 pt-6 bg-gradient-to-t from-black via-black/90 to-transparent">
            <div class="flex flex-wrap items-center justify-between gap-2 px-[clamp(1rem,3vw,3rem)] pb-[clamp(0.75rem,2vh,1.25rem)]">

                <div class="flex items-center space-x-2" id="indicators">
                    <div id="dotsContainer" class="flex items-center space-x-2">
                        <?php foreach ($slides as $index => $slide): ?>
                            <button onclick="goToSlide(<?php echo $index; ?>)"
                                class="indicator-dot h-2.5 rounded-full transition-all duration-300 cursor-pointer <?php echo $index === 0 ? 'w-8 bg-white' : 'w-2.5 bg-white/40 hover:bg-white/70'; ?>"
                                data-index="<?php echo $index; ?>" aria-label="Slide <?php echo $index + 1; ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <div class="text-[11px] uppercase font-bold tracking-wider text-slate-400 bg-white/5 border border-white/10 rounded-full px-3 py-1.5"
                        title="Digital Signage Display ID">
                        ID: <span id="deviceIdText" class="text-slate-200"><?php echo htmlspecialchars($deviceId); ?></span>
                    </div>

                    <a href="panel/" target="_blank"
                        class="text-[11px] uppercase font-bold tracking-wider text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 rounded-full px-3 py-1.5 transition inline-flex items-center gap-1.5"
                        title="Open Admin Dashboard">
                        <?php echo get_icon_svg('settings', 'w-3.5 h-3.5'); ?>
                        <span>Panel</span>
                    </a>
                </div>
            </div>

            <div class="w-full h-1.5 sm:h-2 bg-white/15 overflow-hidden">
                <div id="slideProgress" class="progress-bar-fill h-full bg-white"></div>
            </div>
        </footer>

        <div id="offlineWebModal" class="hidden absolute inset-0 z-50 bg-[#05030a] flex-col">
            <div class="flex items-center justify-between px-6 py-4 bg-[#120d24] border-b border-purple-500/30">
                <div class="flex items-center space-x-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span id="modalTitle" class="text-xs font-extrabold uppercase tracking-wider text-purple-200">
                        Web Page View
                    </span>
                </div>
                <div class="flex items-center space-x-2">
                    <a id="modalNewTab" href="#" target="_blank"
                        class="text-xs font-bold px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-slate-200 transition inline-flex items-center gap-1">
                        <span>New Tab</span>
                        <?php echo get_icon_svg('external', 'w-3 h-3'); ?>
                    </a>
                    <button type="button" onclick="closeOfflineWebModal()"
                        class="text-xs font-extrabold uppercase tracking-wider px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white transition cursor-pointer inline-flex items-center gap-1.5">
                        <?php echo get_icon_svg('close', 'w-3.5 h-3.5'); ?>
                        <span>Close &amp; Resume</span>
                    </button>
                </div>
            </div>
            <iframe id="modalIframe" src="" title="Web Page" class="w-full flex-1 border-0 bg-black"></iframe>
        </div>

    </main>

    <audio id="bgAudio" src="<?php echo htmlspecialchars($firstTrack); ?>" preload="auto"></audio>

    <script>
        window.defaultKiosignLogoHtml = <?php echo json_encode(get_kiosign_logo_svg('w-10 h-10 sm:w-12 sm:h-12 shadow-xl shadow-black/50 rounded-2xl')); ?>;
        window.SIGNAGE_CONFIG = {
            version: "<?php echo $currentVersionHash; ?>",
            apiToken: "<?php echo ks_api_token(); ?>",
            duration: <?php echo (int) $slideDurationSeconds * 1000; ?>,
            autoSlideEnabled: <?php echo $autoSlideEnabled ? 'true' : 'false'; ?>,
            dateLocale: "<?php echo htmlspecialchars($dateLocale, ENT_QUOTES, 'UTF-8'); ?>",
            dateLocaleMaps: <?php echo json_encode($localeMaps, JSON_UNESCAPED_UNICODE); ?>,
            defaultAudioVolume: <?php echo (float) $defaultAudioVolume; ?>,
            bgmPlaylist: <?php echo json_encode($bgmPlaylist); ?>,
            pollIntervalMs: 3000
        };
    </script>
    <script src="asset/js/app.js"></script>
</body>

</html>
