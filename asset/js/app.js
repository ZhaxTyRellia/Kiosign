const config = window.SIGNAGE_CONFIG || {};
let duration = parseInt(config.duration || 8000, 10);
let defaultAudioVolume = parseFloat(config.defaultAudioVolume !== undefined ? config.defaultAudioVolume : 1.0);
let currentVersion = config.version || '';
let apiToken = config.apiToken || '';
let dateLocale = config.dateLocale || 'en';
const dateLocaleMaps = config.dateLocaleMaps || {};
const pollIntervalMs = parseInt(config.pollIntervalMs || 3000, 10);

const sliderEl = document.getElementById('slider');
const dotsContainer = document.getElementById('dotsContainer');
const progressBar = document.getElementById('slideProgress');
const autoSlideToggleBtn = document.getElementById('autoSlideToggleBtn');
const syncToast = document.getElementById('syncToast');

let slides = document.querySelectorAll('.slide-item');
let indicators = document.querySelectorAll('.indicator-dot');
let totalSlides = slides.length;

let currentIndex = 0;
let slideTimer = null;
let isAutoSlidePaused = config.autoSlideEnabled === false;
let isCheckingUpdate = false;

let pendingSlideUpdate = null;
let pendingBgmPlaylist = null;

let bgmPlaylist = Array.isArray(config.bgmPlaylist) ? config.bgmPlaylist : [];
let currentTrackIndex = 0;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function updateClock() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    const clockEl = document.getElementById('liveClock');
    if (clockEl) {
        clockEl.textContent = `${hours}:${minutes}:${seconds}`;
    }

    const dateEl = document.getElementById('liveDate');
    if (dateEl) {
        const map = dateLocaleMaps[dateLocale] || dateLocaleMaps['en'];
        if (map && Array.isArray(map.days) && Array.isArray(map.months)) {
            const dayName = map.days[now.getDay()] || '';
            const monthName = map.months[now.getMonth()] || '';
            const dayNum = String(now.getDate()).padStart(2, '0');
            dateEl.textContent = `${dayName}, ${dayNum} ${monthName} ${now.getFullYear()}`;
        } else {
            const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
            dateEl.textContent = now.toLocaleDateString('en-US', options);
        }
    }
}

function resetProgress() {
    if (!progressBar) return;
    progressBar.style.transitionDuration = '0ms';
    progressBar.style.width = '0%';

    void progressBar.offsetWidth;

    if (!isAutoSlidePaused) {
        progressBar.style.transitionDuration = `${duration}ms`;
        progressBar.style.width = '100%';
    }
}

function showSlide(index) {
    if (totalSlides === 0) return;

    slides.forEach((slide, i) => {
        slide.classList.toggle('active', i === index);
    });

    indicators.forEach((dot, i) => {
        if (i === index) {
            dot.classList.remove('w-2.5', 'bg-white/40', 'hover:bg-white/70');
            dot.classList.add('w-8', 'bg-white');
        } else {
            dot.classList.remove('w-8', 'bg-white');
            dot.classList.add('w-2.5', 'bg-white/40', 'hover:bg-white/70');
        }
    });

    currentIndex = index;
    resetProgress();
}

function nextSlide() {
    if (totalSlides === 0) return;

    const next = (currentIndex + 1) % totalSlides;

    if (next === 0 && pendingSlideUpdate) {
        const dataToApply = pendingSlideUpdate;
        pendingSlideUpdate = null;
        applyLiveUpdate(dataToApply);
        return;
    }

    showSlide(next);
}

function prevSlide() {
    if (totalSlides === 0) return;
    const prev = (currentIndex - 1 + totalSlides) % totalSlides;
    showSlide(prev);
}

function nextSlideManual() {
    clearInterval(slideTimer);
    nextSlide();
    if (!isAutoSlidePaused) startAutoSlide();
}

function prevSlideManual() {
    clearInterval(slideTimer);
    prevSlide();
    if (!isAutoSlidePaused) startAutoSlide();
}

function goToSlide(index) {
    clearInterval(slideTimer);
    showSlide(index);
    if (!isAutoSlidePaused) startAutoSlide();
}

function startAutoSlide() {
    clearInterval(slideTimer);
    if (!isAutoSlidePaused && totalSlides > 1) {
        slideTimer = setInterval(nextSlide, duration);
    }
}

