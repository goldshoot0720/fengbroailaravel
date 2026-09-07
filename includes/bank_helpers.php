<?php

function bankNormalizeText($value)
{
    return function_exists('mb_strtolower')
        ? mb_strtolower((string) $value, 'UTF-8')
        : strtolower((string) $value);
}

function bankTextContains($haystack, $needle)
{
    if ($needle === '') {
        return false;
    }

    return function_exists('mb_strpos')
        ? mb_strpos($haystack, bankNormalizeText($needle)) !== false
        : strpos($haystack, bankNormalizeText($needle)) !== false;
}

function bankDisplayUrl($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function isTaiwanBankAccount($item)
{
    $haystack = bankNormalizeText(
        ($item['name'] ?? '') . ' ' .
        ($item['site'] ?? '') . ' ' .
        ($item['account'] ?? '') . ' ' .
        ($item['card'] ?? '')
    );
    $bankKeywords = [
        '臺灣銀行', '台灣銀行', '土地銀行', '合作金庫', '合庫', '第一銀行', '華南銀行', '彰化銀行',
        '上海商銀', '台北富邦', '富邦銀行', '國泰世華', '高雄銀行', '兆豐銀行', '花旗銀行',
        '王道銀行', '臺灣企銀', '台灣企銀', '渣打銀行', '台中銀行', '京城銀行', '滙豐銀行',
        '匯豐銀行', '瑞興銀行', '華泰銀行', '新光銀行', '陽信銀行', '板信銀行', '三信銀行',
        '聯邦銀行', '遠東銀行', '元大銀行', '永豐銀行', '玉山銀行', '凱基銀行', '星展銀行',
        '台新銀行', '安泰銀行', '中國信託', '中信銀行', '將來銀行', '連線銀行', 'line bank',
        '樂天銀行', '郵局', '郵政', '中華郵政', '農會', '漁會', '信用合作社'
    ];

    foreach ($bankKeywords as $keyword) {
        if (bankTextContains($haystack, $keyword)) {
            return true;
        }
    }

    return preg_match('/銀行|bank/u', $haystack) === 1;
}
