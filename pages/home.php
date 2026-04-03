<?php
$pageTitle = '首頁';
?>

<div class="content-header">
    <div class="page-intro">
        <span class="eyebrow">WELCOME</span>
        <h1>Fengbro AI</h1>
        <pre class="ascii-fengbro" aria-label="ASCII art FENG BRO">
 ______ ______ _   _  _____   ____  ____   ___
|  ____|  ____| \ | |/ ____| |  _ \|  _ \ / _ \
| |__  | |__  |  \| | |  __  | |_) | |_) | | | |
|  __| |  __| | . ` | | |_ | |  _ <|  _ <| | | |
| |    | |____| |\  | |__| | | |_) | |_) | |_| |
|_|    |______|_| \_|\_____| |____/|____/ \___/
                   F E N G   B R O
        </pre>
        <p>個人作業中樞，整合訂閱、筆記、資料庫與日常操作流程，快速掌握每個關鍵狀態。</p>
    </div>
</div>

<div class="content-body">
    <section class="hero-panel hero-panel-home">
        <div class="hero-copy">
            <span class="eyebrow">Tech-focused personal command center</span>
            <h2>專注、清晰、可操作的管理介面</h2>
            <p>用清楚的資訊層級與快速動作，維持日常維運的節奏，讓重要狀態一眼可見。</p>
            <div class="hero-actions">
                <a href="index.php?page=dashboard" class="btn btn-primary">
                    <i class="fa-solid fa-gauge-high"></i> 前往儀表板
                </a>
                <a href="index.php?page=subscription" class="btn btn-ghost">
                    <i class="fa-solid fa-credit-card"></i> 訂閱管理
                </a>
            </div>
        </div>
        <div class="hero-stack">
            <article class="signal-card signal-card-primary">
                <span class="signal-label">Live Focus</span>
                <strong>Subscriptions + Food Ops</strong>
                <p>追蹤下一次付款與食材狀態，集中處理重要提醒。</p>
            </article>
            <article class="signal-card">
                <span class="signal-label">Interaction Goal</span>
                <strong>Fast scan, low friction</strong>
                <p>用最低摩擦的操作完成日常維護與整理。</p>
            </article>
        </div>
    </section>
</div>

<style>
    .ascii-fengbro {
        margin: 12px 0 0 0;
        padding: 12px 16px;
        border-radius: 12px;
        border: 1px solid #dfe7f3;
        background: #f3f6fb;
        color: #2c3e50;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
        font-size: 0.75rem;
        line-height: 1.2;
        white-space: pre;
        overflow-x: auto;
    }

    [data-theme="dark"] .ascii-fengbro {
        background: #0f1a2b;
        color: #d4e2ff;
        border-color: #1f3552;
    }
</style>