function toggleAutoSlide() {
    isAutoSlidePaused = !isAutoSlidePaused;
    if (isAutoSlidePaused) {
        clearInterval(slideTimer);
        if (progressBar) {
            progressBar.style.transitionDuration = '0ms';
            progressBar.style.width = '0%';
        }
        if (autoSlideToggleBtn) {
            autoSlideToggleBtn.textContent = 'AUTO: PAUSE';
            autoSlideToggleBtn.classList.replace('text-emerald-400', 'text-amber-400');
        }
    } else {
        if (autoSlideToggleBtn) {
            autoSlideToggleBtn.textContent = 'AUTO: ON';
            autoSlideToggleBtn.classList.replace('text-amber-400', 'text-emerald-400');
        }
        resetProgress();
        startAutoSlide();
    }
}

const offlineWebModal = document.getElementById('offlineWebModal');
const modalIframe = document.getElementById('modalIframe');
const modalTitle = document.getElementById('modalTitle');
const modalNewTab = document.getElementById('modalNewTab');

function openOfflineWebModal(url, title) {
    if (!offlineWebModal) return;
    clearInterval(slideTimer);
    if (progressBar) progressBar.style.transitionDuration = '0ms';

    if (url && modalIframe) modalIframe.src = url;
    if (title && modalTitle) modalTitle.textContent = title;
    if (url && modalNewTab) modalNewTab.href = url;

    offlineWebModal.classList.remove('hidden');
    offlineWebModal.classList.add('flex');
}

function closeOfflineWebModal() {
    if (!offlineWebModal) return;
    offlineWebModal.classList.add('hidden');
    offlineWebModal.classList.remove('flex');
    if (modalIframe) modalIframe.src = '';
    resetProgress();
    startAutoSlide();
}

const bgAudio = document.getElementById('bgAudio');
const eqIcon = document.getElementById('eqIcon');
const audioStatusText = document.getElementById('audioStatusText');
const audioToggleBtn = document.getElementById('audioToggleBtn');
let isAudioPlaying = false;

if (bgAudio) {
    bgAudio.volume = defaultAudioVolume;
}

function updateAudioUI(playing) {
    isAudioPlaying = playing;
    if (!bgAudio) return;

    if (playing && !bgAudio.muted && bgAudio.volume > 0) {
        if (eqIcon) eqIcon.classList.add('eq-playing');
        if (audioStatusText) {
            const trackNum = bgmPlaylist.length > 1 ? ` (${currentTrackIndex + 1}/${bgmPlaylist.length})` : '';
            audioStatusText.textContent = `BGM: ON${trackNum}`;
            audioStatusText.classList.replace('text-slate-400', 'text-white');
        }
    } else {
        if (eqIcon) eqIcon.classList.remove('eq-playing');
        if (audioStatusText) {
            audioStatusText.textContent = bgAudio.muted || bgAudio.volume === 0 ? 'BGM: MUTE' : 'BGM: OFF';
        }
    }
}

function playTrack(index) {
    if (!bgAudio) return;

    if (pendingBgmPlaylist !== null) {
        bgmPlaylist = pendingBgmPlaylist;
        pendingBgmPlaylist = null;
        currentTrackIndex = 0;
        index = 0;
    }

    if (!bgmPlaylist || bgmPlaylist.length === 0) {
        bgAudio.pause();
        bgAudio.removeAttribute('src');
        if (audioToggleBtn) audioToggleBtn.classList.add('hidden');
        updateAudioUI(false);
        return;
    }

    if (audioToggleBtn) audioToggleBtn.classList.remove('hidden');

    currentTrackIndex = index % bgmPlaylist.length;
    const trackUrl = bgmPlaylist[currentTrackIndex];

    if (bgAudio.src !== trackUrl) {
        bgAudio.src = trackUrl;
        bgAudio.load();
    }

    bgAudio.volume = defaultAudioVolume;
    bgAudio.loop = bgmPlaylist.length === 1;

    const playPromise = bgAudio.play();
    if (playPromise !== undefined) {
        playPromise.then(() => {
            updateAudioUI(true);
        }).catch(() => {
            updateAudioUI(false);
        });
    }
}

function playNextTrack() {
    playTrack(currentTrackIndex + 1);
}

