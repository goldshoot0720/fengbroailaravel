# 🎨 鋒兄常用美化顯示UI模板

> 快速複製貼上，美化您的頁面！

---

## 📦 一、卡片組件 (Cards)

### 基礎卡片
```html
<div class="card">
    <div class="card-title">📝 標題</div>
    <p>卡片內容</p>
</div>
```

### 懸浮效果卡片
```html
<div class="card" style="transition: all 0.3s ease; cursor: pointer;">
    <div class="card-title">✨ 互動卡片</div>
    <p>滑鼠移入會有浮動效果</p>
</div>
```

### 統計數字卡片
```html
<div class="card" style="text-align: center;">
    <div style="font-size: 3rem; font-weight: 700; color: #3498db;">24</div>
    <div style="color: #666; font-size: 0.9rem;">總項目數</div>
</div>
```

### 帶圖標的卡片
```html
<div class="card" style="display: flex; gap: 15px; align-items: center;">
    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
        <i class="fas fa-chart-line" style="color: #fff; font-size: 1.5rem;"></i>
    </div>
    <div>
        <div style="font-size: 1.5rem; font-weight: 700;">1,234</div>
        <div style="color: #666;">總瀏覽量</div>
    </div>
</div>
```

---

## 🎯 二、按鈕樣式 (Buttons)

### 漸層按鈕
```html
<button class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
    <i class="fas fa-plus"></i> 新增項目
</button>
```

### 圓角按鈕
```html
<button class="btn btn-primary" style="border-radius: 25px; padding: 12px 30px;">
    <i class="fas fa-save"></i> 儲存
</button>
```

### 懸浮動畫按鈕
```html
<button class="btn btn-success" style="transition: all 0.3s; transform: translateY(0);" 
        onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 20px rgba(39, 174, 96, 0.4)'"
        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
    <i class="fas fa-rocket"></i> 開始執行
</button>
```

### 圖標按鈕（圓形）
```html
<button style="width: 45px; height: 45px; border-radius: 50%; border: none; background: #3498db; color: #fff; cursor: pointer; transition: all 0.3s;">
    <i class="fas fa-search"></i>
</button>
```

---

## 🏷️ 三、標籤/狀態 (Badges)

### 狀態標籤
```html
<span class="badge badge-success"><i class="fas fa-check"></i> 已完成</span>
<span class="badge badge-danger"><i class="fas fa-times"></i> 未完成</span>
<span style="display: inline-block; padding: 4px 12px; border-radius: 20px; background: #3498db; color: #fff; font-size: 0.8rem;">
    <i class="fas fa-star"></i> 精選
</span>
```

### 帶圖標的類別標籤
```html
<span style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 15px; border-radius: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: #fff; font-size: 0.85rem; font-weight: 500;">
    <i class="fas fa-tag"></i> 熱門
</span>
```

---

## 📊 四、統計區塊 (Stats)

### 四格統計面板
```html
<div class="card-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="card" style="text-align: center; border-left: 4px solid #3498db;">
        <div style="font-size: 2rem; font-weight: 700; color: #3498db;">156</div>
        <div style="color: #666; margin-top: 5px;">📷 圖片</div>
    </div>
    <div class="card" style="text-align: center; border-left: 4px solid #e74c3c;">
        <div style="font-size: 2rem; font-weight: 700; color: #e74c3c;">42</div>
        <div style="color: #666; margin-top: 5px;">🎬 影片</div>
    </div>
    <div class="card" style="text-align: center; border-left: 4px solid #27ae60;">
        <div style="font-size: 2rem; font-weight: 700; color: #27ae60;">89</div>
        <div style="color: #666; margin-top: 5px;">🍕 食物</div>
    </div>
    <div class="card" style="text-align: center; border-left: 4px solid #9b59b6;">
        <div style="font-size: 2rem; font-weight: 700; color: #9b59b6;">12</div>
        <div style="color: #666; margin-top: 5px;">📋 訂閱</div>
    </div>
</div>
```

