document.addEventListener('DOMContentLoaded', function () {
    initDarkMode();
    initHeaderRefreshButtons();
    initGlobalMediaPlayer();
    initFengbroVoiceInput();
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

function initFengbroVoiceInput() {
    if (window.__fengbroVoiceReady) return;
    window.__fengbroVoiceReady = true;

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;
    let listening = false;
    let pendingAction = null;

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
                nextdate: ['下次付款', '付款日期', '日期', '到期'],
                note: ['備註', '筆記', '說明'],
                continue: ['續訂', '不續訂']
            },
            examples: ['新增訂閱 Netflix 價格 299 下月 5 號', '搜尋 Netflix', '篩選 7 天內', '不續訂', '儲存']
        },
        food: {
            title: '鋒兄食品',
            aliases: ['食品', '食材', '商品庫存', '鋒兄食品', 'food'],
            fields: {
                name: ['食品', '商品', '名稱', '品名'],
                store: ['商店', '店家'],
                quantity: ['數量', '庫存'],
                price: ['價格', '金額'],
                expiry_date: ['到期', '有效日期', '日期'],
                category: ['分類', '類別'],
                note: ['備註', '筆記']
            },
            examples: ['新增食品 牛奶 數量 2 到期 7 天', '搜尋 雞蛋', '低庫存', '已過期', '儲存']
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
            examples: ['新增銀行 台新 帳號 123', '新增收入 1000', '新增支出 200', '儲存']
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
            examples: ['搜尋 匯率', '前往設定', '重新整理']
        },
        settings: {
            title: '鋒兄設定',
            aliases: ['設定', '鋒兄設定', 'settings'],
            fields: {},
            examples: ['掃描儲存空間', '傳送推播', '產生金鑰', '刪除未引用檔案']
        },
        about: {
            title: '鋒兄關於',
            aliases: ['關於', '版本', '鋒兄關於', 'about'],
            fields: {},
            examples: ['重新整理', '前往首頁', '深色模式']
        }
    };

    const pageOrder = Object.keys(pageProfiles);

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
            stopListening();
        });
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
        if (fab) fab.classList.toggle('listening', listening);
    }

    function updateHints() {
        const hints = document.getElementById('fengbroVoiceHints');
        if (!hints) return;
        const profile = pageProfiles[currentPage()] || pageProfiles.home;
        const examples = [
            '確認',
            '取消',
            '搜尋 關鍵字',
            '新增',
            '儲存',
            '前往鋒兄訂閱'
        ].concat(profile.examples || []);
        hints.innerHTML = examples.slice(0, 12).map(function (item) {
            return '<button type="button" class="voice-chip">' + escapeHtml(item) + '</button>';
        }).join('');
        hints.querySelectorAll('.voice-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                handleCommand(chip.textContent || '');
            });
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
        recognition.lang = 'zh-TW';
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.maxAlternatives = 3;
        recognition.onstart = function () {
            listening = true;
            updateFab();
            setPanelVisible(true);
            setStatus('正在聽，請說出指令。', 'active');
        };
        recognition.onend = function () {
            listening = false;
            updateFab();
            setStatus('語音已停止。可再按一次繼續。', 'info');
        };
        recognition.onerror = function (event) {
            listening = false;
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
        createUI();
        setPanelVisible(true);
        if (!SpeechRecognition) {
            setStatus('此瀏覽器不支援 Web Speech API，請使用下方文字指令。', 'error');
            return;
        }
        setupRecognition();
        if (listening) {
            stopListening();
            return;
        }
        try {
            recognition.start();
        } catch (error) {
            setStatus('語音已在準備中，請稍候再試。', 'info');
        }
    }

    function stopListening() {
        if (recognition && listening) {
            recognition.stop();
        }
        listening = false;
        updateFab();
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

        const navTarget = findPageTarget(text);
        if (navTarget) {
            stageAction('前往 ' + pageProfiles[navTarget].title, function () {
                window.location.href = 'index.php?page=' + encodeURIComponent(navTarget);
            });
            return;
        }

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
        if (!/(前往|打開|開啟|切換|去|到)/.test(text)) return null;
        for (const page of pageOrder) {
            const profile = pageProfiles[page];
            if ((profile.aliases || []).some(function (alias) { return text.indexOf(alias) !== -1; })) {
                return page;
            }
        }
        return null;
    }

    function handleGlobalCommand(text) {
        if (/重新整理|刷新|重整/.test(text)) {
            stageAction('重新整理頁面', function () { window.location.reload(); });
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
        if (/播放/.test(text)) {
            const btn = document.querySelector('[onclick*="play"], [onclick*="togglePlay"]');
            if (btn) {
                btn.click();
                setStatus('已嘗試播放。', 'success');
                return true;
            }
        }
        if (/暫停|停止播放/.test(text)) {
            document.querySelectorAll('audio, video').forEach(function (media) { media.pause(); });
            setStatus('已暫停媒體。', 'success');
            return true;
        }
        return false;
    }

    function handlePageCommand(text) {
        const page = currentPage();
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
        return false;
    }

    function fillFromSpeech(text) {
        const page = currentPage();
        const profile = pageProfiles[page] || {};
        const fields = profile.fields || {};
        let changed = false;

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

    function extractValueAfterAlias(text, alias) {
        const idx = text.indexOf(alias);
        if (idx === -1) return null;
        let rest = text.slice(idx + alias.length).trim();
        rest = rest.replace(/^(是|為|等於|:|：)/, '').trim();
        if (!rest) return '';
        const stopWords = ['服務', '服務名稱', '名稱', '網站', '網址', '帳號', '價格', '金額', '費用', '幣別', '貨幣', '日期', '到期', '備註', '筆記', '分類', '類別', '數量', '庫存', '商店', '店家', '內容', '標題', '歌詞', '語言', '封面', '參考', '來源', '地址', '卡號', '存款', '提款', '轉帳'];
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
        if (/date|expiry|nextdate/i.test(field)) {
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
        const markers = ['價格', '金額', '數量', '到期', '日期', '分類', '備註', '內容', '網站', '帳號', '語言', '檔案', '封面', '參考'];
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
        if (page === 'food') return 'expiry_date';
        return 'date';
    }

    function addDays(days) {
        const date = new Date();
        date.setDate(date.getDate() + Number(days || 0));
        return date.toISOString().slice(0, 10);
    }

    function addMonths(months) {
        const date = new Date();
        date.setMonth(date.getMonth() + Number(months || 0));
        return date.toISOString().slice(0, 10);
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

    function isHidden(el) {
        const rect = el.getBoundingClientRect();
        return rect.width === 0 || rect.height === 0 || getComputedStyle(el).visibility === 'hidden' || getComputedStyle(el).display === 'none';
    }

    createUI();
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