if (bgAudio) {
    bgAudio.addEventListener('ended', () => {
        playNextTrack();
    });
}

function toggleAudio() {
    if (!bgAudio || !bgAudio.src) return;

    if (bgAudio.paused) {
        bgAudio.muted = false;
        bgAudio.play().then(() => {
            updateAudioUI(true);
        }).catch(() => { });
    } else {
        bgAudio.muted = !bgAudio.muted;
        updateAudioUI(!bgAudio.muted);
    }
}

function initAutoplay() {
    if (!bgAudio || bgmPlaylist.length === 0) return;

    bgAudio.muted = false;
    bgAudio.volume = defaultAudioVolume;

    const tryPlay = () => {
        playTrack(currentTrackIndex);
    };

    const unlockAudio = () => {
        if (bgAudio.paused) {
            bgAudio.muted = false;
            bgAudio.play().then(() => updateAudioUI(true)).catch(() => { });
        }
        document.removeEventListener('click', unlockAudio);
        document.removeEventListener('touchstart', unlockAudio);
        document.removeEventListener('keydown', unlockAudio);
    };

    document.addEventListener('click', unlockAudio);
    document.addEventListener('touchstart', unlockAudio);
    document.addEventListener('keydown', unlockAudio);

    tryPlay();
}

let pointerStartX = 0;
let pointerStartY = 0;
let isDragging = false;

if (sliderEl) {
    sliderEl.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
            pointerStartX = e.touches[0].clientX;
            pointerStartY = e.touches[0].clientY;
            isDragging = true;
        }
    }, { passive: true });

    sliderEl.addEventListener('touchend', (e) => {
        if (!isDragging || e.changedTouches.length === 0) return;
        isDragging = false;
        const deltaX = e.changedTouches[0].clientX - pointerStartX;
        const deltaY = e.changedTouches[0].clientY - pointerStartY;
        if (Math.abs(deltaX) > 45 && Math.abs(deltaX) > Math.abs(deltaY)) {
            if (deltaX < 0) {
                nextSlideManual();
            } else {
                prevSlideManual();
            }
        }
    }, { passive: true });

    sliderEl.addEventListener('mousedown', (e) => {
        if (e.target.closest('button, a, input')) return;
        pointerStartX = e.clientX;
        pointerStartY = e.clientY;
        isDragging = true;
    });

    window.addEventListener('mouseup', (e) => {
        if (!isDragging) return;
        isDragging = false;
        const deltaX = e.clientX - pointerStartX;
        const deltaY = e.clientY - pointerStartY;
        if (Math.abs(deltaX) > 45 && Math.abs(deltaX) > Math.abs(deltaY)) {
            if (deltaX < 0) {
                nextSlideManual();
            } else {
                prevSlideManual();
            }
        }
    });
}

document.addEventListener('keydown', (e) => {
    if (e.key.toLowerCase() === 'm') {
        toggleAudio();
    } else if (e.key.toLowerCase() === 'f') {
        document.body.classList.toggle('force-full');
    } else if (e.key === 'ArrowRight') {
        nextSlideManual();
    } else if (e.key === 'ArrowLeft') {
        prevSlideManual();
    } else if (e.key === ' ') {
        e.preventDefault();
        toggleAutoSlide();
    }
});

function showSyncToast(message) {
    if (!syncToast) return;
    const textEl = syncToast.querySelector('span:last-child');
    if (textEl && message) {
        textEl.textContent = message;
    }
    syncToast.classList.remove('opacity-0', '-translate-y-2');
    syncToast.classList.add('opacity-100', 'translate-y-0');

    setTimeout(() => {
        syncToast.classList.remove('opacity-100', 'translate-y-0');
        syncToast.classList.add('opacity-0', '-translate-y-2');
    }, 2500);
}