### 帶進度條的統計
```html
<div class="card">
    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
        <span>儲存空間使用</span>
        <span style="font-weight: 600;">75%</span>
    </div>
    <div style="height: 10px; background: #eee; border-radius: 5px; overflow: hidden;">
        <div style="width: 75%; height: 100%; background: linear-gradient(90deg, #3498db, #9b59b6); border-radius: 5px;"></div>
    </div>
</div>
```

---

## 🔍 五、搜尋區塊 (Search)

### 美化搜尋框
```html
<div style="display: flex; gap: 10px; margin-bottom: 25px;">
    <div style="flex: 1; position: relative;">
        <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999;"></i>
        <input type="text" class="form-control" placeholder="搜尋..." 
               style="padding-left: 45px; border-radius: 25px; border: 2px solid #eee; transition: all 0.3s;"
               onfocus="this.style.borderColor='#3498db'; this.style.boxShadow='0 0 15px rgba(52, 152, 219, 0.2)'"
               onblur="this.style.borderColor='#eee'; this.style.boxShadow='none'">
    </div>
    <button class="btn btn-primary" style="border-radius: 25px; padding: 10px 25px;">
        <i class="fas fa-search"></i> 搜尋
    </button>
</div>
```

---

## 📋 六、表格美化 (Tables)

### 美化表格
```html
<table class="table" style="border-radius: 10px; overflow: hidden;">
    <thead>
        <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;">
            <th style="padding: 15px;">ID</th>
            <th>名稱</th>
            <th>狀態</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>1</td>
            <td>項目一</td>
            <td><span class="badge badge-success">✓ 啟用</span></td>
            <td>
                <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    </tbody>
</table>
```

---

## 🖼️ 七、圖片網格 (Image Grid)

### 相簿網格
```html
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
    <div style="aspect-ratio: 1; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s; cursor: pointer;"
         onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.2)'"
         onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'">
        <img src="image.jpg" alt="" style="width: 100%; height: 100%; object-fit: cover;">
    </div>
    <!-- 重複更多圖片... -->
</div>
```

---

## 💬 八、提示訊息 (Alerts)

### 成功提示
```html
<div style="padding: 15px 20px; border-radius: 10px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 4px solid #27ae60; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #27ae60; font-size: 1.3rem;"></i>
    <span style="color: #155724;">操作成功！資料已儲存。</span>
</div>
```

### 警告提示
```html
<div style="padding: 15px 20px; border-radius: 10px; background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); border-left: 4px solid #f39c12; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-triangle" style="color: #f39c12; font-size: 1.3rem;"></i>
    <span style="color: #856404;">請注意！此操作無法復原。</span>
</div>
```

### 錯誤提示
```html
<div style="padding: 15px 20px; border-radius: 10px; background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-left: 4px solid #e74c3c; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-times-circle" style="color: #e74c3c; font-size: 1.3rem;"></i>
    <span style="color: #721c24;">發生錯誤！請稍後再試。</span>
</div>
```

---

## 🎨 九、漸層背景 (Gradients)

### 常用漸層色彩
```css
/* 藍紫漸層 */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* 日落漸層 */
background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);

/* 海洋漸層 */
background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);

/* 森林漸層 */
background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);

/* 火焰漸層 */
background: linear-gradient(135deg, #f12711 0%, #f5af19 100%);

/* 暗黑漸層 */
background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
```

---

## ✨ 十、動畫效果 CSS

### 加入 style.css 使用
```css
/* 淡入動畫 */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in { animation: fadeIn 0.5s ease forwards; }

/* 脈衝動畫 */
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
.pulse { animation: pulse 2s infinite; }

/* 閃爍動畫 */
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
.shimmer {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

/* 浮動動畫 */
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.float { animation: float 3s ease-in-out infinite; }
```

---

## 📱 響應式快速樣式

```css
/* 手機版隱藏 */
@media (max-width: 768px) {
    .hide-mobile { display: none !important; }
}

/* 電腦版隱藏 */
@media (min-width: 769px) {
    .hide-desktop { display: none !important; }
}
```

