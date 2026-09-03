document.addEventListener('DOMContentLoaded', function () {
    initDarkMode();
    initHeaderRefreshButtons();
    initGlobalMediaPlayer();
    initFengbroVoiceInput();
});

function getThemePreference() {
    const saved = localStorage.getItem('theme');
    if (saved === 'light' || saved === 'dark' || saved === 'system') return saved;
    // 相容舊值：未設定時視為 system
    return 'system';
}

function resolveThemeMode(preference) {
    if (preference === 'dark') return 'dark';
    if (preference === 'light') return 'light';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyThemePreference(preference, persist) {
    const mode = resolveThemeMode(preference);
    if (mode === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.removeAttribute('data-theme');
    }
    if (persist !== false) {
        localStorage.setItem('theme', preference);
    }
    updateDarkModeIcon(preference, mode === 'dark');
}

function initDarkMode() {
    const preference = getThemePreference();
    applyThemePreference(preference, false);

    // 跟隨系統時監聽系統主題變化
    try {
        const mql = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = function () {
            if (getThemePreference() === 'system') {
                applyThemePreference('system', false);
            }
        };
        if (typeof mql.addEventListener === 'function') {
            mql.addEventListener('change', onChange);
        } else if (typeof mql.addListener === 'function') {
            mql.addListener(onChange);
        }
    } catch (e) {}
}

function toggleDarkMode() {
    // 循環：system → light → dark → system（對齊 Appwrite 三態主題）
    const current = getThemePreference();
    const next = current === 'system' ? 'light' : (current === 'light' ? 'dark' : 'system');
    applyThemePreference(next, true);
}

function updateDarkModeIcon(preference, isDarkResolved) {
    const btn = document.getElementById('darkModeToggle');
    if (!btn) return;
    const pref = preference || getThemePreference();
    const isDark = typeof isDarkResolved === 'boolean'
        ? isDarkResolved
        : (document.documentElement.getAttribute('data-theme') === 'dark');
    if (pref === 'system') {
        btn.innerHTML = '<i class="fa-solid fa-circle-half-stroke"></i>';
        btn.title = '主題：跟隨系統（點擊切換）';
    } else if (isDark || pref === 'dark') {
        btn.innerHTML = '<i class="fa-solid fa-sun"></i>';
        btn.title = '主題：深色（點擊切換）';
    } else {
        btn.innerHTML = '<i class="fa-solid fa-moon"></i>';
        btn.title = '主題：淺色（點擊切換）';
    }
}

function initFengbroVoiceInput() {
    if (window.__fengbroVoiceReady) return;
    window.__fengbroVoiceReady = true;

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;
    let listening = false;
    let pendingAction = null;
    let recognitionLanguage = localStorage.getItem('fengbro_voice_lang') || 'zh-TW';
    let manualStop = false;
    const MIN_RECORDING_MS = 10000;
    let recordingStartedAt = 0;
    let stopAfterMinimumTimer = null;
    let stopRequestedAfterMinimum = false;

    const pageProfiles = {
        home: {
            title: '鋒兄首頁',
            aliases: ['首頁', '鋒兄首頁', 'home'],
            fields: {},
            examples: ['重新整理', '深色模式', '前往鋒兄食品', '搜尋 今天']
        },
        dashboard: {
            title: '鋒兄儀表',
            aliases: ['儀表', '儀表板', '鋒兄儀表', 'dashboard'],
            fields: {},
            examples: ['重新整理', '前往訂閱', '全選', '搜尋 本月']
        },
        subscription: {
            title: '鋒兄訂閱',
            aliases: ['訂閱', '鋒兄訂閱', 'subscription'],
            fields: {
                name: ['服務', '服務名稱', '訂閱', '名稱'],
                site: ['網站', '網址', '連結'],
                account: ['帳號', '使用者'],
                price: ['價格', '金額', '費用'],
                currency: ['幣別', '貨幣'],
                nextdate: ['下次付款', '付款日期', '日期', '時間', '到期'],
                note: ['備註', '筆記', '說明'],
                continue: ['續訂', '不續訂']
            },
            examples: ['新增訂閱 Netflix 價格 299 下月 5 號', '搜尋 Netflix', '篩選 7 天內', '不續訂', '儲存']
        },
        trialpurchase: {
            title: '鋒兄試用／首購',
            aliases: ['試用', '首購', '試用首購', '試用/首購', '鋒兄試用', '鋒兄首購', 'trial', 'trialpurchase'],
            fields: {
                name: ['服務', '服務名稱', '名稱'],
                account: ['帳號', '使用者'],
                eventDate: ['日期', '到期', '扣款日', '試用日', '首購日'],
                firstPurchasePrice: ['首購價格', '首購'],
                regularPrice: ['非首購價格', '一般價格', '價格'],
                trialStatus: ['試用狀態'],
                purchaseStatus: ['首購狀態'],
                note: ['備註', '說明']
            },
            examples: ['新增紀錄 ChatGPT 帳號 demo@example.com', '搜尋 ChatGPT', '篩選尚未試用', '儲存']
        },
        reinstall: {
            title: '鋒兄重灌',
            aliases: ['重灌', '重裝', '軟體清單', '鋒兄重灌', 'reinstall'],
            fields: {
                name: ['服務', '服務名稱', '軟體名稱', '名稱'],
                system: ['系統', 'Windows', 'Mac'],
                softwareType: ['軟體類型', '試用', '免費', '付費'],
                subscriptionSoftware: ['訂閱制', '訂閱制軟體'],
                subscriptionPeriod: ['訂閱週期', '週期'],
                subscriptionPrice: ['訂閱費用', '費用'],
                subscriptionCurrency: ['訂閱費用幣別', '幣別'],
                licenseType: ['授權', '序號', '無序號'],
                serial: ['付費序號', '序號'],
                viewPassword: ['查看密碼', '密碼'],
                site: ['網站', '網址'],
                note: ['備註', '說明']
            },
            examples: ['新增軟體 7-Zip 系統 Windows', '搜尋 Adobe', '篩選 Mac', '儲存']
        },
        food: {
            title: '鋒兄食品',
            aliases: ['食品', '食材', '商品庫存', '鋒兄食品', 'food'],
            fields: {
                name: ['食品', '商品', '名稱', '品名'],
                store: ['商店', '店家'],
                shop: ['商店', '店家'],
                quantity: ['數量', '庫存'],
                amount: ['數量', '庫存'],
                price: ['價格', '金額'],
                expiry_date: ['到期', '有效日期', '日期'],
                todate: ['到期', '有效日期', '日期'],
                category: ['分類', '類別'],
                note: ['備註', '筆記'],
                photo: ['圖片', '照片', '圖片網址']
            },
            examples: ['新增食品 牛奶 數量 2 到期 7 天', '搜尋 雞蛋', '低庫存', '已過期', '編輯第一筆', '儲存']
        },
        notes: {
            title: '鋒兄筆記',
            aliases: ['筆記', '文章', '鋒兄筆記', 'notes'],
            fields: {
                title: ['標題', '題目', '名稱'],
                name: ['名稱', '標題'],
                content: ['內容', '本文'],
                category: ['分類', '類別'],
                note: ['備註', '筆記'],
                ref: ['參考', '來源']
            },
            examples: ['新增筆記 標題 今天想法 內容 記錄一下', '搜尋 AI', '分類 工作', '匯入 ZIP', '儲存']
        },
        favorites: {
            title: '鋒兄常用',
            aliases: ['常用', '常用帳號', '常用網站', '鋒兄常用', 'favorites'],
            fields: {
                name: ['名稱', '帳號名稱', '網站名稱'],
                site: ['網站', '平台', '分類'],
                note: ['備註', '內容', '筆記']
            },
            examples: ['新增常用 GitHub 備註 工作帳號', '搜尋 Google', '新增欄位', '儲存']
        },
        images: {
            title: '鋒兄圖片',
            aliases: ['圖片', '照片', '圖庫', '鋒兄圖片', 'images'],
            fields: {
                name: ['名稱', '圖片名稱', '標題'],
                file: ['檔案', '圖片網址', '網址'],
                category: ['分類', '類別'],
                ref: ['參考', '來源'],
                note: ['備註', '描述']
            },
            examples: ['新增圖片 名稱 封面 分類 設計', '上傳圖片', '卡片模式', '列表模式', '匯入 ZIP']
        },
        videos: {
            title: '鋒兄影片',
            aliases: ['影片', '視頻', '鋒兄影片', 'videos'],
            fields: {
                name: ['名稱', '影片名稱', '標題'],
                file: ['檔案', '影片網址', '網址'],
                cover: ['封面'],
                category: ['分類', '類別'],
                ref: ['參考', '來源'],
                note: ['備註', '描述']
            },
            examples: ['新增影片 名稱 Demo 分類 教學', '上傳影片', '播放', '暫停', '匯入 ZIP']
        },
        music: {
            title: '鋒兄音樂',
            aliases: ['音樂', '歌曲', '鋒兄音樂', 'music'],
            fields: {
                name: ['名稱', '歌名', '歌曲'],
                file: ['檔案', '音樂網址', '網址'],
                cover: ['封面'],
                category: ['分類', '類別'],
                language: ['語言', '版本'],
                lyrics: ['歌詞'],
                ref: ['參考', '來源'],
                note: ['備註', '描述']
            },
            examples: ['新增音樂 歌名 Blue 語言 日文', '上傳音樂', '播放', '歌詞 打開', '匯入 ZIP']
        },
        documents: {
            title: '鋒兄文件',
            aliases: ['文件', '檔案', '文檔', '鋒兄文件', 'documents'],
            fields: {
                name: ['名稱', '文件名稱', '標題'],
                file: ['檔案', '檔案路徑', '網址'],
                category: ['分類', '類別'],
                ref: ['參考', '來源'],
                note: ['備註', '描述']
            },
            examples: ['新增文件 名稱 合約 分類 工作', '多選上傳', '上傳文件', '列表模式', '匯入 ZIP']
        },
        podcast: {
            title: '鋒兄播客',
            aliases: ['播客', 'podcast', '鋒兄播客'],
            fields: {
                name: ['名稱', '節目', '標題'],
                file: ['檔案', '音訊網址', '網址'],
                cover: ['封面'],
                category: ['分類', '類別'],
                ref: ['參考', '來源'],
                note: ['備註', '描述']
            },
            examples: ['新增播客 名稱 訪談 分類 AI', '上傳播客', '播放', '匯入 ZIP']
        },
        bank: {
            title: '鋒兄銀行',
            aliases: ['銀行', '帳戶', '鋒兄銀行', 'bank'],
            fields: {
                name: ['名稱', '銀行名稱'],
                account: ['帳號'],
                card: ['卡號'],
                address: ['地址'],
                site: ['網站', '網址'],
                activity: ['活動', '活動網址'],
                deposit: ['存款', '收入'],
                withdrawals: ['提款', '支出'],
                transfer: ['轉帳'],
                transactionAmount: ['交易金額', '金額']
            },
            examples: ['新增銀行 台新 帳號 123', '新增收入 1000', '新增支出 200', '多選設定存款', '儲存']
        },
        routine: {
            title: '鋒兄例行',
            aliases: ['例行', '待辦', '習慣', '鋒兄例行', 'routine'],
            fields: {
                name: ['名稱', '事項', '任務'],
                category: ['分類', '類別'],
                schedule: ['週期', '排程'],
                note: ['備註', '描述']
            },
            examples: ['新增例行 喝水 分類 健康', '搜尋 每天', '儲存', '刪除選取']
        },
        tools: {
            title: '鋒兄工具',
            aliases: ['工具', '比價', '鋒兄工具', 'tools'],
            fields: {
                name: ['名稱', '工具名稱'],
                url: ['網址', '連結'],
                note: ['備註', '描述']
            },
            examples: [
                '前往鋒兄新聞', '前往手機比價', '前往圖加語音', '前往影片合併',
                '前往 YT 轉檔', '前往 PNG', '前往手動價格', '前往鋒兄tube', '前往鋒兄金融',
                '搜尋 匯率', '重新整理'
            ]
        },
        settings: {
            title: '鋒兄設定',
            aliases: ['設定', '鋒兄設定', 'settings'],
            fields: {
                resend_api_key: ['RESEND_API_KEY', 'RESEND API KEY', 'resend key', '郵件金鑰', '寄信金鑰'],
                resend_to_email: ['收件 Email', '收件信箱', '收件者'],
                resend_from_email: ['寄件 Email', '寄件信箱', '寄件者']
            },
            examples: ['在 RESEND_API_KEY 輸入 re_...', '儲存 RESEND 設定', '掃描儲存空間', '傳送推播', '產生金鑰']
        },
        about: {
            title: '鋒兄關於',
            aliases: ['關於', '版本', '鋒兄關於', 'about'],
            fields: {},
            examples: ['重新整理', '前往首頁', '深色模式']
        }
    };

    const pageOrder = Object.keys(pageProfiles);
    const commandVerbs = ['前往', '打開', '開啟', '切換到', '切換', '去', '到', '點擊', '按下', '按', '選擇', '選取', '進入'];
    const commonActionLabels = [
        '新增', '儲存', '取消', '刪除', '編輯', '查看', '下載', '匯入', '匯出', '上傳',
        '搜尋', '篩選', '重新整理', '卡片', '列表', '清單', '播放', '暫停', '關閉'
    ];

    function currentPage() {
        const params = new URLSearchParams(window.location.search);
        return params.get('page') || 'home';
    }

    function normalize(text) {
        return String(text || '')
            .replace(/[，。！？、,.!?]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function compact(text) {
        return normalize(text).toLowerCase().replace(/\s+/g, '').replace(/[()（）＋+·・_-]/g, '');
    }

    function stripCommandVerb(text) {
        let cleaned = normalize(text);
        commandVerbs.forEach(function (verb) {
            if (cleaned.indexOf(verb) === 0) cleaned = cleaned.slice(verb.length).trim();
        });
        return cleaned;
    }

    function createUI() {
        if (document.getElementById('fengbroVoiceFab')) return;

        const fab = document.createElement('button');
        fab.id = 'fengbroVoiceFab';
        fab.type = 'button';
        fab.className = 'voice-fab';
        fab.title = '鋒兄語音輸入';
        fab.innerHTML = '<i class="fa-solid fa-microphone"></i><span>語音</span>';
        fab.addEventListener('click', toggleListening);

        const panel = document.createElement('div');
        panel.id = 'fengbroVoicePanel';
        panel.className = 'voice-panel';
        panel.innerHTML = [
            '<div class="voice-panel-head">',
            '<strong><i class="fa-solid fa-microphone-lines"></i> 鋒兄語音輸入</strong>',
            '<button type="button" id="fengbroVoiceClose" class="voice-icon-btn" title="關閉"><i class="fa-solid fa-xmark"></i></button>',
            '</div>',
            '<div id="fengbroVoiceStatus" class="voice-status">按下語音，說出要做的事。</div>',
            '<div class="voice-record-actions">',
            '<button type="button" id="fengbroVoiceStart" class="btn btn-primary btn-sm"><i class="fa-solid fa-microphone"></i> 開始錄音</button>',
            '<button type="button" id="fengbroVoiceStop" class="btn btn-sm"><i class="fa-solid fa-stop"></i> 結束錄音</button>',
            '<span id="fengbroVoiceMinimum" class="voice-minimum">最低錄音 10 秒</span>',
            '</div>',
            '<div id="fengbroVoiceTranscript" class="voice-transcript"></div>',
            '<div id="fengbroVoiceConfirm" class="voice-confirm" style="display:none;">',
            '<div id="fengbroVoiceConfirmText"></div>',
            '<div class="voice-confirm-actions">',
            '<button type="button" id="fengbroVoiceConfirmYes" class="btn btn-primary btn-sm">確認執行</button>',
            '<button type="button" id="fengbroVoiceConfirmNo" class="btn btn-sm">取消</button>',
            '</div>',
            '</div>',
            '<div id="fengbroVoiceHints" class="voice-hints"></div>',
            '<div class="voice-manual">',
            '<input id="fengbroVoiceManualInput" type="text" placeholder="也可以輸入語音指令文字">',
            '<button type="button" id="fengbroVoiceManualRun" class="btn btn-sm">執行</button>',
            '</div>'
        ].join('');

        document.body.appendChild(fab);
        document.body.appendChild(panel);

        document.getElementById('fengbroVoiceClose').addEventListener('click', function () {
            panel.classList.remove('show');
            requestStopListening();
        });
        document.getElementById('fengbroVoiceStart').addEventListener('click', startListening);
        document.getElementById('fengbroVoiceStop').addEventListener('click', requestStopListening);
        document.getElementById('fengbroVoiceConfirmYes').addEventListener('click', runPendingAction);
        document.getElementById('fengbroVoiceConfirmNo').addEventListener('click', clearPendingAction);
        document.getElementById('fengbroVoiceManualRun').addEventListener('click', function () {
            const input = document.getElementById('fengbroVoiceManualInput');
            handleCommand(input.value);
            input.value = '';
        });
        document.getElementById('fengbroVoiceManualInput').addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                document.getElementById('fengbroVoiceManualRun').click();
            }
        });
        updateHints();
    }

    function setPanelVisible(visible) {
        const panel = document.getElementById('fengbroVoicePanel');
        if (panel) panel.classList.toggle('show', visible);
    }

    function setStatus(message, kind) {
        const status = document.getElementById('fengbroVoiceStatus');
        if (!status) return;
        status.textContent = message;
        status.dataset.kind = kind || 'info';
    }

    function setTranscript(text) {
        const el = document.getElementById('fengbroVoiceTranscript');
        if (el) el.textContent = text || '';
    }

    function updateFab() {
        const fab = document.getElementById('fengbroVoiceFab');
        if (fab) {
            fab.classList.toggle('listening', listening || stopRequestedAfterMinimum);
            fab.title = (listening || stopRequestedAfterMinimum) ? '結束錄音' : '開始錄音';
            fab.innerHTML = (listening || stopRequestedAfterMinimum)
                ? '<i class="fa-solid fa-stop"></i><span>結束</span>'
                : '<i class="fa-solid fa-microphone"></i><span>語音</span>';
        }
        updateRecordingControls();
    }

    function updateRecordingControls() {
        const startBtn = document.getElementById('fengbroVoiceStart');
        const stopBtn = document.getElementById('fengbroVoiceStop');
        const minimum = document.getElementById('fengbroVoiceMinimum');
        if (startBtn) startBtn.disabled = listening || stopRequestedAfterMinimum;
        if (stopBtn) stopBtn.disabled = !listening && !stopRequestedAfterMinimum;
        if (minimum) {
            const remaining = getRemainingRecordingMs();
            minimum.textContent = remaining > 0 && listening
                ? '最低錄音 10 秒，還剩 ' + Math.ceil(remaining / 1000) + ' 秒'
                : '最低錄音 10 秒';
        }
    }

    function getRemainingRecordingMs() {
        if (!recordingStartedAt) return 0;
        return Math.max(0, MIN_RECORDING_MS - (Date.now() - recordingStartedAt));
    }

    function clearMinimumStopTimer() {
        if (stopAfterMinimumTimer) {
            window.clearTimeout(stopAfterMinimumTimer);
            stopAfterMinimumTimer = null;
        }
    }

    function scheduleMinimumStop(remainingMs) {
        clearMinimumStopTimer();
        stopAfterMinimumTimer = window.setTimeout(function () {
            stopAfterMinimumTimer = null;
            if (stopRequestedAfterMinimum) forceStopListening();
        }, Math.max(0, remainingMs));
    }

    function updateHints() {
        const hints = document.getElementById('fengbroVoiceHints');
        if (!hints) return;
        const profile = pageProfiles[currentPage()] || pageProfiles.home;
        const menuExamples = getVoiceMenuItems().slice(0, 4).map(function (item) {
            return '前往 ' + item.label;
        });
        const pageButtons = getVisibleActionLabels().slice(0, 6);
        const examples = [
            '確認',
            '取消',
            '搜尋 關鍵字',
            '新增',
            '儲存',
            '下一個選單',
            '打開選單',
            '開始錄音',
            '結束錄音',
            '下一欄',
            '編輯第一筆',
            '在名稱輸入 範例'
        ].concat(menuExamples).concat(pageButtons).concat(profile.examples || []);
        hints.innerHTML = unique(examples).slice(0, 18).map(function (item) {
            return '<button type="button" class="voice-chip">' + escapeHtml(item) + '</button>';
        }).join('');
        hints.querySelectorAll('.voice-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                handleCommand(chip.textContent || '');
            });
        });
    }

    function unique(items) {
        const seen = new Set();
        return items.filter(function (item) {
            const key = compact(item);
            if (!key || seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    }

    function escapeHtml(text) {
        return String(text || '').replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch];
        });
    }

    function setupRecognition() {
        if (!SpeechRecognition || recognition) return;
        recognition = new SpeechRecognition();
        recognition.lang = recognitionLanguage;
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.maxAlternatives = 3;
        recognition.onstart = function () {
            listening = true;
            manualStop = false;
            if (!recordingStartedAt) recordingStartedAt = Date.now();
            updateFab();
            setPanelVisible(true);
            setStatus('錄音中。請手動按「結束錄音」，最低錄音 10 秒。', 'active');
        };
        recognition.onend = function () {
            listening = false;
            updateFab();
            if (!manualStop) {
                setStatus('錄音仍在進行，正在恢復收音。', 'info');
                window.setTimeout(function () {
                    if (manualStop || listening) return;
                    try {
                        recognition.start();
                    } catch (error) {}
                }, 450);
                return;
            }
            recordingStartedAt = 0;
            stopRequestedAfterMinimum = false;
            clearMinimumStopTimer();
            updateFab();
            setStatus('錄音已停止。可按「開始錄音」繼續。', 'info');
        };
        recognition.onerror = function (event) {
            const recoverable = !manualStop && ['no-speech', 'aborted', 'network'].indexOf(event.error) !== -1;
            listening = false;
            if (recoverable) {
                updateFab();
                setStatus('錄音仍在進行，正在恢復收音。', 'info');
                window.setTimeout(function () {
                    if (manualStop || listening || !recognition) return;
                    try {
                        recognition.start();
                    } catch (error) {}
                }, 450);
                return;
            }
            recordingStartedAt = 0;
            stopRequestedAfterMinimum = false;
            clearMinimumStopTimer();
            updateFab();
            setStatus('語音辨識失敗：' + (event.error || '未知錯誤') + '。可改用文字指令。', 'error');
        };
        recognition.onresult = function (event) {
            let interim = '';
            let finalText = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const text = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalText += text;
                } else {
                    interim += text;
                }
            }
            setTranscript(finalText || interim);
            if (finalText) handleCommand(finalText);
        };
    }

    function toggleListening() {
        if (listening || stopRequestedAfterMinimum) {
            requestStopListening();
            return;
        }
        startListening();
    }

    function startListening() {
        createUI();
        setPanelVisible(true);
        if (!SpeechRecognition) {
            setStatus('此瀏覽器不支援 Web Speech API，請使用下方文字指令。', 'error');
            return;
        }
        setupRecognition();
        if (listening) return;
        try {
            manualStop = false;
            stopRequestedAfterMinimum = false;
            clearMinimumStopTimer();
            recordingStartedAt = Date.now();
            updateFab();
            recognition.start();
        } catch (error) {
            setStatus('語音已在準備中，請稍候再試。', 'info');
        }
    }

    function stopListening() {
        requestStopListening();
    }

    function requestStopListening() {
        if (!listening && !stopRequestedAfterMinimum) return;
        const remaining = getRemainingRecordingMs();
        if (remaining > 0) {
            stopRequestedAfterMinimum = true;
            setStatus('已收到結束錄音，滿 10 秒後停止。還剩 ' + Math.ceil(remaining / 1000) + ' 秒。', 'info');
            updateFab();
            scheduleMinimumStop(remaining);
            return;
        }
        forceStopListening();
    }

    function forceStopListening() {
        manualStop = true;
        stopRequestedAfterMinimum = false;
        clearMinimumStopTimer();
        if (recognition && listening) {
            recognition.stop();
        }
        listening = false;
        updateFab();
    }

    function rebuildRecognition() {
        const wasListening = listening;
        if (recognition) {
            try {
                recognition.onend = null;
                recognition.stop();
            } catch (error) {}
        }
        recognition = null;
        setupRecognition();
        if (wasListening && recognition) {
            try {
                manualStop = false;
                recognition.start();
            } catch (error) {}
        }
    }

    function stageAction(label, callback) {
        pendingAction = { label: label, callback: callback };
        const box = document.getElementById('fengbroVoiceConfirm');
        const text = document.getElementById('fengbroVoiceConfirmText');
        if (box && text) {
            text.textContent = label + '。請再說「確認」或按確認執行。';
            box.style.display = 'block';
        }
        setStatus('等待二次確認。', 'confirm');
    }

    function runPendingAction() {
        if (!pendingAction) {
            setStatus('目前沒有待確認動作。', 'info');
            return;
        }
        const action = pendingAction;
        clearPendingAction(false);
        action.callback();
        setStatus('已執行：' + action.label, 'success');
    }

    function clearPendingAction(showStatus) {
        pendingAction = null;
        const box = document.getElementById('fengbroVoiceConfirm');
        if (box) box.style.display = 'none';
        if (showStatus !== false) setStatus('已取消待確認動作。', 'info');
    }

    function handleCommand(rawText) {
        createUI();
        setPanelVisible(true);
        const text = normalize(rawText);
        if (!text) return;
        setTranscript(text);

        if (/^(確認|確定|執行|好|可以|yes)$/i.test(text)) {
            runPendingAction();
            return;
        }
        if (/^(取消|不要|停止|算了|關閉)$/i.test(text)) {
            clearPendingAction();
            stopListening();
            return;
        }
        if (/幫助|指令|可以說什麼|說明/.test(text)) {
            updateHints();
            setStatus('已顯示這一頁可用的語音指令範例。', 'success');
            return;
        }

        if (handleVoicePanelCommand(text)) return;

        if (handleCrossPageSubscriptionCommand(text)) return;

        const navTarget = findPageTarget(text);
        if (navTarget) {
            if (navTarget === currentPage() && /(新增|建立|加入|新增一筆|一筆資料|名稱|服務名稱|日期|時間)/.test(text)) {
                // Stay on the current page so the command can open the add form and fill fields.
            } else {
            stageAction('前往 ' + pageProfiles[navTarget].title, function () {
                window.location.href = 'index.php?page=' + encodeURIComponent(navTarget);
            });
            return;
            }
        }

        if (handleMenuCommand(text)) return;
        if (handleFieldCommand(text)) return;
        if (handleItemCommand(text)) return;
        if (handlePageCommand(text)) return;
        if (handleGlobalCommand(text)) return;
        if (fillFromSpeech(text)) return;

        const active = getWritableActiveElement();
        if (active) {
            insertText(active, text);
            setStatus('已輸入到目前欄位。', 'success');
            return;
        }

        setStatus('沒有找到可執行的語音指令。可說「幫助」看範例。', 'error');
    }

    function findPageTarget(text) {
        const stripped = stripCommandVerb(text);
        const needsVerb = !/(前往|打開|開啟|切換|去|到|進入)/.test(text);
        for (const page of pageOrder) {
            const profile = pageProfiles[page];
            if ((profile.aliases || []).some(function (alias) {
                const aliasKey = compact(alias);
                const textKey = compact(stripped);
                return textKey === aliasKey || (!needsVerb && textKey.indexOf(aliasKey) !== -1);
            })) {
                return page;
            }
        }
        return null;
    }

    function handleVoicePanelCommand(text) {
        if (/結束錄音|停止錄音|停止連續聆聽|關閉連續聆聽|停止連續|不要一直聽/.test(text)) {
            requestStopListening();
            return true;
        }
        if (/開始錄音|開始收音|連續聆聽|連續聽|持續聽|一直聽|免按語音/.test(text)) {
            startListening();
            setStatus('已開始手動錄音。請手動按「結束錄音」，最低錄音 10 秒。', 'success');
            return true;
        }
        if (/中文語音|切換中文|繁體中文/.test(text)) {
            setVoiceLanguage('zh-TW', '繁體中文');
            return true;
        }
        if (/英文語音|切換英文|英語/.test(text)) {
            setVoiceLanguage('en-US', '英文');
            return true;
        }
        if (/日文語音|日語|切換日文/.test(text)) {
            setVoiceLanguage('ja-JP', '日文');
            return true;
        }
        if (/打開.*語音|開啟.*語音|語音面板|語音輸入/.test(text)) {
            setPanelVisible(true);
            updateHints();
            setStatus('語音面板已開啟。', 'success');
            return true;
        }
        if (/關閉.*語音|收起.*語音|隱藏.*語音/.test(text)) {
            setPanelVisible(false);
            requestStopListening();
            return true;
        }
        if (/開始聽|開始語音|聽我說/.test(text)) {
            startListening();
            return true;
        }
        return false;
    }

    function setVoiceLanguage(lang, label) {
        recognitionLanguage = lang;
        localStorage.setItem('fengbro_voice_lang', lang);
        rebuildRecognition();
        setStatus('語音辨識語言已切換為' + label + '。', 'success');
    }

    function handleMenuCommand(text) {
        if (/打開選單|開啟選單|顯示選單|展開選單|側邊選單/.test(text)) {
            if (typeof window.toggleMobileMenu === 'function') window.toggleMobileMenu();
            else document.querySelector('.sidebar')?.classList.add('open');
            setStatus('已開啟選單。', 'success');
            return true;
        }
        if (/關閉選單|收起選單|隱藏選單/.test(text)) {
            if (typeof window.closeMobileMenu === 'function') window.closeMobileMenu();
            setStatus('已關閉選單。', 'success');
            return true;
        }
        if (/下一(個)?(選單|頁|頁面)|下個(選單|頁面)/.test(text)) {
            navigateRelativePage(1);
            return true;
        }
        if (/上一(個)?(選單|頁|頁面)|上個(選單|頁面)/.test(text)) {
            navigateRelativePage(-1);
            return true;
        }

        const menuItem = findMenuItemByVoice(text);
        if (menuItem && /(前往|打開|開啟|切換|去|到|進入|選單)/.test(text)) {
            stageAction('前往 ' + menuItem.label, function () {
                window.location.href = menuItem.href;
            });
            return true;
        }

        const subnav = findSubnavByVoice(text);
        if (subnav) {
            stageAction('開啟 ' + subnav.label, function () { subnav.element.click(); });
            return true;
        }

        if (/^(點擊|按下|按|開啟|打開|選擇|選取)/.test(text)) {
            const label = stripCommandVerb(text);
            if (clickByTextLoose([label].concat(expandActionLabels(label)))) {
                setStatus('已操作：' + label, 'success');
                return true;
            }
        }
        return false;
    }

    function navigateRelativePage(delta) {
        const page = currentPage();
        const idx = Math.max(0, pageOrder.indexOf(page));
        const next = pageOrder[(idx + delta + pageOrder.length) % pageOrder.length];
        stageAction('前往 ' + pageProfiles[next].title, function () {
            window.location.href = 'index.php?page=' + encodeURIComponent(next);
        });
    }

    function getVoiceMenuItems() {
        const sidebarItems = Array.from(document.querySelectorAll('[data-voice-menu]')).map(function (el) {
            const key = el.getAttribute('data-voice-menu') || '';
            const profile = pageProfiles[key] || {};
            return {
                key: key,
                label: el.getAttribute('data-voice-label') || (el.textContent || '').trim(),
                aliases: unique([key, profile.title].concat(profile.aliases || [])),
                href: el.getAttribute('href') || ('index.php?page=' + key),
                element: el
            };
        });
        if (sidebarItems.length) return sidebarItems;
        return pageOrder.map(function (key) {
            const profile = pageProfiles[key];
            return {
                key: key,
                label: profile.title,
                aliases: unique([key, profile.title].concat(profile.aliases || [])),
                href: 'index.php?page=' + key,
                element: null
            };
        });
    }

    function findMenuItemByVoice(text) {
        const stripped = compact(stripCommandVerb(text));
        return getVoiceMenuItems().find(function (item) {
            return item.aliases.some(function (alias) {
                const key = compact(alias);
                return stripped === key || stripped.indexOf(key) !== -1;
            });
        }) || null;
    }

    function findSubnavByVoice(text) {
        const stripped = compact(stripCommandVerb(text));
        const links = Array.from(document.querySelectorAll('.tools-subnav-link, [role="tab"], .tabs a, .tab-button, .filter-btn, .btn'));
        const found = links.find(function (el) {
            if (isHidden(el)) return false;
            const label = elementVoiceLabel(el);
            if (!label) return false;
            const key = compact(label);
            return key && (stripped === key || stripped.indexOf(key) !== -1);
        });
        return found ? { label: elementVoiceLabel(found), element: found } : null;
    }

    function handleFieldCommand(text) {
        if (/下一欄|下一個欄位|下一格|下一個輸入/.test(text)) {
            if (focusRelativeField(1)) return true;
        }
        if (/上一欄|上一個欄位|上一格|上一個輸入/.test(text)) {
            if (focusRelativeField(-1)) return true;
        }
        if (/清空目前欄位|清除目前欄位|清空這欄|清除這欄/.test(text)) {
            const active = getWritableActiveElement();
            if (active && setElementValue(active, '')) {
                setStatus('已清空目前欄位。', 'success');
                return true;
            }
        }
        const fillMatch = text.match(/^(?:在|把)?(.{1,18}?)(?:輸入|填入|填上|改成|設為|設定為|等於|是)\s*(.+)$/);
        if (fillMatch) {
            const label = fillMatch[1].replace(/欄位|輸入框|表單/g, '').trim();
            let value = fillMatch[2].trim();
            const field = findFieldByLabel(label);
            if (field && /date/i.test(field.type + ' ' + field.name + ' ' + field.id + ' ' + (field.dataset ? field.dataset.field : ''))) {
                value = parseSpokenDate(value) || value;
            }
            if (field && setElementValue(field, value)) {
                setStatus('已在「' + label + '」填入：' + value, 'success');
                return true;
            }
        }

        const appendMatch = text.match(/^(?:在)?(.{1,18}?)(?:追加|加上|補上)\s*(.+)$/);
        if (appendMatch) {
            const label = appendMatch[1].replace(/欄位|輸入框|表單/g, '').trim();
            const field = findFieldByLabel(label);
            if (field && appendToField(field, appendMatch[2].trim())) {
                setStatus('已追加到「' + label + '」。', 'success');
                return true;
            }
        }

        const clearMatch = text.match(/^清(?:空|除)\s*(.+)$/);
        if (clearMatch) {
            const label = clearMatch[1].replace(/欄位|輸入框|表單/g, '').trim();
            const field = findFieldByLabel(label);
            if (field && setElementValue(field, '')) {
                setStatus('已清空欄位：' + label, 'success');
                return true;
            }
        }

        const focusMatch = text.match(/^(?:聚焦|移到|跳到|選到|編輯)\s*(.+)$/);
        if (focusMatch) {
            const label = focusMatch[1].replace(/欄位|輸入框|表單/g, '').trim();
            const field = findFieldByLabel(label);
            if (field) {
                field.focus();
                field.scrollIntoView({ block: 'center', behavior: 'smooth' });
                setStatus('已移到欄位：' + label, 'success');
                return true;
            }
        }

        const selectMatch = text.match(/^(?:選擇|選取)\s*(.+?)\s*(?:為|是|選項)?\s*(.+)?$/);
        if (selectMatch) {
            const field = findFieldByLabel(selectMatch[1]);
            const value = (selectMatch[2] || selectMatch[1] || '').trim();
            if (field && field.tagName === 'SELECT') {
                selectOptionByText(field, value);
                field.dispatchEvent(new Event('change', { bubbles: true }));
                setStatus('已選擇：' + value, 'success');
                return true;
            }
        }

        if (/取消勾選|取消打勾|停用|關閉/.test(text)) {
            const label = text.replace(/取消勾選|取消打勾|停用|關閉/g, '').trim();
            if (toggleCheckboxByLabel(label, false)) return true;
        }
        if (/勾選|打勾|啟用|開啟/.test(text)) {
            const label = text.replace(/勾選|打勾|啟用|開啟/g, '').trim();
            if (toggleCheckboxByLabel(label, true)) return true;
        }
        return false;
    }

    function handleItemCommand(text) {
        const ordinalInfo = parseOrdinal(text);
        if (!ordinalInfo) return false;
        const action = parseItemAction(text);
        if (!action) return false;
        const item = getActionableItem(ordinalInfo.index);
        if (!item) {
            setStatus('找不到指定的項目。', 'error');
            return true;
        }
        const label = itemLabel(item) || ordinalInfo.label + '項目';
        if (action === 'delete') {
            stageAction('刪除 ' + label, function () { clickItemAction(item, ['刪除', 'delete', '移除']); });
            return true;
        }
        if (action === 'edit') {
            clickItemAction(item, ['編輯', 'edit', '修改']);
            setStatus('已開啟編輯：' + label, 'success');
            return true;
        }
        if (action === 'open') {
            clickItemAction(item, ['開啟', '查看', '預覽', '連結', '播放', '下載']);
            setStatus('已開啟：' + label, 'success');
            return true;
        }
        if (action === 'select') {
            const box = item.querySelector('input[type="checkbox"]');
            if (box) {
                box.checked = true;
                box.dispatchEvent(new Event('change', { bubbles: true }));
                setStatus('已選取：' + label, 'success');
                return true;
            }
        }
        return false;
    }

    function toggleCheckboxByLabel(label, checked) {
        const field = findFieldByLabel(label);
        if (!field || field.type !== 'checkbox') return false;
        field.checked = checked;
        field.dispatchEvent(new Event('change', { bubbles: true }));
        setStatus((checked ? '已勾選：' : '已取消勾選：') + label, 'success');
        return true;
    }

    function handleGlobalCommand(text) {
        if (/重新整理|刷新|重整/.test(text)) {
            stageAction('重新整理頁面', function () { window.location.reload(); });
            return true;
        }
        if (/上一頁|返回|回上一頁/.test(text)) {
            stageAction('返回上一頁', function () { window.history.back(); });
            return true;
        }
        if (/往下|下滑|向下|捲下/.test(text)) {
            window.scrollBy({ top: Math.round(window.innerHeight * 0.75), behavior: 'smooth' });
            setStatus('已往下捲動。', 'success');
            return true;
        }
        if (/往上|上滑|向上|捲上/.test(text)) {
            window.scrollBy({ top: -Math.round(window.innerHeight * 0.75), behavior: 'smooth' });
            setStatus('已往上捲動。', 'success');
            return true;
        }
        if (/到頂部|回頂端|最上面/.test(text)) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setStatus('已回到頂端。', 'success');
            return true;
        }
        if (/到底部|最下面/.test(text)) {
            window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
            setStatus('已移到底部。', 'success');
            return true;
        }
        if (/深色|暗色|夜間/.test(text)) {
            if (document.documentElement.getAttribute('data-theme') !== 'dark') toggleDarkMode();
            setStatus('已切換深色模式。', 'success');
            return true;
        }
        if (/淺色|亮色|白天/.test(text)) {
            if (document.documentElement.getAttribute('data-theme') === 'dark') toggleDarkMode();
            setStatus('已切換淺色模式。', 'success');
            return true;
        }
        if (/清空搜尋|清除搜尋/.test(text)) {
            if (fillSearch('')) {
                setStatus('已清空搜尋。', 'success');
                return true;
            }
        }
        if (/搜尋|查詢|找/.test(text)) {
            const keyword = text.replace(/^(搜尋|查詢|找)\s*/, '').trim();
            if (keyword && fillSearch(keyword)) {
                setStatus('已搜尋：' + keyword, 'success');
                return true;
            }
        }
        if (/新增欄位|加欄位|增加欄位/.test(text)) {
            if (clickByText(['新增欄位', '+ 新增欄位'])) {
                setStatus('已新增欄位。', 'success');
                return true;
            }
        }
        if (/新增|建立|加入/.test(text)) {
            const added = invokeFirst(['handleAdd', 'startInlineAdd', 'openModal']) || clickByText(['新增', '新增圖片', '新增文件']);
            if (added) {
                setStatus('已開啟新增。可繼續說欄位內容。', 'success');
                setTimeout(function () { fillFromSpeech(text); }, 80);
                return true;
            }
        }
        if (/儲存|保存|送出|完成/.test(text)) {
            stageAction('儲存目前資料', function () {
                invokeFirst(['saveInlineAdd', 'submitTransaction']) || clickByText(['儲存', '完成', '送出']);
            });
            return true;
        }
        if (/全選/.test(text)) {
            const checkbox = document.getElementById('selectAllCheckbox');
            if (checkbox) {
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                setStatus('已全選。', 'success');
                return true;
            }
        }
        if (/取消全選|取消選取/.test(text)) {
            document.querySelectorAll('.item-checkbox:checked, #selectAllCheckbox:checked').forEach(function (el) {
                el.checked = false;
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
            setStatus('已取消選取。', 'success');
            return true;
        }
        if (/刪除/.test(text)) {
            stageAction('刪除選取或目前項目', function () {
                clickByText(['刪除選取', '批次刪除', '刪除']);
            });
            return true;
        }
        if (/匯出.*ZIP|ZIP.*匯出|導出.*ZIP/.test(text)) {
            stageAction('匯出 ZIP', function () { clickByText(['匯出 ZIP', '導出 ZIP']); });
            return true;
        }
        if (/匯入.*ZIP|ZIP.*匯入|導入.*ZIP/.test(text)) {
            stageAction('匯入 ZIP', function () { clickByText(['匯入 ZIP', '導入 ZIP']); });
            return true;
        }
        if (/匯入.*CSV|CSV.*匯入|導入.*CSV/.test(text)) {
            stageAction('匯入 CSV', function () { clickByText(['匯入 CSV', '導入 CSV']); });
            return true;
        }
        if (/匯出.*CSV|CSV.*匯出|導出.*CSV/.test(text)) {
            stageAction('匯出 CSV', function () { clickByText(['匯出 CSV', '導出 CSV']); });
            return true;
        }
        if (/多選上傳|批次上傳|上傳/.test(text)) {
            stageAction('開啟上傳檔案', function () {
                clickByText(['多選上傳', '一次上傳', '上傳圖片', '上傳音樂', '上傳影片', '上傳文件', '上傳檔案', '上傳']);
            });
            return true;
        }
        if (/卡片/.test(text)) {
            if (clickByText(['卡片', '卡片式'])) {
                setStatus('已切換卡片模式。', 'success');
                return true;
            }
        }
        if (/列表|清單/.test(text)) {
            if (clickByText(['列表', '列表式', '清單'])) {
                setStatus('已切換列表模式。', 'success');
                return true;
            }
        }
        const volumeMatch = text.match(/音量\s*(\d{1,3})/);
        if (volumeMatch && setMediaVolume(Number(volumeMatch[1]))) return true;
        if (/靜音|關靜音/.test(text) && setMediaVolume(0)) return true;
        if (/最大聲|音量最大/.test(text) && setMediaVolume(100)) return true;
        if (/收合播放器|縮小播放器/.test(text) && window.FengbroMedia && window.FengbroMedia.toggleCollapse) {
            window.FengbroMedia.toggleCollapse();
            setStatus('已切換播放器收合。', 'success');
            return true;
        }
        if (/播放/.test(text)) {
            if (window.FengbroMedia && window.FengbroMedia.toggle) {
                window.FengbroMedia.toggle();
                setStatus('已切換播放狀態。', 'success');
                return true;
            }
            const btn = document.querySelector('[onclick*="play"], [onclick*="togglePlay"]');
            if (btn) {
                btn.click();
                setStatus('已嘗試播放。', 'success');
                return true;
            }
        }
        if (/暫停|停止播放/.test(text)) {
            if (window.FengbroMedia && window.FengbroMedia.stop && /停止播放/.test(text)) {
                window.FengbroMedia.stop();
                setStatus('已停止媒體播放。', 'success');
                return true;
            }
            document.querySelectorAll('audio, video').forEach(function (media) { media.pause(); });
            setStatus('已暫停媒體。', 'success');
            return true;
        }
        if (/歌詞/.test(text) && window.FengbroMedia && window.FengbroMedia.toggleLyricsPanel) {
            window.FengbroMedia.toggleLyricsPanel();
            setStatus('已切換歌詞。', 'success');
            return true;
        }
        return false;
    }

    function setMediaVolume(value) {
        const volume = Math.max(0, Math.min(100, value)) / 100;
        const media = Array.from(document.querySelectorAll('audio, video')).find(function (el) {
            return !el.paused || el.currentTime > 0;
        }) || document.querySelector('audio, video');
        if (!media) return false;
        media.volume = volume;
        media.muted = volume === 0;
        setStatus('已設定音量 ' + Math.round(volume * 100) + '。', 'success');
        return true;
    }

    function handlePageCommand(text) {
        const page = currentPage();
        if (handleFoodVoiceCrud(text)) return true;
        if (page === 'subscription') {
            if (/7\s*天|七天|一週|續訂|不續/.test(text)) {
                if (/7\s*天|七天|一週/.test(text) && window.toggleWithin7) window.toggleWithin7();
                if (/不續/.test(text) && window.filterByContinue) window.filterByContinue('0');
                if (/續訂/.test(text) && !/不續/.test(text) && window.filterByContinue) window.filterByContinue('1');
                setStatus('已套用訂閱篩選。', 'success');
                return true;
            }
        }
        if (page === 'food') {
            if (/過期|已過期/.test(text) && clickByText(['已過期'])) return true;
            if (/今天到期/.test(text) && clickByText(['今天到期'])) return true;
            if (/3\s*天|三天/.test(text) && clickByText(['3 天內', '3天內'])) return true;
            if (/低庫存/.test(text) && clickByText(['低庫存'])) return true;
        }
        if (page === 'bank') {
            if (/多選.*(金額|調整|存款)|批次.*(金額|調整|存款)|多家銀行|設定.*存款/.test(text)) {
                if (typeof window.openBankBatchAdjust === 'function') {
                    window.openBankBatchAdjust();
                    setStatus('已開啟多選銀行存款數字設定。', 'success');
                    return true;
                }
            }
            if (/收入/.test(text)) {
                if (window.openTransactionModal) window.openTransactionModal('income');
                fillFromSpeech(text);
                setStatus('已開啟收入。', 'success');
                return true;
            }
            if (/支出|花費/.test(text)) {
                if (window.openTransactionModal) window.openTransactionModal('expense');
                fillFromSpeech(text);
                setStatus('已開啟支出。', 'success');
                return true;
            }
        }
        if (page === 'settings') {
            if (/掃描|儲存空間/.test(text)) return clickByText(['掃描儲存空間']);
            if (/推播|通知/.test(text)) return clickByText(['傳送測試推播', '傳送推播']);
            if (/金鑰|VAPID/i.test(text)) {
                stageAction('產生 VAPID 金鑰', function () { clickByText(['產生 VAPID 金鑰', '產生金鑰']); });
                return true;
            }
        }
        if (page === 'tools') {
            const priceMatch = text.match(/(?:比價|查價格|價格查詢)\s*(.+)$/);
            if (priceMatch && setFieldValue('priceQuery', priceMatch[1].trim())) {
                stageAction('查詢價格：' + priceMatch[1].trim(), function () {
                    if (typeof window.runBigGoLookup === 'function') window.runBigGoLookup();
                    else clickByText(['查詢價格']);
                });
                return true;
            }
            const phoneMatch = text.match(/(?:手機比價|查手機|手機查詢|通路查詢)\s*(.+)$/);
            if (phoneMatch && setFieldValue('phoneQuery', phoneMatch[1].trim())) {
                stageAction('查詢手機通路：' + phoneMatch[1].trim(), function () {
                    if (typeof window.runPhoneCompare === 'function') window.runPhoneCompare();
                    else clickByText(['查詢通路']);
                });
                return true;
            }
            const toolRoutes = [
                { re: /鋒兄tube|tube|youtube\s*頻道|頻道管理/, tool: 'tube', label: '鋒兄tube' },
                { re: /金融|股價|匯率|指數/, tool: 'finance', label: '鋒兄金融' },
                { re: /新聞|便當|台鐵/, tool: 'news', label: '鋒兄新聞' },
                { re: /手動價格|價格紀錄/, tool: 'manual', label: '手動價格' },
                { re: /png|jpeg|圖片轉換|格式轉換/, tool: 'image-convert', label: 'PNG/JPEG 轉換' },
                { re: /圖\s*\+?\s*語音|圖加語音|圖片語音|image\s*voice|ivv/, tool: 'image-voice', label: '圖+語音' },
                { re: /影片合併|合併影片|video\s*merge/, tool: 'video-merge', label: '影片合併' },
                { re: /yt\s*轉檔|b站|bilibili|youtube\s*轉|轉檔/, tool: 'yt-bili', label: 'YT/B站轉檔' },
                { re: /手機比價|手機通路|地標|傑昇/, tool: 'phone', label: '手機比價' },
                { re: /比價|biggo/, tool: 'price', label: '鋒兄比價' }
            ];
            for (let ti = 0; ti < toolRoutes.length; ti++) {
                if (toolRoutes[ti].re.test(text)) {
                    const route = toolRoutes[ti];
                    stageAction('開啟' + route.label, function () {
                        window.location.href = 'index.php?page=tools&tool=' + encodeURIComponent(route.tool);
                    });
                    return true;
                }
            }
        }
        return false;
    }

    function fillFromSpeech(text) {
        const page = currentPage();
        const profile = pageProfiles[page] || {};
        const fields = profile.fields || {};
        let changed = false;

        if (page === 'subscription' && fillSubscriptionVoicePayload(text)) {
            return true;
        }

        Object.keys(fields).forEach(function (field) {
            const aliases = fields[field];
            for (const alias of aliases) {
                const value = extractValueAfterAlias(text, alias);
                if (value !== null) {
                    if (setFieldValue(field, normalizeValueForField(field, value, text))) changed = true;
                    break;
                }
            }
        });

        if (!changed) {
            const guessedName = guessNameFromCommand(text, page);
            if (guessedName) changed = setFieldValue(bestNameField(page), guessedName) || changed;
        }

        if (/到期\s*\d+\s*天|(\d+)\s*天後/.test(text)) {
            const days = parseInt((text.match(/到期\s*(\d+)\s*天/) || text.match(/(\d+)\s*天後/) || [])[1], 10);
            if (!isNaN(days)) changed = setFieldValue(bestDateField(page), addDays(days)) || changed;
        }
        if (/下月|下個月/.test(text)) {
            changed = setFieldValue(bestDateField(page), addMonths(1)) || changed;
        }
        if (/今天/.test(text) && /(日期|到期|付款)/.test(text)) {
            changed = setFieldValue(bestDateField(page), addDays(0)) || changed;
        }
        if (/明天/.test(text)) {
            changed = setFieldValue(bestDateField(page), addDays(1)) || changed;
        }
        if (/不續/.test(text)) changed = setFieldValue('continue', false) || changed;
        if (/續訂/.test(text) && !/不續/.test(text)) changed = setFieldValue('continue', true) || changed;

        if (changed) {
            setStatus('已填入語音內容。請確認後再儲存。', 'success');
        }
        return changed;
    }

    function handleFoodVoiceCrud(text) {
        const page = currentPage();
        const targetsFood = /鋒兄食品|食品|食材|商品庫存|商品|庫存/.test(text);
        if (page !== 'food' && targetsFood && /新增|加入|建立|記錄|搜尋|查詢|找/.test(text)) {
            stageAction('前往鋒兄食品處理語音指令', function () {
                try {
                    sessionStorage.setItem('fengbro_pending_food_voice', text);
                } catch (error) {}
                window.location.href = 'index.php?page=food';
            });
            return true;
        }
        if (page !== 'food') return false;
        if (!targetsFood && !/新增|加入|建立|搜尋|查詢|找|刪除|移除|複製|編輯|修改|更新|數量|加|減|補貨|用掉/.test(text)) return false;

        if (/搜尋|查詢|找/.test(text)) {
            const query = extractFoodName(text) || text.replace(/鋒兄食品|食品|食材|商品庫存|商品|搜尋|查詢|找/g, '').trim();
            if (query && voiceFoodSetSearch(query)) {
                setStatus('已搜尋食品：' + query, 'success');
                return true;
            }
        }

        if (/新增|加入|建立|記錄|買了|補一筆|新增一筆/.test(text)) {
            const name = extractFoodName(text);
            if (!name) {
                setStatus('請說出要新增的食品名稱，例如：新增食品牛奶數量2到期7天後。', 'info');
                return true;
            }
            stageAction('新增食品：' + name, function () {
                fillFoodModalFromVoice(text, null);
                submitFoodModal();
            });
            return true;
        }

        const item = findFoodItemByVoice(text, extractFoodName(text));
        if (!item) return false;
        const id = item.dataset.id;
        const label = item.dataset.name || itemLabel(item);

        if (/刪除|移除|清除/.test(text)) {
            stageAction('刪除食品：' + label, function () {
                if (typeof window.deleteItem === 'function') window.deleteItem(id);
                else clickItemAction(item, ['刪除', '移除']);
            });
            return true;
        }

        if (/複製|拷貝|再來一筆/.test(text)) {
            stageAction('複製食品並進入編輯：' + label, function () {
                if (typeof window.duplicateItem === 'function') window.duplicateItem(id);
                else clickItemAction(item, ['複製']);
            });
            return true;
        }

        const delta = extractFoodAmountDelta(text);
        if (delta !== 0 && typeof window.adjustFoodAmount === 'function') {
            stageAction((delta > 0 ? '增加' : '減少') + '食品數量：' + label + ' ' + Math.abs(delta), function () {
                window.adjustFoodAmount(id, delta);
            });
            return true;
        }

        if (/編輯|修改|更新|改成|改為|設定|變成/.test(text)) {
            stageAction('更新食品：' + label, function () {
                fillFoodModalFromVoice(text, id);
                submitFoodModal();
            });
            return true;
        }

        return false;
    }

    function processPendingFoodVoice() {
        if (currentPage() !== 'food') return;
        let text = '';
        try {
            text = sessionStorage.getItem('fengbro_pending_food_voice') || '';
            if (text) sessionStorage.removeItem('fengbro_pending_food_voice');
        } catch (error) {}
        if (text) window.setTimeout(function () { handleFoodVoiceCrud(text); }, 120);
    }

    function fillFoodModalFromVoice(text, id) {
        if (id && typeof window.editItem === 'function') window.editItem(id);
        else if (typeof window.openModal === 'function') window.openModal();
        else if (typeof window.handleAdd === 'function') window.handleAdd();

        window.setTimeout(function () {
            const payload = extractFoodVoicePayload(text);
            if (id) setFieldValue('id', id);
            if (payload.name) setFieldValue('name', payload.name);
            if (payload.amount !== null) setFieldValue('amount', payload.amount);
            if (payload.price !== null) setFieldValue('price', payload.price);
            if (payload.shop) setFieldValue('shop', payload.shop);
            if (payload.todate) setFieldValue('todate', payload.todate);
            if (payload.photo) setFieldValue('photo', payload.photo);
        }, id ? 260 : 80);
    }

    function submitFoodModal() {
        window.setTimeout(function () {
            const form = document.getElementById('itemForm');
            if (!form) return;
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }, 420);
    }

    function extractFoodVoicePayload(text) {
        return {
            name: extractFoodName(text),
            amount: extractFoodAmount(text),
            price: extractFoodPrice(text),
            shop: extractFoodShop(text),
            todate: extractFoodDate(text),
            photo: extractFoodPhoto(text)
        };
    }

    function extractFoodName(text) {
        const match = text.match(/(?:叫做|名稱是|名字是|品名是|食品是|商品是|新增(?:食品|商品)?|加入(?:食品|商品)?|建立(?:食品|商品)?|搜尋|查詢|找)\s*([\s\S]+?)(?=\s*(?:數量|庫存|價格|價錢|金額|商店|店家|店名|到期|有效|日期|時間|圖片|照片|連結|網址|加|減|改成|改為|設定|變成|$))/);
        if (match && match[1]) return cleanupFoodText(match[1]);

        const datasetName = (text.match(/(?:刪除|移除|複製|編輯|修改|更新)\s*([\s\S]+?)(?=\s*(?:數量|庫存|價格|商店|到期|$))/) || [])[1];
        return cleanupFoodText(datasetName || '');
    }

    function cleanupFoodText(value) {
        return String(value || '')
            .replace(/鋒兄食品|食品|食材|商品庫存|商品/g, '')
            .replace(/^(一筆|一個|這個|那個)\s*/, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function extractFoodAmount(text) {
        const match = text.match(/(?:數量|庫存|還有|有)\s*([0-9一二三四五六七八九十兩]+)/);
        if (!match) return null;
        return voiceNumberToValue(match[1]);
    }

    function extractFoodAmountDelta(text) {
        const match = text.match(/(?:數量)?\s*(加|增加|補貨|減|減少|用掉)\s*([0-9一二三四五六七八九十兩]+)?/);
        if (!match) return 0;
        const amount = match[2] ? voiceNumberToValue(match[2]) : 1;
        return /減|用掉/.test(match[1]) ? -amount : amount;
    }

    function extractFoodPrice(text) {
        const match = text.match(/(?:價格|價錢|金額|單價)\s*([0-9]+)/);
        return match ? Number(match[1]) : null;
    }

    function extractFoodShop(text) {
        const match = text.match(/(?:商店|店家|店名|購買於|買自)\s*([\s\S]+?)(?=\s*(?:數量|庫存|價格|到期|有效|日期|圖片|照片|$))/);
        return match ? cleanupFoodText(match[1]) : '';
    }

    function extractFoodPhoto(text) {
        const match = text.match(/(?:圖片|照片|圖檔|網址|連結)\s*(https?:\/\/\S+)/i);
        return match ? match[1] : '';
    }

    function extractFoodDate(text) {
        const dayMatch = text.match(/(?:到期|有效期限|保存|日期|時間).*?([0-9一二三四五六七八九十兩]+)\s*天(?:後|內)?/);
        if (dayMatch) return addDays(voiceNumberToValue(dayMatch[1]));
        const parsed = parseSpokenDate(text);
        return parsed || '';
    }

    function voiceNumberToValue(value) {
        const raw = String(value || '').trim();
        if (/^\d+$/.test(raw)) return Number(raw);
        const map = { 零: 0, 一: 1, 二: 2, 兩: 2, 三: 3, 四: 4, 五: 5, 六: 6, 七: 7, 八: 8, 九: 9 };
        if (raw === '十') return 10;
        const ten = raw.match(/^([一二兩三四五六七八九])?十([一二兩三四五六七八九])?$/);
        if (ten) return (ten[1] ? map[ten[1]] : 1) * 10 + (ten[2] ? map[ten[2]] : 0);
        return map[raw] || spokenNumber(raw) || 0;
    }

    function findFoodItemByVoice(text, wantedName) {
        const items = uniqueElements(Array.from(document.querySelectorAll('[data-food-item]')).filter(function (item) {
            return !isHidden(item) && item.dataset && item.dataset.id;
        }));
        if (!items.length) return null;

        const ordinal = parseOrdinal(text);
        if (ordinal) return ordinal.index === -1 ? items[items.length - 1] : items[ordinal.index] || null;

        const wanted = compact(wantedName || '');
        if (wanted) {
            const exact = items.find(function (item) { return compact(item.dataset.name || '') === wanted; });
            if (exact) return exact;
            const partial = items.find(function (item) { return compact(item.dataset.name || '').indexOf(wanted) !== -1 || wanted.indexOf(compact(item.dataset.name || '')) !== -1; });
            if (partial) return partial;
        }

        const source = compact(text);
        const mentioned = items.find(function (item) {
            const name = compact(item.dataset.name || '');
            return name && source.indexOf(name) !== -1;
        });
        if (mentioned) return mentioned;

        return null;
    }

    function voiceFoodSetSearch(query) {
        const search = document.getElementById('foodSearchInput') || document.querySelector('input[type="search"], input[id*="food"][id*="Search" i]');
        if (!search) return false;
        search.value = query;
        search.dispatchEvent(new Event('input', { bubbles: true }));
        search.dispatchEvent(new Event('change', { bubbles: true }));
        if (typeof window.filterFoods === 'function') window.filterFoods();
        search.focus();
        return true;
    }

    function handleCrossPageSubscriptionCommand(text) {
        if (currentPage() === 'subscription') return false;
        if (!/(新增|建立|加入|新增一筆|一筆資料)/.test(text)) return false;
        if (!isSubscriptionVoiceTarget(text)) return false;
        if (!extractSubscriptionServiceName(text) && !parseSpokenDate(text)) return false;

        stageAction('前往鋒兄訂閱並新增資料', function () {
            try {
                sessionStorage.setItem('fengbro_pending_subscription_voice', text);
            } catch (error) {}
            window.location.href = 'index.php?page=subscription';
        });
        return true;
    }

    function isSubscriptionVoiceTarget(text) {
        const profile = pageProfiles.subscription || {};
        return unique(['鋒兄訂閱', '訂閱', 'subscription'].concat(profile.aliases || [])).some(function (alias) {
            const key = compact(alias);
            const source = compact(text);
            return key && source.indexOf(key) !== -1;
        });
    }

    function processPendingSubscriptionVoice() {
        if (currentPage() !== 'subscription') return;
        let text = '';
        try {
            text = sessionStorage.getItem('fengbro_pending_subscription_voice') || '';
            if (text) sessionStorage.removeItem('fengbro_pending_subscription_voice');
        } catch (error) {}
        if (!text) return;

        const added = invokeFirst(['handleAdd', 'startInlineAdd', 'openModal']) || clickByText(['新增', '新增訂閱']);
        window.setTimeout(function () {
            if (added) fillFromSpeech(text);
            else setStatus('已到鋒兄訂閱，請先按新增後再說一次資料內容。', 'info');
        }, added ? 120 : 0);
    }

    function fillSubscriptionVoicePayload(text) {
        if (!/(新增|建立|加入|新增一筆|一筆資料)/.test(text)) return false;

        const serviceName = extractSubscriptionServiceName(text);
        const spokenDate = parseSpokenDate(text);
        const timeWord = extractSubscriptionTimeWord(text);
        let changed = false;

        if (!serviceName && !spokenDate) return false;
        if (serviceName) changed = setFieldValue('name', serviceName) || changed;
        if (spokenDate) changed = setFieldValue('nextdate', spokenDate) || changed;
        if (timeWord) changed = setFieldValue('note', '鋒兄語音 ' + timeWord) || changed;

        if (changed) {
            setStatus('已解析訂閱語音：服務名稱、日期與備註已填入。請確認後再儲存。', 'success');
        }
        return changed;
    }

    function extractSubscriptionServiceName(text) {
        const match = text.match(/(?:服務名稱|服務|名稱|叫做|名叫|叫)\s*(?:是|為|:|：)?\s*([\s\S]+?)(?=\s*(?:日期|時間|下次付款|付款日期|到期|備註|價格|金額|網站|網址|帳號)|$)/);
        if (!match) return '';
        return cleanupSubscriptionServiceName(match[1]);
    }

    function cleanupSubscriptionServiceName(value) {
        return String(value || '')
            .replace(/^(?:一筆)?(?:資料|訂閱)\s*/, '')
            .replace(/^(?:是|為|叫做|名叫|叫|:|：)\s*/, '')
            .replace(/\s*(?:上午|早上|中午|下午|晚上|晚間|凌晨)\s*$/, '')
            .replace(/[，,。.\s]+$/g, '')
            .trim();
    }

    function extractSubscriptionTimeWord(text) {
        const match = text.match(/(上午|早上|中午|下午|晚上|晚間|凌晨)/);
        if (!match) return '';
        return match[1] === '早上' ? '上午' : match[1];
    }

    function extractValueAfterAlias(text, alias) {
        const idx = text.indexOf(alias);
        if (idx === -1) return null;
        let rest = text.slice(idx + alias.length).trim();
        rest = rest.replace(/^(是|為|等於|:|：)/, '').trim();
        if (!rest) return '';
        const stopWords = ['服務', '服務名稱', '名稱', '網站', '網址', '帳號', '價格', '金額', '費用', '幣別', '貨幣', '日期', '時間', '到期', '備註', '筆記', '分類', '類別', '數量', '庫存', '商店', '店家', '內容', '標題', '歌詞', '語言', '封面', '參考', '來源', '地址', '卡號', '存款', '提款', '轉帳'];
        let end = rest.length;
        stopWords.forEach(function (word) {
            const pos = rest.indexOf(word);
            if (pos > 0 && pos < end) end = pos;
        });
        return rest.slice(0, end).trim();
    }

    function normalizeValueForField(field, value, fullText) {
        const raw = String(value || '').trim();
        if (field === 'price' || field === 'quantity' || field === 'deposit' || field === 'withdrawals' || field === 'transfer' || field === 'transactionAmount') {
            const number = raw.match(/-?\d+(\.\d+)?/);
            return number ? number[0] : raw;
        }
        if (/date|expiry|nextdate|todate/i.test(field)) {
            const parsed = parseSpokenDate(raw + ' ' + fullText);
            if (parsed) return parsed;
            if (/明天/.test(raw + fullText)) return addDays(1);
            const days = (raw + fullText).match(/(\d+)\s*天/);
            if (days) return addDays(parseInt(days[1], 10));
            if (/下月|下個月/.test(raw + fullText)) return addMonths(1);
            if (/今天/.test(raw + fullText)) return addDays(0);
        }
        if (field === 'currency') {
            if (/日幣|日圓|JPY/i.test(raw)) return 'JPY';
            if (/美金|美元|USD/i.test(raw)) return 'USD';
            if (/台幣|台元|TWD/i.test(raw)) return 'TWD';
        }
        return raw;
    }

    function guessNameFromCommand(text, page) {
        const cleaned = text
            .replace(/^(新增|建立|加入)\s*/, '')
            .replace(/(訂閱|食品|食材|筆記|常用|圖片|影片|音樂|文件|播客|銀行|例行)/g, '')
            .trim();
        if (!cleaned || cleaned === text) return '';
        return extractUntilFieldWord(cleaned);
    }

    function extractUntilFieldWord(value) {
        const markers = ['價格', '金額', '數量', '到期', '日期', '時間', '分類', '備註', '內容', '網站', '帳號', '語言', '檔案', '封面', '參考'];
        let end = value.length;
        markers.forEach(function (marker) {
            const pos = value.indexOf(marker);
            if (pos > 0 && pos < end) end = pos;
        });
        return value.slice(0, end).trim();
    }

    function bestNameField(page) {
        return page === 'notes' ? 'title' : 'name';
    }

    function bestDateField(page) {
        if (page === 'subscription') return 'nextdate';
        if (page === 'food') return 'todate';
        return 'date';
    }

    function addDays(days) {
        const date = new Date();
        date.setDate(date.getDate() + Number(days || 0));
        return formatLocalDate(date);
    }

    function addMonths(months) {
        const date = new Date();
        date.setMonth(date.getMonth() + Number(months || 0));
        return formatLocalDate(date);
    }

    function formatLocalDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function parseSpokenDate(text) {
        const raw = normalize(text);
        const now = new Date();
        if (/大後天/.test(raw)) return addDays(3);
        if (/後天/.test(raw)) return addDays(2);
        if (/明天/.test(raw)) return addDays(1);
        if (/今天/.test(raw)) return addDays(0);
        if (/昨天/.test(raw)) return addDays(-1);
        if (/月底|本月底/.test(raw)) return formatLocalDate(new Date(now.getFullYear(), now.getMonth() + 1, 0));
        if (/下月底|下個月底/.test(raw)) return formatLocalDate(new Date(now.getFullYear(), now.getMonth() + 2, 0));

        const yearMonthDay = raw.match(/(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*(?:日|號)?/);
        if (yearMonthDay) return formatLocalDate(new Date(Number(yearMonthDay[1]), Number(yearMonthDay[2]) - 1, Number(yearMonthDay[3])));

        const slashDate = raw.match(/(\d{4})[\/.-](\d{1,2})[\/.-](\d{1,2})/);
        if (slashDate) return formatLocalDate(new Date(Number(slashDate[1]), Number(slashDate[2]) - 1, Number(slashDate[3])));

        const nextMonthDay = raw.match(/下(?:個)?月\s*(\d{1,2}|[一二三四五六七八九十兩]+)\s*(?:日|號)/);
        if (nextMonthDay) return formatLocalDate(new Date(now.getFullYear(), now.getMonth() + 1, spokenNumber(nextMonthDay[1])));

        const monthDay = raw.match(/(\d{1,2}|[一二三四五六七八九十兩]+)\s*月\s*(\d{1,2}|[一二三四五六七八九十兩]+)\s*(?:日|號)?/);
        if (monthDay) {
            const month = spokenNumber(monthDay[1]);
            const day = spokenNumber(monthDay[2]);
            const date = new Date(now.getFullYear(), month - 1, day);
            if (date < stripTime(now)) date.setFullYear(date.getFullYear() + 1);
            return formatLocalDate(date);
        }

        const dayOnly = raw.match(/(?:本月|這個月)?\s*(\d{1,2}|[一二三四五六七八九十兩]+)\s*(?:日|號)/);
        if (dayOnly) {
            const date = new Date(now.getFullYear(), now.getMonth(), spokenNumber(dayOnly[1]));
            if (date < stripTime(now)) date.setMonth(date.getMonth() + 1);
            return formatLocalDate(date);
        }

        const dayOffset = raw.match(/(\d+|[一二三四五六七八九十兩]+)\s*天(?:後|內)?/);
        if (dayOffset) return addDays(spokenNumber(dayOffset[1]));
        const weekOffset = raw.match(/(\d+|[一二三四五六七八九十兩]+)\s*(?:週|周|星期)(?:後|內)/);
        if (weekOffset) return addDays(spokenNumber(weekOffset[1]) * 7);
        if (/下週|下周|下星期/.test(raw)) return addDays(7);
        if (/下月|下個月/.test(raw)) return addMonths(1);
        if (/明年|下一年/.test(raw)) {
            const date = new Date();
            date.setFullYear(date.getFullYear() + 1);
            return formatLocalDate(date);
        }

        const weekdayMatch = raw.match(/下?(?:週|周|星期|禮拜)([一二三四五六日天])/);
        if (weekdayMatch) {
            const weekdays = { 日: 0, 天: 0, 一: 1, 二: 2, 三: 3, 四: 4, 五: 5, 六: 6 };
            const target = weekdays[weekdayMatch[1]];
            let delta = (target - now.getDay() + 7) % 7;
            if (delta === 0 || raw.indexOf('下') !== -1) delta += 7;
            return addDays(delta);
        }
        return '';
    }

    function stripTime(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function spokenNumber(value) {
        const text = String(value || '').trim();
        if (/^\d+$/.test(text)) return Number(text);
        const digits = { 零: 0, 一: 1, 二: 2, 兩: 2, 三: 3, 四: 4, 五: 5, 六: 6, 七: 7, 八: 8, 九: 9 };
        if (text === '十') return 10;
        const ten = text.match(/^([一二三四五六七八九兩])?十([一二三四五六七八九])?$/);
        if (ten) return (ten[1] ? digits[ten[1]] : 1) * 10 + (ten[2] ? digits[ten[2]] : 0);
        return digits[text] || Number(text) || 0;
    }

    function setFieldValue(field, value) {
        const selectors = [
            '#' + cssEscape(field),
            '[name="' + cssEscape(field) + '"]',
            '[data-field="' + cssEscape(field) + '"]'
        ];
        let el = null;
        for (const selector of selectors) {
            el = Array.from(document.querySelectorAll(selector)).find(isUsableField);
            if (el) break;
        }
        if (!el) return false;
        if (el.type === 'checkbox') {
            el.checked = !!value;
        } else if (el.tagName === 'SELECT') {
            selectOptionByText(el, value);
        } else {
            el.value = value;
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.focus({ preventScroll: true });
        return true;
    }

    function cssEscape(value) {
        if (window.CSS && CSS.escape) return CSS.escape(String(value));
        return String(value).replace(/"/g, '\\"');
    }

    function isUsableField(el) {
        if (!el) return false;
        if (el.disabled || el.readOnly) return false;
        if (el.type === 'hidden' || el.type === 'file') return false;
        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function selectOptionByText(select, value) {
        const wanted = String(value || '').toLowerCase();
        const option = Array.from(select.options).find(function (opt) {
            return opt.value.toLowerCase() === wanted || opt.textContent.toLowerCase().indexOf(wanted) !== -1;
        });
        if (option) select.value = option.value;
    }

    function fillSearch(keyword) {
        const search = Array.from(document.querySelectorAll('input[type="search"], input[placeholder*="搜尋"], input[placeholder*="搜索"], input[id*="search" i], input[name*="search" i]')).find(isUsableField);
        if (!search) return false;
        search.value = keyword;
        search.dispatchEvent(new Event('input', { bubbles: true }));
        search.dispatchEvent(new Event('change', { bubbles: true }));
        search.focus();
        return true;
    }

    function findFieldByLabel(label) {
        const wanted = compact(label);
        if (!wanted) return null;
        const fields = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([type="file"]), textarea, select')).filter(isUsableField);
        const direct = fields.find(function (field) {
            return getFieldAliases(field).some(function (alias) {
                const key = compact(alias);
                return key && (key === wanted || key.indexOf(wanted) !== -1 || wanted.indexOf(key) !== -1);
            });
        });
        if (direct) return direct;
        const pageFields = (pageProfiles[currentPage()] || {}).fields || {};
        const fieldName = Object.keys(pageFields).find(function (name) {
            return pageFields[name].some(function (alias) { return compact(alias) === wanted || compact(alias).indexOf(wanted) !== -1; });
        });
        return fieldName ? fields.find(function (field) {
            return field.name === fieldName || field.id === fieldName || field.dataset.field === fieldName;
        }) || null : null;
    }

    function getFieldAliases(field) {
        const aliases = [
            field.name,
            field.id,
            field.dataset ? field.dataset.field : '',
            field.placeholder,
            field.getAttribute('aria-label'),
            field.getAttribute('title')
        ];
        if (field.id) {
            const label = document.querySelector('label[for="' + cssEscape(field.id) + '"]');
            if (label) aliases.push(label.textContent);
        }
        const wrappingLabel = field.closest('label');
        if (wrappingLabel) aliases.push(wrappingLabel.textContent);
        const formGroup = field.closest('.form-group, .inline-field, .field, td, .modal-body, .card');
        if (formGroup) {
            const nearLabel = formGroup.querySelector('label, .field-label, .form-label, th');
            if (nearLabel) aliases.push(nearLabel.textContent);
        }
        return aliases.filter(Boolean).map(function (alias) {
            return String(alias).replace(/[*：:]/g, '').trim();
        });
    }

    function setElementValue(el, value) {
        if (!el) return false;
        if (el.type === 'checkbox') {
            el.checked = /^(是|要|勾選|開|true|1|yes)$/i.test(value);
        } else if (el.tagName === 'SELECT') {
            selectOptionByText(el, value);
        } else {
            el.value = value;
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.focus({ preventScroll: true });
        return true;
    }

    function appendToField(el, value) {
        if (!el || el.type === 'checkbox' || el.tagName === 'SELECT') return false;
        const prefix = el.value && !/\s$/.test(el.value) ? ' ' : '';
        el.value = (el.value || '') + prefix + value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.focus({ preventScroll: true });
        return true;
    }

    function focusRelativeField(delta) {
        const fields = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([type="file"]), textarea, select')).filter(isUsableField);
        if (!fields.length) return false;
        const active = document.activeElement;
        const current = Math.max(0, fields.indexOf(active));
        const next = fields[(current + delta + fields.length) % fields.length];
        next.focus();
        next.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setStatus(delta > 0 ? '已移到下一欄。' : '已移到上一欄。', 'success');
        return true;
    }

    function parseOrdinal(text) {
        if (/最後|最後一/.test(text)) return { index: -1, label: '最後一個' };
        const compactText = compact(text);
        const map = [
            ['第一', 0], ['第1', 0], ['一號', 0],
            ['第二', 1], ['第2', 1], ['二號', 1],
            ['第三', 2], ['第3', 2], ['三號', 2],
            ['第四', 3], ['第4', 3], ['四號', 3],
            ['第五', 4], ['第5', 4], ['五號', 4],
            ['第六', 5], ['第6', 5],
            ['第七', 6], ['第7', 6],
            ['第八', 7], ['第8', 7],
            ['第九', 8], ['第9', 8],
            ['第十', 9], ['第10', 9]
        ];
        const found = map.find(function (item) { return compactText.indexOf(item[0]) !== -1; });
        if (found) return { index: found[1], label: found[0] };
        const numeric = compactText.match(/第(\d+)(筆|個|項|列|張|首|部)?/);
        if (numeric) return { index: Math.max(0, Number(numeric[1]) - 1), label: '第' + numeric[1] + '個' };
        return null;
    }

    function parseItemAction(text) {
        if (/刪除|移除|丟掉/.test(text)) return 'delete';
        if (/編輯|修改|更改/.test(text)) return 'edit';
        if (/選取|勾選|選擇/.test(text)) return 'select';
        if (/開啟|打開|查看|預覽|播放|下載|進入/.test(text)) return 'open';
        return null;
    }

    function getActionableItem(index) {
        const selectors = [
            'tr[data-id]',
            '[data-food-item]',
            '.sub-card',
            '.mobile-card',
            '.note-card',
            '.image-card',
            '.video-card',
            '.music-card',
            '.document-card',
            '.podcast-card',
            '.metric-card',
            '.finance-card',
            '.tube-channel-card',
            '.card'
        ];
        const items = uniqueElements(Array.from(document.querySelectorAll(selectors.join(','))).filter(function (el) {
            return !isHidden(el) && !el.classList.contains('inline-add-row') && !el.closest('#fengbroVoicePanel');
        }));
        if (!items.length) return null;
        return index === -1 ? items[items.length - 1] : items[index] || null;
    }

    function uniqueElements(items) {
        const seen = new Set();
        return items.filter(function (item) {
            if (seen.has(item)) return false;
            seen.add(item);
            return true;
        });
    }

    function itemLabel(item) {
        return (item.getAttribute('data-name') ||
            textFromSelector(item, '.card-title, .mobile-card-title, .sub-card-title, h3, h4, strong') ||
            (item.textContent || '').trim().slice(0, 24)).replace(/\s+/g, ' ');
    }

    function textFromSelector(root, selector) {
        const el = root.querySelector(selector);
        return el ? (el.textContent || '').trim() : '';
    }

    function clickItemAction(item, labels) {
        const controls = Array.from(item.querySelectorAll('button, a, [role="button"], .card-edit-btn, .card-delete-btn, span[onclick]'));
        const found = controls.find(function (control) {
            if (isHidden(control)) return false;
            const haystack = compact(elementVoiceLabel(control) + ' ' + (control.getAttribute('onclick') || '') + ' ' + control.className);
            return labels.some(function (label) { return haystack.indexOf(compact(label)) !== -1; });
        }) || controls.find(function (control) { return !isHidden(control); });
        if (found) {
            found.click();
            return true;
        }
        if (labels.some(function (label) { return /開啟|查看|預覽|播放|下載/.test(label); })) {
            const link = item.matches('a[href]') ? item : item.querySelector('a[href]');
            if (link) {
                link.click();
                return true;
            }
        }
        item.click();
        return true;
    }

    function getWritableActiveElement() {
        const el = document.activeElement;
        if (!el) return null;
        if (el.matches && el.matches('input:not([type="hidden"]):not([type="file"]), textarea')) return el;
        if (el.isContentEditable) return el;
        return null;
    }

    function insertText(el, text) {
        if (el.isContentEditable) {
            el.textContent = (el.textContent || '') + text;
            return;
        }
        const start = typeof el.selectionStart === 'number' ? el.selectionStart : el.value.length;
        const end = typeof el.selectionEnd === 'number' ? el.selectionEnd : el.value.length;
        el.value = el.value.slice(0, start) + text + el.value.slice(end);
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function invokeFirst(names) {
        for (const name of names) {
            if (typeof window[name] === 'function') {
                window[name]();
                return true;
            }
        }
        return false;
    }

    function clickByText(labels) {
        const candidates = Array.from(document.querySelectorAll('button, a, label'));
        for (const label of labels) {
            const found = candidates.find(function (el) {
                if (isHidden(el)) return false;
                const text = (el.textContent || '').trim();
                const title = el.getAttribute('title') || '';
                return text.indexOf(label) !== -1 || title.indexOf(label) !== -1;
            });
            if (found) {
                found.click();
                return true;
            }
        }
        return false;
    }

    function clickByTextLoose(labels) {
        const candidates = Array.from(document.querySelectorAll('button, a, label, [role="button"], [role="tab"], summary'));
        for (const label of labels) {
            const key = compact(label);
            if (!key) continue;
            const found = candidates.find(function (el) {
                if (isHidden(el)) return false;
                const text = compact(elementVoiceLabel(el));
                return text && (text === key || text.indexOf(key) !== -1 || key.indexOf(text) !== -1);
            });
            if (found) {
                found.click();
                return true;
            }
        }
        return false;
    }

    function elementVoiceLabel(el) {
        return [
            el.getAttribute('data-voice-label'),
            el.getAttribute('aria-label'),
            el.getAttribute('title'),
            el.getAttribute('value'),
            el.textContent
        ].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    }

    function expandActionLabels(label) {
        const base = [label];
        commonActionLabels.forEach(function (action) {
            if (label.indexOf(action) !== -1) base.push(action);
        });
        return base;
    }

    function getVisibleActionLabels() {
        return unique(Array.from(document.querySelectorAll('button, a, [role="button"], [role="tab"]')).filter(function (el) {
            return !isHidden(el);
        }).map(elementVoiceLabel).filter(function (label) {
            return label && label.length <= 16 && !/^https?:/i.test(label);
        })).slice(0, 12);
    }

    function isHidden(el) {
        const rect = el.getBoundingClientRect();
        return rect.width === 0 || rect.height === 0 || getComputedStyle(el).visibility === 'hidden' || getComputedStyle(el).display === 'none';
    }

    window.FengbroVoiceInput = {
        open: function () {
            createUI();
            setPanelVisible(true);
            updateHints();
            setStatus('可以直接說「前往訂閱」、「搜尋牛奶」、「新增食品」或「在名稱輸入牛奶」。', 'info');
        },
        listen: startListening,
        toggle: toggleListening,
        run: handleCommand,
        stop: requestStopListening
    };

    createUI();
    window.setTimeout(processPendingSubscriptionVoice, 120);
    window.setTimeout(processPendingFoodVoice, 160);
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
    const PLAYER_COLLAPSED_KEY = 'fengbro_media_player_collapsed';
    let shell;
    let titleEl;
    let metaEl;
    let audioEl;
    let videoEl;
    let thumbEl;
    let closeBtn;
    let toggleBtn;
    let collapseBtn;
    let lyricsBtn;
    let downloadBtn;
    let lyricsPanelEl;
    let lyricsTitleEl;
    let lyricsContentEl;
    let lyricsCloseBtn;
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
        collapseBtn = document.getElementById('globalMediaCollapse');
        lyricsBtn = document.getElementById('globalMediaLyricsToggle');
        downloadBtn = document.getElementById('globalMediaDownload');
        lyricsPanelEl = document.getElementById('globalLyricsPanel');
        lyricsTitleEl = document.getElementById('globalLyricsTitle');
        lyricsContentEl = document.getElementById('globalLyricsContent');
        lyricsCloseBtn = document.getElementById('globalLyricsClose');
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

    function readCollapsedPreference() {
        return localStorage.getItem(PLAYER_COLLAPSED_KEY) === '1';
    }

    function writeCollapsedPreference(collapsed) {
        localStorage.setItem(PLAYER_COLLAPSED_KEY, collapsed ? '1' : '0');
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

    function pauseCompetingMedia(activeEl) {
        document.querySelectorAll('audio, video').forEach(function (mediaEl) {
            if (!mediaEl || mediaEl === activeEl) return;
            try {
                mediaEl.pause();
            } catch (error) {
                // ignore pause errors from detached or unsupported elements
            }
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

    function applyLyricsState(state) {
        getElements();
        const hasLyrics = !!(state && state.kind === 'audio' && state.mediaType === 'music' && state.lyrics);
        if (lyricsBtn) {
            lyricsBtn.style.display = hasLyrics ? 'inline-flex' : 'none';
            lyricsBtn.classList.toggle('active', !!(hasLyrics && state.lyricsOpen));
            lyricsBtn.title = hasLyrics ? (state.lyricsOpen ? '隱藏歌詞' : '顯示歌詞') : '顯示歌詞';
        }
        if (!lyricsPanelEl || !lyricsTitleEl || !lyricsContentEl) return;
        if (!hasLyrics) {
            lyricsPanelEl.style.display = 'none';
            lyricsTitleEl.textContent = '歌詞';
            lyricsContentEl.textContent = '';
            return;
        }
        lyricsTitleEl.textContent = state.lyricsTitle || ((state.title || '目前歌曲') + ' - 歌詞');
        lyricsContentEl.textContent = state.lyrics;
        lyricsPanelEl.style.display = state.lyricsOpen && !state.collapsed ? 'block' : 'none';
    }

    function applyCollapsedState(state) {
        getElements();
        if (!shell) return;
        const canCollapse = !!(state && state.src);
        const collapsed = !!(canCollapse && state.collapsed);

        shell.classList.toggle('is-collapsed', collapsed);

        if (collapseBtn) {
            collapseBtn.style.display = canCollapse ? 'inline-flex' : 'none';
            collapseBtn.classList.toggle('active', collapsed);
            collapseBtn.title = collapsed ? '展開播放器' : '收合播放器';
            collapseBtn.innerHTML = collapsed
                ? '<i class="fa-solid fa-chevron-up"></i>'
                : '<i class="fa-solid fa-chevron-down"></i>';
        }

        if (lyricsPanelEl && collapsed) {
            lyricsPanelEl.style.display = 'none';
        }
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
            shell.classList.remove('is-collapsed');
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
            applyLyricsState(null);
            activeKind = null;
            return;
        }

        activeKind = state.kind === 'video' ? 'video' : 'audio';
        shell.style.display = 'block';
        shell.classList.toggle('is-video', activeKind === 'video');
        shell.classList.toggle('is-audio', activeKind === 'audio');
        applyShellMode(state);
        applyTheme(readTheme(state), state);
        applyCollapsedState(state);

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
        applyLyricsState(state);

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
            lyrics: payload.lyrics || '',
            lyricsTitle: payload.lyricsTitle || '',
            lyricsOpen: !!payload.lyricsOpen,
            collapsed: readCollapsedPreference(),
        };
        writeState(current);
        pauseCompetingMedia(kind === 'video' ? videoEl : audioEl);
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

    function setLyrics(payload) {
        const current = readState();
        if (!current || current.kind !== 'audio') return;
        current.lyrics = String(payload && payload.lyrics ? payload.lyrics : '');
        current.lyricsTitle = String(payload && payload.title ? payload.title : (current.title || '歌詞'));
        current.lyricsOpen = !!(payload && payload.open && current.lyrics);
        writeState(current);
        applyLyricsState(current);
    }

    function toggleLyricsPanel() {
        const current = readState();
        if (!current || !current.lyrics) return;
        current.lyricsOpen = !current.lyricsOpen;
        if (current.lyricsOpen && current.collapsed) {
            current.collapsed = false;
            writeCollapsedPreference(false);
        }
        writeState(current);
        renderShell(current);
    }

    function toggleCollapse() {
        const current = readState();
        if (!current) return;
        current.collapsed = !current.collapsed;
        if (current.collapsed) {
            current.lyricsOpen = false;
        }
        writeCollapsedPreference(current.collapsed);
        writeState(current);
        renderShell(current);
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
                    if (eventName === 'play') {
                        pauseCompetingMedia(el);
                        syncStateFromElement(true);
                    } else if (eventName === 'ended') {
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

        document.addEventListener('play', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLMediaElement)) return;
            pauseCompetingMedia(target);
        }, true);

        if (closeBtn) {
            closeBtn.addEventListener('click', stop);
        }
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggle);
        }
        if (collapseBtn) {
            collapseBtn.addEventListener('click', toggleCollapse);
        }
        if (lyricsBtn) {
            lyricsBtn.addEventListener('click', toggleLyricsPanel);
        }
        if (lyricsCloseBtn) {
            lyricsCloseBtn.addEventListener('click', function () {
                const current = readState();
                if (!current) return;
                current.lyricsOpen = false;
                writeState(current);
                applyLyricsState(current);
            });
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
        toggleCollapse: toggleCollapse,
        setLyrics: setLyrics,
        toggleLyricsPanel: toggleLyricsPanel,
        toggleBySource: toggleBySource,
        getState: readState,
        setMediaView: setMediaView,
        initMediaView: initMediaView
    };

    window.initGlobalMediaPlayer = initGlobalMediaPlayer;
    window.setMediaView = setMediaView;
    window.initMediaView = initMediaView;
})();
