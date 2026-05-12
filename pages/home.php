<?php
$pageTitle = '鋒兄首頁';
require_once __DIR__ . '/../includes/fengbro_tube.php';
require_once __DIR__ . '/../includes/fengbro_finance.php';

$nowTaipei = new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei'));
$currentHour = (int) $nowTaipei->format('G');
$sleepWarningClass = '';
$currentHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$currentHost = preg_replace('/:\d+$/', '', $currentHost);
$serviceCountdown = null;
$serviceNotice = null;
$tubeNewVideos = [];
$financeHighNotices = [];

if ($currentHour >= 0 && $currentHour <= 2) {
    $sleepWarningClass = 'sleep-warning-yellow';
} elseif ($currentHour >= 3 && $currentHour <= 6) {
    $sleepWarningClass = 'sleep-warning-red';
}

$countdownTargets = [
    'laravel.tpe12thmayor2025to2038.com' => [
        'date' => '2026-05-13',
        'prefix' => '至',
        'label' => '網站終止服務'
    ],
    'fengbroailaravel.tpe12thmayor2038from2025.com' => [
        'date' => '2026-06-15',
        'prefix' => '暫定至',
        'label' => '網站終止服務'
    ],
];

$noticeTargets = [
    'tpe12thmayor2025to2038.cloudaccess.host' => '每月月底之前確認網站效期',
];

if (isset($countdownTargets[$currentHost])) {
    $targetConfig = $countdownTargets[$currentHost];
    $todayTaipei = $nowTaipei->setTime(0, 0);
    $targetDate = new DateTimeImmutable($targetConfig['date'], new DateTimeZone('Asia/Taipei'));
    $daysRemaining = (int) $todayTaipei->diff($targetDate)->format('%r%a');
    $serviceCountdown = [
        'days' => max(0, $daysRemaining),
        'dateText' => $targetDate->format('Y年m月d日'),
        'prefix' => $targetConfig['prefix'],
        'label' => $targetConfig['label'],
    ];
}

if (isset($noticeTargets[$currentHost])) {
    $serviceNotice = $noticeTargets[$currentHost];
}

$tubeData = fengbroTubeGetData(false);
$tubeNewVideos = $tubeData['newVideos'] ?? [];
$financeData = fengbroFinanceGetData(false);
foreach (($financeData['quotes'] ?? []) as $quote) {
    if (($quote['name'] ?? '') === 'Shiller PE Ratio' && ($quote['status'] ?? '') === '創新高') {
        $financeHighNotices[] = $quote;
    }
}
?>

<?php if ($sleepWarningClass): ?>
    <div class="sleep-warning <?= $sleepWarningClass ?>" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <strong>請入睡</strong>
    </div>
<?php endif; ?>

<?php if ($serviceNotice): ?>
    <div class="service-notice" role="status">
        <i class="fa-solid fa-circle-exclamation"></i>
        <strong><?php echo htmlspecialchars($serviceNotice); ?></strong>
    </div>
<?php endif; ?>

<?php if ($serviceCountdown): ?>
    <div class="service-countdown" role="status">
        <div class="service-countdown-copy">
            <span class="service-countdown-label">服務倒數</span>
            <strong><?php echo $serviceCountdown['prefix']; ?> <?php echo $serviceCountdown['dateText']; ?><?php echo $serviceCountdown['label']; ?></strong>
        </div>
        <div class="service-countdown-days">
            <span><?php echo $serviceCountdown['days']; ?></span>
            <small>天</small>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($tubeNewVideos)): ?>
    <a class="tube-home-notice" href="index.php?page=tools&tool=tube" role="status">
        <i class="fa-brands fa-youtube"></i>
        <span>
            <strong>鋒兄tube 有 <?php echo count($tubeNewVideos); ?> 部 3 天內新影片</strong>
            <small><?php echo htmlspecialchars($tubeNewVideos[0]['channel'] ?? 'YouTube'); ?>：<?php echo htmlspecialchars($tubeNewVideos[0]['title'] ?? '最新影片'); ?></small>
        </span>
        <i class="fa-solid fa-arrow-right"></i>
    </a>
<?php endif; ?>

<?php if (!empty($financeHighNotices)): ?>
    <?php $shillerNotice = $financeHighNotices[0]; ?>
    <a class="tube-home-notice finance-home-notice" href="index.php?page=tools&tool=finance" role="status">
        <i class="fa-solid fa-chart-line"></i>
        <span>
            <strong>Shiller PE Ratio 創新高</strong>
            <small>目前 <?php echo htmlspecialchars($shillerNotice['value'] ?? '-'); ?>，歷史高點 <?php echo htmlspecialchars($shillerNotice['high52'] ?? '44.19 (Dec 1999)'); ?></small>
        </span>
        <i class="fa-solid fa-arrow-right"></i>
    </a>