---

## 🚀 快速使用範例

```php
<!-- 一個完整的美化頁面區塊 -->
<div class="content-body">
    <!-- 搜尋區 -->
    <div style="display: flex; gap: 10px; margin-bottom: 25px;">
        <div style="flex: 1; position: relative;">
            <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999;"></i>
            <input type="text" class="form-control" placeholder="搜尋..." style="padding-left: 45px; border-radius: 25px;">
        </div>
        <button class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 25px;">
            <i class="fas fa-plus"></i> 新增
        </button>
    </div>
    
    <!-- 統計卡片 -->
    <div class="card-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 25px;">
        <div class="card" style="text-align: center; border-left: 4px solid #3498db;">
            <div style="font-size: 2rem; font-weight: 700; color: #3498db;">24</div>
            <div style="color: #666;">總數量</div>
        </div>
        <!-- 更多卡片... -->
    </div>
    
    <!-- 資料表格 -->
    <table class="table" style="border-radius: 10px; overflow: hidden;">
        <!-- 表格內容... -->
    </table>
</div>
```

---

## 🎵 十一、兩層分類音樂播放器 (B型H系-おしえてA to Z)

### 完整音樂播放器模板（16首歌）
```html
<!-- 🎵 B型H系風格音樂播放器 -->
<div class="card" style="padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;">
    <div class="card-title" style="color: #fff; font-size: 1.3rem; margin-bottom: 20px;">
        🎵 B型H系-おしえてA to Z
    </div>
    
    <!-- 第一層：語言分類按鈕 -->
    <div id="langSelector" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
        <button type="button" class="lang-btn active" data-lang="中文" onclick="selectLang('中文')" 
                style="padding: 10px 20px; border-radius: 25px; border: 2px solid #fff; background: #fff; color: #764ba2; font-weight: 600; cursor: pointer; transition: all 0.3s;">
            🇨🇳 中文
        </button>
        <button type="button" class="lang-btn" data-lang="英語" onclick="selectLang('英語')"
                style="padding: 10px 20px; border-radius: 25px; border: 2px solid rgba(255,255,255,0.5); background: transparent; color: #fff; font-weight: 600; cursor: pointer; transition: all 0.3s;">
            🇺🇸 英語
        </button>
        <button type="button" class="lang-btn" data-lang="日語" onclick="selectLang('日語')"
                style="padding: 10px 20px; border-radius: 25px; border: 2px solid rgba(255,255,255,0.5); background: transparent; color: #fff; font-weight: 600; cursor: pointer; transition: all 0.3s;">
            🇯🇵 日語
        </button>
        <button type="button" class="lang-btn" data-lang="韓語" onclick="selectLang('韓語')"
                style="padding: 10px 20px; border-radius: 25px; border: 2px solid rgba(255,255,255,0.5); background: transparent; color: #fff; font-weight: 600; cursor: pointer; transition: all 0.3s;">
            🇰🇷 韓語
        </button>
        <button type="button" class="lang-btn" data-lang="粵語" onclick="selectLang('粵語')"
                style="padding: 10px 20px; border-radius: 25px; border: 2px solid rgba(255,255,255,0.5); background: transparent; color: #fff; font-weight: 600; cursor: pointer; transition: all 0.3s;">
            🇭🇰 粵語
        </button>
        <button type="button" class="lang-btn" data-lang="其他" onclick="selectLang('其他')"
                style="padding: 10px 20px; border-radius: 25px; border: 2px solid rgba(255,255,255,0.5); background: transparent; color: #fff; font-weight: 600; cursor: pointer; transition: all 0.3s;">
            🌐 其他
        </button>
    </div>

    <!-- 第二層：子分類選擇 -->
    <div id="subLangSelector" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 12px;">
        <!-- 動態填充子分類按鈕 -->
    </div>

    <!-- 播放控制區 -->
    <div style="display: flex; align-items: center; gap: 15px; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 15px;">
        <button id="playBtn" onclick="togglePlay()" 
                style="width: 60px; height: 60px; border-radius: 50%; border: none; background: #fff; color: #764ba2; font-size: 1.5rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
            <i class="fas fa-play"></i>
        </button>
        <div style="flex: 1;">
            <div id="currentTrack" style="font-weight: 600; margin-bottom: 5px;">請選擇歌曲</div>
            <div id="trackInfo" style="font-size: 0.85rem; opacity: 0.8;">--:-- / --:--</div>
        </div>
    </div>

    <!-- 隱藏的 audio 元素 -->
    <audio id="audioPlayer" style="display: none;"></audio>
</div>

<script>
// 歌曲數據 - 16首歌分類
const songData = {
    '中文': [
        { label: '中文(女聲)', file: 'path/to/chinese_female.mp3' },
        { label: '中文(男聲)', file: 'path/to/chinese_male.mp3' },
        { label: '中文(合唱)', file: 'path/to/chinese_duet.mp3' }
    ],
    '英語': [
        { label: '英語(女聲)', file: 'path/to/english_female.mp3' },
        { label: '英語(男聲)', file: 'path/to/english_male.mp3' }
    ],
    '日語': [
        { label: '日語(原唱)', file: 'path/to/japanese_original.mp3' },
        { label: '日語(女聲)', file: 'path/to/japanese_female.mp3' },
        { label: '日語(男聲)', file: 'path/to/japanese_male.mp3' }
    ],
    '韓語': [
        { label: '韓語(女聲)', file: 'path/to/korean_female.mp3' },
        { label: '韓語(男聲)', file: 'path/to/korean_male.mp3' }
    ],
    '粵語': [
        { label: '粵語(女聲)', file: 'path/to/cantonese_female.mp3' },
        { label: '粵語(男聲)', file: 'path/to/cantonese_male.mp3' }
    ],
    '其他': [
        { label: '純音樂', file: 'path/to/instrumental.mp3' },
        { label: '伴奏版', file: 'path/to/karaoke.mp3' },
        { label: '混音版', file: 'path/to/remix.mp3' },
        { label: '現場版', file: 'path/to/live.mp3' }
    ]
};

let currentLang = '中文';
let currentTrackFile = null;
let isPlaying = false;

function selectLang(lang) {
    currentLang = lang;
    
    // 更新第一層按鈕樣式
    document.querySelectorAll('.lang-btn').forEach(btn => {
        if (btn.dataset.lang === lang) {
            btn.style.background = '#fff';
            btn.style.color = '#764ba2';
            btn.style.borderColor = '#fff';
        } else {
            btn.style.background = 'transparent';
            btn.style.color = '#fff';
            btn.style.borderColor = 'rgba(255,255,255,0.5)';
        }
    });
    
    // 更新第二層子分類
    renderSubLang(lang);
}

function renderSubLang(lang) {
    const container = document.getElementById('subLangSelector');
    const songs = songData[lang] || [];
    
    container.innerHTML = songs.map((song, index) => `
        <button type="button" class="sub-lang-btn" data-file="${song.file}" data-label="${song.label}"
                onclick="selectTrack('${song.file}', '${song.label}')"
                style="padding: 8px 16px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.5); 
                       background: ${index === 0 ? 'rgba(255,255,255,0.3)' : 'transparent'}; 
                       color: #fff; font-size: 0.9rem; cursor: pointer; transition: all 0.3s;">
            ${song.label}
        </button>
    `).join('');
    
    // 自動選擇第一首
    if (songs.length > 0) {
        selectTrack(songs[0].file, songs[0].label);
    }
}

function selectTrack(file, label) {
    currentTrackFile = file;
    document.getElementById('currentTrack').textContent = label;
    
    // 更新子分類按鈕樣式
    document.querySelectorAll('.sub-lang-btn').forEach(btn => {
        if (btn.dataset.file === file) {
            btn.style.background = 'rgba(255,255,255,0.3)';
        } else {
            btn.style.background = 'transparent';
        }
    });
    
    // 設置音源
    const audio = document.getElementById('audioPlayer');
    audio.src = file;
}

function togglePlay() {
    const audio = document.getElementById('audioPlayer');
    const playBtn = document.getElementById('playBtn');
    
    if (!currentTrackFile) {
        alert('請先選擇歌曲');
        return;
    }
    
    if (isPlaying) {
        audio.pause();
        playBtn.innerHTML = '<i class="fas fa-play"></i>';
    } else {
        audio.play();
        playBtn.innerHTML = '<i class="fas fa-pause"></i>';
    }
    isPlaying = !isPlaying;
}

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    renderSubLang('中文');
    
    const audio = document.getElementById('audioPlayer');
    audio.addEventListener('timeupdate', function() {
        const current = formatTime(audio.currentTime);
        const duration = formatTime(audio.duration);
        document.getElementById('trackInfo').textContent = `${current} / ${duration}`;
    });
    
    audio.addEventListener('ended', function() {
        document.getElementById('playBtn').innerHTML = '<i class="fas fa-play"></i>';
        isPlaying = false;
    });
});

function formatTime(seconds) {
    if (isNaN(seconds)) return '--:--';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>
```

