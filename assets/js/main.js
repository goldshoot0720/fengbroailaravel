document.addEventListener('DOMContentLoaded', function () {
    initDarkMode();
    initHeaderRefreshButtons();
    initGlobalMediaPlayer();
});

function initDarkMode() {
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
        document.documentElement.setAttribute('data-theme', 'dark');
        updateDarkModeIcon(true);
    }
}

function toggleDarkMode() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('theme', 'light');
        updateDarkModeIcon(false);
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
        updateDarkModeIcon(true);
    }
}

function updateDarkModeIcon(isDark) {
    const btn = document.getElementById('darkModeToggle');
    if (btn) {
        btn.innerHTML = isDark ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
    }
}

function initHeaderRefreshButtons() {
    document.querySelectorAll('.content-header').forEach(function (header) {
        if (header.querySelector('.header-refresh-btn')) return;

        const actionWrap = document.createElement('div');
        actionWrap.className = 'content-header-actions';

        const refreshBtn = document.createElement('button');
        refreshBtn.type = 'button';
        refreshBtn.className = 'btn btn-ghost header-refresh-btn';
        refreshBtn.title = '重新整理';
        refreshBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> 重新整理';
        refreshBtn.addEventListener('click', function () {
            window.location.reload();
        });

        actionWrap.appendChild(refreshBtn);
        header.appendChild(actionWrap);
    });
}