function checkLiveUpdates() {
    if (isCheckingUpdate) return;
    isCheckingUpdate = true;

    fetch(`api.php?action=check&version=${encodeURIComponent(currentVersion)}&t=${Date.now()}`, {
        headers: {
            'X-KS-Token': apiToken
        }
    })
        .then(response => {
            if (!response.ok) throw new Error('API request failed');
            return response.json();
        })
        .then(data => {
            isCheckingUpdate = false;
            if (data && data.token) {
                apiToken = data.token;
            }
            if (data && data.changed === true) {
                if (totalSlides === 0) {
                    applyLiveUpdate(data);
                } else {
                    pendingSlideUpdate = data;
                    if (data.bgm_playlist) {
                        if (!bgAudio || bgAudio.paused || !bgAudio.src) {
                            bgmPlaylist = data.bgm_playlist;
                            playTrack(0);
                        } else {
                            pendingBgmPlaylist = data.bgm_playlist;
                        }
                    }
                    showSyncToast('Update queued • Applying on next cycle');
                }
            }
        })
        .catch(() => {
            isCheckingUpdate = false;
        });
}

function applyLiveUpdate(data) {
    currentVersion = data.version;

    if (data.settings) {
        if (data.settings.site_title) {
            document.title = data.settings.site_title;
        }

        const badgeEl = document.getElementById('headerBadgeText');
        if (badgeEl && data.settings.header_badge !== undefined) {
            badgeEl.textContent = data.settings.header_badge;
        }

        const companyEl = document.getElementById('headerCompanyText');
        if (companyEl && data.settings.header_company !== undefined) {
            companyEl.textContent = data.settings.header_company;
        }

        const logoContainer = document.getElementById('headerLogoContainer');
        if (logoContainer && data.settings.header_logo_url !== undefined) {
            const logoUrl = (data.settings.header_logo_url || '').trim();
            if (logoUrl) {
                logoContainer.innerHTML = `<img id="headerLogoImg" src="${logoUrl}" alt="Organization Logo" class="w-full h-full object-contain rounded-2xl shadow-xl shadow-black/50 p-1 bg-black/40 backdrop-blur border border-white/10">`;
            } else if (window.defaultKiosignLogoHtml) {
                logoContainer.innerHTML = window.defaultKiosignLogoHtml;
            }
        }

        if (data.settings.slide_duration_seconds) {
            duration = parseInt(data.settings.slide_duration_seconds * 1000, 10);
        }

        if (data.settings.auto_slide_enabled !== undefined) {
            isAutoSlidePaused = data.settings.auto_slide_enabled === '0';
            if (isAutoSlidePaused) {
                clearInterval(slideTimer);
                if (progressBar) {
                    progressBar.style.transitionDuration = '0ms';
                    progressBar.style.width = '0%';
                }
            } else {
                resetProgress();
                startAutoSlide();
            }
        }

        if (data.settings.date_locale !== undefined) {
            dateLocale = data.settings.date_locale || 'en';
            updateClock();
        }

        if (data.settings.default_audio_volume !== undefined) {
            defaultAudioVolume = parseFloat(data.settings.default_audio_volume);
            if (bgAudio) bgAudio.volume = defaultAudioVolume;
        }
    }

    if (data.bgm_playlist && Array.isArray(data.bgm_playlist)) {
        if (!bgAudio || bgAudio.paused || !bgAudio.src) {
            bgmPlaylist = data.bgm_playlist;
            playTrack(0);
        } else {
            pendingBgmPlaylist = data.bgm_playlist;
        }
    }

    const slideList = data.slides || [];
    renderSlides(slideList);
    showSyncToast('Content Updated');
}