### 精簡版 - 僅下拉選單
```html
<!-- 精簡版兩層選擇播放器 -->
<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <!-- 第一層：語言選擇 -->
    <select id="langSelect" class="form-control" style="width: auto;" onchange="updateSubLang()">
        <option value="中文">🇨🇳 中文</option>
        <option value="英語">🇺🇸 英語</option>
        <option value="日語">🇯🇵 日語</option>
        <option value="韓語">🇰🇷 韓語</option>
        <option value="粵語">🇭🇰 粵語</option>
        <option value="其他">🌐 其他</option>
    </select>
    
    <!-- 第二層：子分類選擇 -->
    <select id="subLangSelect" class="form-control" style="width: auto;">
        <option value="female">中文(女聲)</option>
        <option value="male">中文(男聲)</option>
        <option value="duet">中文(合唱)</option>
    </select>
    
    <!-- 播放按鈕 -->
    <button class="btn btn-primary" onclick="playSelectedTrack()" style="border-radius: 25px;">
        <i class="fas fa-play"></i> 播放
    </button>
</div>
```

### 卡片式網格選擇
```html
<!-- 卡片式歌曲選擇網格 -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
    <div class="song-card" onclick="playSong('chinese_female.mp3')"
         style="padding: 15px; border-radius: 12px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: #fff; cursor: pointer; transition: all 0.3s; text-align: center;"
         onmouseover="this.style.transform='scale(1.05)'"
         onmouseout="this.style.transform='scale(1)'">
        <div style="font-size: 2rem; margin-bottom: 8px;">🇨🇳</div>
        <div style="font-weight: 600;">中文(女聲)</div>
    </div>
    <div class="song-card" onclick="playSong('chinese_male.mp3')"
         style="padding: 15px; border-radius: 12px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: #fff; cursor: pointer; transition: all 0.3s; text-align: center;"
         onmouseover="this.style.transform='scale(1.05)'"
         onmouseout="this.style.transform='scale(1)'">
        <div style="font-size: 2rem; margin-bottom: 8px;">🇨🇳</div>
        <div style="font-weight: 600;">中文(男聲)</div>
    </div>
    <div class="song-card" onclick="playSong('japanese_original.mp3')"
         style="padding: 15px; border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; cursor: pointer; transition: all 0.3s; text-align: center;"
         onmouseover="this.style.transform='scale(1.05)'"
         onmouseout="this.style.transform='scale(1)'">
        <div style="font-size: 2rem; margin-bottom: 8px;">🇯🇵</div>
        <div style="font-weight: 600;">日語(原唱)</div>
    </div>
    <!-- 更多歌曲卡片... -->
</div>
```

---

💡 **提示**: 所有樣式都支援暗黑模式，會自動使用 CSS 變數切換顏色！