(function () {
    const PLAYER_KEY = 'fengbro_global_media_state';
    const VIEW_KEY_PREFIX = 'fengbro_media_view_';
    const THEME_KEY = 'fengbro_media_player_theme';
    let shell;
    let titleEl;
    let metaEl;
    let audioEl;
    let videoEl;
    let thumbEl;
    let closeBtn;
    let toggleBtn;
    let downloadBtn;
    let themeButtons;
    let activeKind = null;
    let syncing = false;
    let preservingPlaybackState = false;
    let resumeOnInteractionHandler = null;

    function getElements() {
        if (shell) return;
        shell = document.getElementById('globalMediaShell');
        titleEl = document.getElementById('globalMediaTitle');
        metaEl = document.getElementById('globalMediaMeta');
        audioEl = document.getElementById('globalAudioPlayer');
        videoEl = document.getElementById('globalVideoPlayer');
        thumbEl = document.getElementById('globalMediaThumb');
        closeBtn = document.getElementById('globalMediaClose');
        toggleBtn = document.getElementById('globalMediaToggle');
        downloadBtn = document.getElementById('globalMediaDownload');
        themeButtons = Array.from(document.querySelectorAll('[data-player-theme]'));
    }

    function readState() {
        try {
            return JSON.parse(localStorage.getItem(PLAYER_KEY) || 'null');
        } catch (error) {
            return null;
        }
    }

    function writeState(state) {
        if (!state || !state.src) {
            localStorage.removeItem(PLAYER_KEY);
            return;
        }
        localStorage.setItem(PLAYER_KEY, JSON.stringify(state));
    }

    function getActiveElement() {
        return activeKind === 'video' ? videoEl : audioEl;
    }

    function getThemeOptions(state) {
        if (state && state.kind === 'video') {
            return [
                { theme: 'bilibili', label: 'Bilibili' },
                { theme: 'youtube', label: 'YouTube' }
            ];
        }
        return [
            { theme: 'spotify', label: 'Spotify' },
            { theme: 'youtube', label: 'YouTube' },
            { theme: 'apple', label: 'Apple Podcasts' }
        ];
    }

    function readTheme(state) {
        const saved = localStorage.getItem(THEME_KEY);
        const options = getThemeOptions(state);
        if (saved && options.some(function (option) { return option.theme === saved; })) {
            return saved;
        }
        return options[0].theme;
    }

    function getCurrentPageName() {
        const params = new URLSearchParams(window.location.search);
        return params.get('page') || 'home';
    }

    function applyShellMode(state) {
        if (!shell) return;
        const isMiniVideo = !!(state && state.src && state.kind === 'video' && getCurrentPageName() !== 'videos');
        shell.classList.toggle('is-mini-video', isMiniVideo);
    }

    function applyTheme(theme, state) {
        getElements();
        const options = getThemeOptions(state);
        const fallbackTheme = options[0].theme;
        const normalized = options.some(function (option) { return option.theme === theme; }) ? theme : fallbackTheme;
        if (!shell) return;
        shell.classList.remove('theme-spotify', 'theme-youtube', 'theme-apple', 'theme-bilibili');
        shell.classList.add('theme-' + normalized);
        themeButtons.forEach(function (btn, index) {
            const option = options[index];
            if (!option) {
                btn.style.display = 'none';
                btn.classList.remove('active');
                btn.removeAttribute('data-player-theme');
                return;
            }
            btn.style.display = 'inline-flex';
            btn.dataset.playerTheme = option.theme;
            btn.textContent = option.label;
            btn.classList.toggle('active', option.theme === normalized);
        });
        localStorage.setItem(THEME_KEY, normalized);
    }

    function updateToggleIcon(isPaused) {
        if (!toggleBtn) return;
        toggleBtn.innerHTML = isPaused
            ? '<i class="fa-solid fa-play"></i>'
            : '<i class="fa-solid fa-pause"></i>';
    }

    function clearResumeOnInteraction() {
        if (!resumeOnInteractionHandler) return;
        ['pointerdown', 'keydown', 'touchstart'].forEach(function (eventName) {
            document.removeEventListener(eventName, resumeOnInteractionHandler, true);
        });
        resumeOnInteractionHandler = null;
    }

    function queueResumeOnInteraction(el) {
        clearResumeOnInteraction();
        resumeOnInteractionHandler = function () {
            el.play().then(function () {
                clearResumeOnInteraction();
                syncStateFromElement(true);
            }).catch(function () {});
        };
        ['pointerdown', 'keydown', 'touchstart'].forEach(function (eventName) {
            document.addEventListener(eventName, resumeOnInteractionHandler, true);
        });
    }

    function applyDownload(state) {
        if (!downloadBtn) return;
        if (state && state.src) {
            downloadBtn.href = state.src;
            if (state.downloadName) {
                downloadBtn.setAttribute('download', state.downloadName);
            } else {
                downloadBtn.removeAttribute('download');
            }
            downloadBtn.style.display = 'inline-flex';
            return;
        }
        downloadBtn.removeAttribute('href');
        downloadBtn.removeAttribute('download');
        downloadBtn.style.display = 'none';
    }

    function renderShell(state) {
        getElements();
        if (!shell) return;

        if (!state || !state.src) {
            clearResumeOnInteraction();
            shell.style.display = 'none';
            shell.classList.remove('is-video');
            shell.classList.remove('is-audio');
            shell.classList.remove('is-mini-video');
            if (audioEl) {
                audioEl.pause();
                audioEl.removeAttribute('src');
                audioEl.load();
            }
            if (videoEl) {
                videoEl.pause();
                videoEl.removeAttribute('src');
                videoEl.load();
                videoEl.removeAttribute('poster');
            }
            applyDownload(null);
            activeKind = null;
            return;
        }

        activeKind = state.kind === 'video' ? 'video' : 'audio';
        shell.style.display = 'block';
        shell.classList.toggle('is-video', activeKind === 'video');
        shell.classList.toggle('is-audio', activeKind === 'audio');
        applyShellMode(state);
        applyTheme(readTheme(state), state);

        titleEl.textContent = state.title || (activeKind === 'video' ? '影片播放中' : '音訊播放中');
        metaEl.textContent = state.meta || (state.mediaType === 'podcast' ? 'Podcast' : state.mediaType === 'music' ? 'Music' : 'Media');

        if (thumbEl) {
            if (state.poster) {
                thumbEl.src = state.poster;
                thumbEl.style.display = 'block';
            } else {
                thumbEl.removeAttribute('src');
                thumbEl.style.display = 'none';
            }
        }

        applyDownload(state);

        if (audioEl) {
            audioEl.style.display = activeKind === 'audio' ? 'block' : 'none';
        }
        if (videoEl) {
            videoEl.style.display = activeKind === 'video' ? 'block' : 'none';
        }
    }

    function syncStateFromElement(forceKeepPlaying) {
        if (syncing) return;
        const current = readState();
        const el = getActiveElement();
        if (!current || !el) return;
        current.currentTime = Number(el.currentTime || 0);
        current.volume = Number(el.volume || 1);
        current.wasPlaying = forceKeepPlaying ? true : (!el.paused && !el.ended);
        writeState(current);
        updateToggleIcon(el.paused);
    }

    function loadStateIntoElement(state, autoplay) {
        getElements();
        if (!state || !state.src) {
            renderShell(null);
            return;
        }

        renderShell(state);
        const el = state.kind === 'video' ? videoEl : audioEl;
        const other = state.kind === 'video' ? audioEl : videoEl;

        syncing = true;
        if (other) {
            other.pause();
            other.removeAttribute('src');
            other.load();
        }

        if (state.kind === 'video' && state.poster) {
            videoEl.poster = state.poster;
        }

        if (el.src !== state.src) {
            el.src = state.src;
        }
        el.volume = Number(state.volume ?? 1);

        const resumeAt = Number(state.currentTime || 0);
        const finalizeLoad = function () {
            if (resumeAt > 0 && Number.isFinite(resumeAt)) {
                try {
                    el.currentTime = resumeAt;
                } catch (error) {
                    // ignore seek errors during initial load
                }
            }
            syncing = false;
            updateToggleIcon(el.paused);
            if (autoplay || state.wasPlaying) {
                el.play().then(function () {
                    clearResumeOnInteraction();
                    syncStateFromElement();
                }).catch(function () {
                    queueResumeOnInteraction(el);
                    syncStateFromElement(true);
                });
            } else {
                clearResumeOnInteraction();
                syncStateFromElement();
            }
        };

        if (el.readyState >= 1) {
            finalizeLoad();
        } else {
            el.onloadedmetadata = finalizeLoad;
        }
    }

    function play(kind, payload) {
        const current = {
            kind: kind,
            mediaType: payload.mediaType || kind,
            id: payload.id || '',
            src: payload.src || '',
            title: payload.title || '',
            meta: payload.meta || '',
            poster: payload.poster || '',
            currentTime: Number(payload.currentTime || 0),
            volume: Number(payload.volume ?? readState()?.volume ?? 1),
            wasPlaying: true,
            downloadName: payload.downloadName || '',
        };
        writeState(current);
        loadStateIntoElement(current, true);
    }

    function stop() {
        renderShell(null);
        writeState(null);
    }

    function toggle() {
        const el = getActiveElement();
        if (!el) return;
        if (el.paused) {
            el.play().catch(function () {});
        } else {
            el.pause();
        }
        syncStateFromElement();
    }

    function toggleBySource(payload) {
        const state = readState();
        const el = getActiveElement();
        if (state && el && state.src === payload.src && state.kind === payload.kind) {
            toggle();
            return;
        }
        play(payload.kind, payload);
    }

    function initGlobalMediaPlayer() {
        getElements();
        if (!shell || !audioEl || !videoEl) return;

        [audioEl, videoEl].forEach(function (el) {
            ['play', 'pause', 'timeupdate', 'volumechange', 'ended'].forEach(function (eventName) {
                el.addEventListener(eventName, function () {
                    if (eventName === 'ended') {
                        const current = readState();
                        if (current) {
                            current.wasPlaying = false;
                            current.currentTime = 0;
                            writeState(current);
                        }
                    } else if (eventName === 'pause' && (preservingPlaybackState || document.visibilityState === 'hidden')) {
                        return;
                    } else {
                        syncStateFromElement();
                    }
                });
            });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', stop);
        }
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggle);
        }
        themeButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyTheme(btn.dataset.playerTheme, readState());
            });
        });

        const state = readState();
        applyTheme(readTheme(state), state);
        if (state && state.src) {
            loadStateIntoElement(state, false);
        } else {
            renderShell(null);
        }

        document.addEventListener('visibilitychange', function () {
            preservingPlaybackState = document.visibilityState === 'hidden';
            if (preservingPlaybackState) {
                syncStateFromElement(true);
            }
        });

        window.addEventListener('pagehide', function () {
            preservingPlaybackState = true;
            syncStateFromElement(true);
        });

        window.addEventListener('pageshow', function () {
            preservingPlaybackState = false;
        });

        window.addEventListener('beforeunload', function () {
            preservingPlaybackState = true;
            syncStateFromElement(true);
        });
    }

    function setMediaView(scope, mode) {
        const browser = document.querySelector('[data-media-scope="' + scope + '"]');
        if (!browser) return;
        const normalized = mode === 'list' ? 'list' : 'grid';
        browser.classList.remove('media-view-grid', 'media-view-list');
        browser.classList.add('media-view-' + normalized);
        browser.querySelectorAll('[data-media-view-btn]').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.mediaViewBtn === normalized);
        });
        localStorage.setItem(VIEW_KEY_PREFIX + scope, normalized);
    }

    function initMediaView(scope, fallbackMode) {
        const saved = localStorage.getItem(VIEW_KEY_PREFIX + scope) || fallbackMode || 'grid';
        setMediaView(scope, saved);
    }

    window.FengbroMedia = {
        initGlobalMediaPlayer: initGlobalMediaPlayer,
        playAudio: function (payload) { play('audio', payload); },
        playVideo: function (payload) { play('video', payload); },
        stop: stop,
        toggle: toggle,
        toggleBySource: toggleBySource,
        getState: readState,
        setMediaView: setMediaView,
        initMediaView: initMediaView
    };

    window.initGlobalMediaPlayer = initGlobalMediaPlayer;
    window.setMediaView = setMediaView;
    window.initMediaView = initMediaView;
})();