<?php endif; ?>
<div class="content-header">
    <div class="page-intro">
        <span class="eyebrow">WELCOME</span>
        <h1>Fengbro AI</h1>
        <pre class="ascii-fengbro" aria-label="ASCII art FENG BRO">
 ______ ______ _   _  _____   ____  _____   ____
|  ____|  ____| \ | |/ ____| |  _ \|  __ \ / __ \
| |__  | |__  |  \| | |  __  | |_) | |__) | |  | |
|  __| |  __| | . ` | | |_ | |  _ <|  _  /| |  | |
| |    | |____| |\  | |__| | | |_) | | \ \| |__| |
|_|    |______|_| \_|\_____| |____/|_|  \_\\____/
                    F E N G   B R O
        </pre>
        <p>&#x500B;&#x4EBA;&#x4F5C;&#x696D;&#x4E2D;&#x6A1E;&#xFF0C;&#x6574;&#x5408;&#x8A02;&#x95B1;&#x3001;&#x7B46;&#x8A18;&#x3001;&#x8CC7;&#x6599;&#x5EAB;&#x8207;&#x65E5;&#x5E38;&#x64CD;&#x4F5C;&#x6D41;&#x7A0B;&#xFF0C;&#x5FEB;&#x901F;&#x638C;&#x63E1;&#x6BCF;&#x500B;&#x95DC;&#x9375;&#x72C0;&#x614B;&#x3002;</p>
    </div>
</div>

<div class="content-body">
    <section class="hero-panel hero-panel-home">
        <div class="hero-copy">
            <span class="eyebrow">Tech-focused personal command center</span>
            <h2>&#x5C08;&#x6CE8;&#x3001;&#x6E05;&#x6670;&#x3001;&#x53EF;&#x64CD;&#x4F5C;&#x7684;&#x7BA1;&#x7406;&#x4ECB;&#x9762;</h2>
            <p>&#x7528;&#x6E05;&#x695A;&#x7684;&#x8CC7;&#x8A0A;&#x5C64;&#x7D1A;&#x8207;&#x5FEB;&#x901F;&#x52D5;&#x4F5C;&#xFF0C;&#x7DAD;&#x6301;&#x65E5;&#x5E38;&#x7DAD;&#x904B;&#x7684;&#x7BC0;&#x594F;&#xFF0C;&#x8B93;&#x91CD;&#x8981;&#x72C0;&#x614B;&#x4E00;&#x773C;&#x53EF;&#x898B;&#x3002;</p>
            <div class="hero-actions">
                <a href="index.php?page=dashboard" class="btn btn-primary">
                    <i class="fa-solid fa-gauge-high"></i> &#x524D;&#x5F80;&#x5100;&#x8868;&#x677F;
                </a>
                <a href="index.php?page=subscription" class="btn btn-ghost">
                    <i class="fa-solid fa-credit-card"></i> &#x8A02;&#x95B1;&#x7BA1;&#x7406;
                </a>
            </div>
        </div>
        <div class="hero-stack">
            <article class="signal-card signal-card-primary">
                <span class="signal-label">Live Focus</span>
                <strong>Subscriptions + Food Ops</strong>
                <p>&#x8FFD;&#x8E64;&#x4E0B;&#x4E00;&#x6B21;&#x4ED8;&#x6B3E;&#x8207;&#x98DF;&#x6750;&#x72C0;&#x614B;&#xFF0C;&#x96C6;&#x4E2D;&#x8655;&#x7406;&#x91CD;&#x8981;&#x63D0;&#x9192;&#x3002;</p>
            </article>
            <article class="signal-card">
                <span class="signal-label">Interaction Goal</span>
                <strong>Fast scan, low friction</strong>
                <p>&#x7528;&#x6700;&#x4F4E;&#x6469;&#x64E6;&#x7684;&#x64CD;&#x4F5C;&#x5B8C;&#x6210;&#x65E5;&#x5E38;&#x7DAD;&#x8B77;&#x8207;&#x6574;&#x7406;&#x3002;</p>
            </article>
        </div>
    </section>
</div>

<style>
    .sleep-warning {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
        padding: 16px 20px;
        border-radius: 20px;
        border: 1px solid transparent;
        box-shadow: 0 16px 38px rgba(15, 23, 42, 0.12);
        font-size: 1.1rem;
        color: #111827;
    }

    .sleep-warning i {
        font-size: 1.25rem;
    }

    .sleep-warning-yellow {
        background: linear-gradient(180deg, rgba(254, 243, 199, 0.96), rgba(253, 230, 138, 0.86));
        border-color: rgba(245, 158, 11, 0.36);
    }

    .sleep-warning-red {
        background: linear-gradient(180deg, rgba(254, 226, 226, 0.96), rgba(252, 165, 165, 0.9));
        border-color: rgba(239, 68, 68, 0.42);
        color: #7f1d1d;
    }

    [data-theme="dark"] .sleep-warning {
        box-shadow: 0 18px 44px rgba(0, 0, 0, 0.3);
    }

    [data-theme="dark"] .sleep-warning-yellow {
        background: linear-gradient(180deg, rgba(146, 64, 14, 0.76), rgba(120, 53, 15, 0.82));
        border-color: rgba(251, 191, 36, 0.36);
        color: #fef3c7;
    }

    [data-theme="dark"] .sleep-warning-red {
        background: linear-gradient(180deg, rgba(127, 29, 29, 0.78), rgba(153, 27, 27, 0.86));
        border-color: rgba(248, 113, 113, 0.4);
        color: #fee2e2;
    }

    .service-notice {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
        padding: 16px 20px;
        border-radius: 20px;
        border: 1px solid rgba(245, 158, 11, 0.36);
        background: linear-gradient(180deg, rgba(254, 243, 199, 0.96), rgba(255, 251, 235, 0.78));
        color: #78350f;
        box-shadow: 0 16px 38px rgba(120, 53, 15, 0.12);
        font-size: 1.05rem;
    }

    .service-notice i {
        color: #d97706;
        font-size: 1.2rem;
    }

    [data-theme="dark"] .service-notice {
        background: linear-gradient(180deg, rgba(120, 53, 15, 0.86), rgba(69, 26, 3, 0.82));
        border-color: rgba(251, 191, 36, 0.32);
        color: #fef3c7;
        box-shadow: 0 18px 44px rgba(0, 0, 0, 0.3);
    }

    [data-theme="dark"] .service-notice i {
        color: #fde68a;
    }

    .service-countdown {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 18px;
        padding: 18px 22px;
        border-radius: 22px;
        border: 1px solid rgba(14, 165, 233, 0.28);
        background: linear-gradient(180deg, rgba(224, 242, 254, 0.94), rgba(240, 249, 255, 0.76));
        color: #0f172a;
        box-shadow: 0 16px 38px rgba(14, 116, 144, 0.12);
    }

    .service-countdown-copy {
        display: grid;
        gap: 4px;
    }

    .service-countdown-label {
        color: #0369a1;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .service-countdown-copy strong {
        font-size: 1.05rem;
        line-height: 1.45;
    }

    .service-countdown-days {
        display: flex;
        align-items: baseline;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.72);
        color: #075985;
        flex: 0 0 auto;
    }

    .service-countdown-days span {
        font-size: 2rem;
        font-weight: 900;
        line-height: 1;
    }

    .service-countdown-days small {
        font-size: 0.9rem;
        font-weight: 700;
    }

    .tube-home-notice {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 18px;
        padding: 16px 20px;
        border-radius: 20px;
        border: 1px solid rgba(239, 68, 68, 0.26);
        background: linear-gradient(180deg, rgba(254, 226, 226, 0.94), rgba(255, 247, 237, 0.76));
        color: #7f1d1d;
        text-decoration: none;
        box-shadow: 0 16px 38px rgba(185, 28, 28, 0.12);
    }

    .tube-home-notice > i:first-child {
        color: #dc2626;
        font-size: 1.4rem;
    }

    .tube-home-notice span {
        display: grid;
        gap: 4px;
        min-width: 0;
        flex: 1;
    }

    .tube-home-notice small {
        color: #991b1b;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    [data-theme="dark"] .tube-home-notice {
        background: linear-gradient(180deg, rgba(127, 29, 29, 0.76), rgba(69, 26, 3, 0.76));
        border-color: rgba(248, 113, 113, 0.32);
        color: #fee2e2;
    }

    [data-theme="dark"] .tube-home-notice small {
        color: #fecaca;
    }

    .finance-home-notice {
        border-color: rgba(245, 158, 11, 0.32);
        background: linear-gradient(180deg, rgba(254, 243, 199, 0.96), rgba(255, 251, 235, 0.78));
        color: #78350f;
        box-shadow: 0 16px 38px rgba(180, 83, 9, 0.12);
    }

    .finance-home-notice > i:first-child {
        color: #d97706;
    }

    .finance-home-notice small {
        color: #92400e;
    }

    [data-theme="dark"] .finance-home-notice {
        background: linear-gradient(180deg, rgba(120, 53, 15, 0.76), rgba(69, 26, 3, 0.76));
        border-color: rgba(251, 191, 36, 0.34);
        color: #fef3c7;
    }

    [data-theme="dark"] .service-countdown {
        background: linear-gradient(180deg, rgba(12, 74, 110, 0.82), rgba(8, 47, 73, 0.76));
        border-color: rgba(125, 211, 252, 0.28);
        color: #e0f2fe;
        box-shadow: 0 18px 44px rgba(0, 0, 0, 0.3);
    }

    [data-theme="dark"] .service-countdown-label,
    [data-theme="dark"] .service-countdown-days {
        color: #bae6fd;
    }

    [data-theme="dark"] .service-countdown-days {
        background: rgba(15, 23, 42, 0.44);
    }

    @media (max-width: 640px) {
        .service-countdown {
            align-items: flex-start;
            flex-direction: column;
        }

        .service-countdown-days {
            width: 100%;
            justify-content: center;
        }
    }

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