function renderSlides(slideList) {
    if (!sliderEl) return;

    if (slideList.length === 0) {
        sliderEl.innerHTML = `
            <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center">
                <div class="w-16 h-16 rounded-2xl bg-white/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                </div>
                <h2 class="text-2xl font-bold mb-2">No Active Content</h2>
                <p class="text-slate-400 max-w-md mb-6">Please open the Admin Panel to add or activate display content.</p>
                <a href="panel/" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 font-bold transition">Open Admin Panel</a>
            </div>
        `;
        if (dotsContainer) dotsContainer.innerHTML = '';
        slides = [];
        indicators = [];
        totalSlides = 0;
        clearInterval(slideTimer);
        return;
    }

    let slidesHtml = '';
    let dotsHtml = '';

    slideList.forEach((slide, index) => {
        const isOfflineWeb = (slide.type || 'image') === 'offline_web';
        const isVideo = (slide.type || 'image') === 'video';
        const accent = escapeHtml(slide.accent || '#3b82f6');
        const tag = escapeHtml(slide.tag || '');
        const badge = escapeHtml(slide.badge || '');
        const title = escapeHtml(slide.title || '');
        const subtitle = escapeHtml(slide.subtitle || '');
        const actionLabel = escapeHtml(slide.action_label || (isOfflineWeb ? 'Interactive Portal' : 'Information Center'));
        const buttonLabel = escapeHtml(slide.button_label || (isOfflineWeb ? 'Open Web Page →' : 'Learn More'));
        const image = escapeHtml(slide.image || '');
        const videoUrl = escapeHtml(slide.video_url || '');
        const iframeUrl = escapeHtml(slide.iframe_url || '');

        let specsHtml = '';
        if (slide.specs && Array.isArray(slide.specs) && slide.specs.length > 0) {
            specsHtml = '<div class="flex flex-wrap gap-2 mb-[clamp(0.6rem,1.8vh,1rem)]">';
            slide.specs.forEach(spec => {
                specsHtml += `
                    <span class="text-[clamp(0.625rem,0.9vw,0.75rem)] font-semibold px-2.5 py-1 rounded-md bg-white/10 border border-white/15 text-slate-100 backdrop-blur-sm">
                        ${escapeHtml(spec)}
                    </span>
                `;
            });
            specsHtml += '</div>';
        }

        let mediaContent = '';
        if (isOfflineWeb) {
            mediaContent = `
                <div class="absolute inset-0 bg-[#070510] flex flex-col pt-[clamp(4.5rem,10vh,7rem)] pb-[clamp(5.5rem,14vh,9rem)] px-[clamp(1rem,3vw,3rem)]">
                    <div class="relative z-30 flex items-center justify-between px-4 py-2.5 rounded-t-2xl bg-[#120d24] border border-b-0 border-purple-500/30">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                            <span class="ml-2 text-[11px] font-mono text-purple-200/90 bg-black/40 px-2.5 py-0.5 rounded border border-white/10">
                                web://${iframeUrl}
                            </span>
                        </div>
                        <button type="button" onclick="openOfflineWebModal('${iframeUrl}', '${title}')"
                            class="text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-lg bg-purple-500/25 hover:bg-purple-500/40 text-purple-200 border border-purple-400/40 transition cursor-pointer">
                            Fullscreen ↗
                        </button>
                    </div>

                    <div class="relative z-20 w-full h-[46%] rounded-b-2xl overflow-hidden border border-purple-500/30 shadow-[0_0_50px_rgba(192,132,252,0.2)] bg-black">
                        <iframe src="${iframeUrl}" title="${title}" class="w-full h-full border-0" loading="lazy"></iframe>
                    </div>
                </div>
                <div class="pointer-events-none absolute inset-x-0 bottom-0 h-[52%] bg-gradient-to-t from-black via-black/95 to-transparent z-20"></div>
            `;
        } else if (isVideo && videoUrl) {
            mediaContent = `
                <div class="absolute inset-0 bg-black"></div>
                <video src="${videoUrl}" autoplay loop muted playsinline class="absolute inset-0 w-full h-full object-cover object-center pointer-events-none"></video>
                <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-transparent to-black/90"></div>
                <div class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-black via-black/80 to-transparent"></div>
            `;
        } else {
            mediaContent = `
                <div class="absolute inset-0" style="background: radial-gradient(circle at 50% 30%, ${accent}33 0%, #05070f 75%);"></div>
                ${image ? `<img src="${image}" alt="${title}" draggable="false" class="absolute inset-0 w-full h-full object-cover object-center pointer-events-none">` : ''}
                <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-transparent to-black/95"></div>
                <div class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-black via-black/80 to-transparent"></div>
            `;
        }

        let actionBtnHtml = '';
        if (isOfflineWeb) {
            actionBtnHtml = `
                <button type="button" onclick="openOfflineWebModal('${iframeUrl}', '${title}')"
                    class="shrink-0 text-[clamp(0.625rem,0.9vw,0.75rem)] font-extrabold uppercase tracking-wider px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white shadow-lg transition cursor-pointer">
                    ${buttonLabel}
                </button>
            `;
        } else {
            actionBtnHtml = `
                <span class="shrink-0 text-[clamp(0.625rem,0.9vw,0.75rem)] font-bold uppercase tracking-widest px-3 py-1.5 rounded-lg bg-white/10 text-slate-200">
                    ${buttonLabel}
                </span>
            `;
        }

        slidesHtml += `
            <div class="slide-item absolute inset-0 w-full h-full ${index === 0 ? 'active' : ''}" data-index="${index}">
                ${mediaContent}
                <div class="pointer-events-none relative z-30 flex flex-col justify-end h-full px-[clamp(1rem,3vw,3rem)] pb-[clamp(4.25rem,9vh,6.5rem)] max-w-6xl">
                    <div class="w-12 sm:w-16 h-1.5 rounded-full mb-[clamp(0.5rem,1.5vh,1rem)]"
                        style="background-color: ${accent}; box-shadow: 0 0 20px ${accent};">
                    </div>

                    <div class="flex flex-wrap items-center gap-2 mb-[clamp(0.4rem,1.2vh,0.75rem)]">
                        ${tag ? `<span class="inline-block px-3 py-1 text-[clamp(0.625rem,0.9vw,0.75rem)] font-extrabold uppercase tracking-wider rounded-lg border bg-black/60 backdrop-blur-md"
                            style="color: ${accent}; border-color: ${accent}66;">${tag}</span>` : ''}
                        ${badge ? `<span class="inline-flex items-center px-3 py-1 text-[clamp(0.625rem,0.9vw,0.75rem)] font-bold rounded-lg bg-white/15 border border-white/15 backdrop-blur-md text-white">${badge}</span>` : ''}
                    </div>

                    <h1 class="text-[clamp(1.5rem,4vw,3.5rem)] font-extrabold tracking-tight leading-[1.1] text-white drop-shadow-lg mb-[clamp(0.4rem,1.2vh,0.75rem)]">
                        ${title}
                    </h1>

                    ${subtitle ? `<p class="text-[clamp(0.8rem,1.4vw,1.125rem)] text-slate-200/90 font-normal leading-relaxed mb-[clamp(0.6rem,1.8vh,1rem)] drop-shadow max-w-4xl">${subtitle}</p>` : ''}
                    ${specsHtml}

                    <div class="pointer-events-auto flex items-center justify-between gap-3 p-[clamp(0.65rem,1.5vh,1rem)] rounded-2xl bg-white/[0.07] border border-white/15 backdrop-blur-md">
                        <div class="flex items-center space-x-3 min-w-0">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 shrink-0 rounded-xl flex items-center justify-center font-extrabold text-xs sm:text-sm"
                                style="background-color: ${accent}22; color: ${accent}; border: 1px solid ${accent}55;">
                                0${index + 1}
                            </div>
                            <div class="min-w-0">
                                <div class="text-[clamp(0.65rem,1vw,0.8rem)] font-bold uppercase tracking-wider text-white truncate">
                                    ${actionLabel}
                                </div>
                                <div class="text-[clamp(0.65rem,0.95vw,0.75rem)] text-slate-300 truncate">
                                    ${badge}
                                </div>
                            </div>
                        </div>
                        ${actionBtnHtml}
                    </div>
                </div>
            </div>
        `;

        dotsHtml += `
            <button onclick="goToSlide(${index})"
                class="indicator-dot h-2.5 rounded-full transition-all duration-300 cursor-pointer ${index === 0 ? 'w-8 bg-white' : 'w-2.5 bg-white/40 hover:bg-white/70'}"
                data-index="${index}" aria-label="Slide ${index + 1}">
            </button>
        `;
    });

    sliderEl.innerHTML = slidesHtml;
    if (dotsContainer) dotsContainer.innerHTML = dotsHtml;

    slides = document.querySelectorAll('.slide-item');
    indicators = document.querySelectorAll('.indicator-dot');
    totalSlides = slides.length;

    if (currentIndex >= totalSlides) {
        currentIndex = 0;
    }

    showSlide(currentIndex);
    startAutoSlide();
}

window.openOfflineWebModal = openOfflineWebModal;
window.closeOfflineWebModal = closeOfflineWebModal;
window.toggleAudio = toggleAudio;
window.goToSlide = goToSlide;
window.nextSlideManual = nextSlideManual;
window.prevSlideManual = prevSlideManual;
window.toggleAutoSlide = toggleAutoSlide;

setInterval(updateClock, 1000);
updateClock();
if (totalSlides > 0) {
    showSlide(0);
    startAutoSlide();
}
initAutoplay();

setInterval(checkLiveUpdates, pollIntervalMs);
