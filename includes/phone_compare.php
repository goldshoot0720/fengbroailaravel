<?php
/**
 * 手機比價：對齊 fengbroaiappwrite 的 landtop + jyes 抓取與合併邏輯。
 */

function phoneCompareUserAgent(): string
{
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36';
}

function phoneCompareFetchText(string $url, int $timeout = 12, array $extraHeaders = []): string
{
    $headers = array_merge([
        'User-Agent: ' . phoneCompareUserAgent(),
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain,*/*;q=0.8',
        'Accept-Language: zh-TW,zh;q=0.9,en;q=0.8',
    ], $extraHeaders);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ($status >= 200 && $status < 400 && is_string($body)) ? $body : '';
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers) . "\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    return is_string($body) ? $body : '';
}

function phoneCompareNormalizeSpace(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function phoneCompareDecodeHtml(string $value): string
{
    return html_entity_decode(
        str_replace(['&nbsp;'], [' '], $value),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
}

function phoneCompareStripTags(string $value): string
{
    return phoneCompareNormalizeSpace(phoneCompareDecodeHtml(strip_tags($value)));
}

function phoneCompareParsePrice(?string $line): ?int
{
    if ($line === null || $line === '') {
        return null;
    }
    if (str_contains($line, '特價請洽門市') || str_contains($line, '即將開賣') || str_contains($line, '挑戰手機最低價')) {
        return null;
    }
    $raw = preg_replace('/[^\d]/', '', $line);
    if ($raw === '' || $raw === null) {
        return null;
    }
    $price = (int) $raw;
    return ($price >= 10 && $price <= 5000000) ? $price : null;
}

function phoneCompareFormatPriceLabel(?int $price, string $fallback = '挑戰手機最低價'): string
{
    if ($price === null) {
        return $fallback;
    }
    return 'NT$ ' . number_format($price);
}

function phoneCompareCreateProductId(string $brand, string $name): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? $name);
    $slug = trim($slug, '-');
    return $brand . '-' . $slug;
}

function phoneCompareNormalizeVariantName(string $value): string
{
    $value = preg_replace('/\b(\d{3,4})G\b/i', '$1GB', $value) ?? $value;
    $value = str_replace('/', ' ', $value);
    return phoneCompareNormalizeSpace($value);
}

function phoneCompareNormalizeQuery(string $value): array
{
    $value = preg_replace('/\b(\d{3,4})G\b/i', '$1GB', $value) ?? $value;
    $value = str_replace('/', ' ', $value);
    $value = phoneCompareNormalizeSpace(mb_strtolower($value, 'UTF-8'));
    if ($value === '') {
        return [];
    }
    return preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function phoneCompareNormalizeCompareName(string $value): string
{
    $value = preg_replace('/\[(.*?)\]/u', '', $value) ?? $value;
    $value = preg_replace('/[()]/u', ' ', $value) ?? $value;
    $value = preg_replace('/\b(\d{3,4})G\b/i', '$1GB', $value) ?? $value;
    $value = str_replace('/', ' ', $value);
    return phoneCompareNormalizeSpace(mb_strtolower($value, 'UTF-8'));
}

function phoneCompareMatchesQuery(array $product, string $query): bool
{
    $tokens = phoneCompareNormalizeQuery($query);
    if (!$tokens) {
        return true;
    }
    $haystack = phoneCompareNormalizeQuery(($product['brand'] ?? '') . ' ' . ($product['name'] ?? ''));
    $haystackText = implode(' ', $haystack);
    foreach ($tokens as $token) {
        if (!str_contains($haystackText, $token)) {
            return false;
        }
    }
    return true;
}

function phoneCompareIsProductTitle(string $name, string $brand): bool
{
    if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
        return false;
    }
    if ($brand === 'samsung') {
        return (bool) preg_match('/^Samsung\s+/i', $name);
    }
    return (bool) preg_match('/^(iPhone|iPad|AirPods|Apple Watch|Apple\s+)/i', $name);
}

function phoneCompareInferBrand(string $value): string
{
    $lowered = mb_strtolower($value, 'UTF-8');
    if (str_contains($lowered, 'iphone') || str_contains($lowered, 'apple')) {
        return 'apple';
    }
    if (str_contains($lowered, 'samsung')) {
        return 'samsung';
    }
    return 'other';
}

function phoneCompareReaderUrl(string $url): string
{
    // jina.ai reader proxy（與 Appwrite 版相同策略）
    $clean = preg_replace('~^https?://~i', '', $url) ?? $url;
    return 'https://r.jina.ai/http://' . $clean;
}

function phoneCompareFetchPage(string $url): array
{
    $direct = phoneCompareFetchText($url, 12, ['Referer: https://www.landtop.com.tw/']);
    if ($direct !== '') {
        return ['html' => $direct, 'via' => 'direct'];
    }
    $reader = phoneCompareFetchText(phoneCompareReaderUrl($url), 15);
    if ($reader !== '') {
        return ['html' => $reader, 'via' => 'reader'];
    }
    return ['html' => '', 'via' => 'failed'];
}

function phoneCompareParseBrandProductsFromMarkdown(string $markdown, string $brand): array
{
    $products = [];
    $pattern = '/##\s+\[([^\]]+)\]\((https:\/\/www\.landtop\.com\.tw\/products\/[^)]+)\)[\s\S]{0,240}?建議售價[:：]\$?([\d,]+)[\s\S]{0,120}?地標價[:：](挑戰手機最低價|\$?[\d,]+)/u';
    if (!preg_match_all($pattern, $markdown, $matches, PREG_SET_ORDER)) {
        return [];
    }
    foreach ($matches as $match) {
        $name = phoneCompareNormalizeSpace($match[1]);
        if (!phoneCompareIsProductTitle($name, $brand)) {
            continue;
        }
        $suggestedPrice = phoneCompareParsePrice($match[3]);
        $landtopPrice = str_contains($match[4], '挑戰手機最低價') ? null : phoneCompareParsePrice($match[4]);
        $id = phoneCompareCreateProductId($brand, $name);
        $products[$id] = [
            'id' => $id,
            'brand' => $brand,
            'name' => $name,
            'suggestedPrice' => $suggestedPrice,
            'landtopPrice' => $landtopPrice,
            'landtopPriceLabel' => phoneCompareFormatPriceLabel($landtopPrice),
            'sourceUrl' => $match[2],
        ];
    }
    return array_values($products);
}

function phoneCompareParseBrandProducts(string $html, string $brand): array
{
    if (str_contains($html, 'Markdown Content:') && str_contains($html, '## [')) {
        $markdownProducts = phoneCompareParseBrandProductsFromMarkdown($html, $brand);
        if ($markdownProducts) {
            return $markdownProducts;
        }
    }

    $products = [];
    $pattern = '/<a[^>]+href="(\/products\/[^"]+)"[\s\S]{0,1800}?(?:<h3[^>]*>|<div class="product-name[^"]*">|<img[^>]+alt=")([\s\S]*?)(?:<\/h3>|<\/div>|")/iu';
    if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($matches as $match) {
        $sourceUrl = 'https://www.landtop.com.tw' . $match[1];
        $name = phoneCompareNormalizeSpace(phoneCompareStripTags($match[2]));
        if (!phoneCompareIsProductTitle($name, $brand)) {
            continue;
        }
        $chunk = mb_substr($html, max(0, (int) mb_strpos($html, $match[0])), 2400, 'UTF-8');
        $suggestedPrice = null;
        $landtopPrice = null;
        if (preg_match('/建議售價[\s\S]{0,120}?(\$?\s*[\d,]+)/iu', $chunk, $sm)) {
            $suggestedPrice = phoneCompareParsePrice($sm[1]);
        }
        if (preg_match('/地標價[\s\S]{0,120}?(\$?\s*[\d,]+)/iu', $chunk, $lm)
            || preg_match('/挑戰手機最低價[\s\S]{0,120}?(\$?\s*[\d,]+)/iu', $chunk, $lm)
        ) {
            $landtopPrice = phoneCompareParsePrice($lm[1]);
        }
        $id = phoneCompareCreateProductId($brand, $name);
        $products[$id] = [
            'id' => $id,
            'brand' => $brand,
            'name' => $name,
            'suggestedPrice' => $suggestedPrice,
            'landtopPrice' => $landtopPrice,
            'landtopPriceLabel' => phoneCompareFormatPriceLabel($landtopPrice),
            'sourceUrl' => $sourceUrl,
        ];
    }
    return array_values($products);
}

function phoneCompareParseProductVariant(string $html, string $brand, string $sourceUrl): ?array
{
    if (!preg_match('/<div class="price-product-name">([\s\S]*?)<\/div>/iu', $html, $nameMatch)) {
        return null;
    }
    $rawName = phoneCompareStripTags($nameMatch[1]);
    $parts = explode('|', $rawName);
    $name = phoneCompareNormalizeVariantName($parts[0] ?? $rawName);
    $suggestedPrice = null;
    $landtopPrice = null;
    $landtopLabel = '';
    if (preg_match('/text-strikethrough[^"]*">([\s\S]*?)<\/div>/iu', $html, $sm)) {
        $suggestedPrice = phoneCompareParsePrice(phoneCompareStripTags($sm[1]));
    }
    if (preg_match('/discount-price">([\s\S]*?)<\/div>/iu', $html, $dm)) {
        $landtopLabel = phoneCompareStripTags($dm[1]);
        $landtopPrice = phoneCompareParsePrice($landtopLabel);
    }
    $id = phoneCompareCreateProductId($brand, $name);
    return [
        'id' => $id,
        'brand' => $brand,
        'name' => $name,
        'suggestedPrice' => $suggestedPrice,
        'landtopPrice' => $landtopPrice,
        'landtopPriceLabel' => phoneCompareFormatPriceLabel($landtopPrice, $landtopLabel !== '' ? $landtopLabel : '挑戰手機最低價'),
        'sourceUrl' => $sourceUrl,
    ];
}

function phoneCompareParseProductMarkdownVariants(string $markdown, string $brand, string $sourceUrl): array
{
    $normalized = str_replace("\r", '', $markdown);
    $products = [];
    $brandPattern = $brand === 'samsung' ? 'Samsung' : 'Apple|iPhone';
    $namePattern = '/(' . $brandPattern . '[^\n]+?(?:\d+G\/\d+G|\d+GB|\d+G\s+\d+GB))([\s\S]{0,220}?建議售價\s*\$?[\d,]+[\s\S]{0,120}?地標(?:最低)?價[\s\S]{0,80}?\$?[\d,]+)/iu';
    if (preg_match_all($namePattern, $normalized, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $nameParts = explode('|', $match[1]);
            $name = phoneCompareNormalizeVariantName($nameParts[0] ?? $match[1]);
            $chunk = $match[2];
            $suggestedPrice = null;
            $landtopPrice = null;
            if (preg_match('/建議售價\s*\$?([\d,]+)/u', $chunk, $sm)) {
                $suggestedPrice = phoneCompareParsePrice($sm[1]);
            }
            if (preg_match('/地標(?:最低)?價[\s\S]{0,40}?\$?([\d,]+)/u', $chunk, $lm)) {
                $landtopPrice = phoneCompareParsePrice($lm[1]);
            }
            $id = phoneCompareCreateProductId($brand, $name);
            $products[$id] = [
                'id' => $id,
                'brand' => $brand,
                'name' => $name,
                'suggestedPrice' => $suggestedPrice,
                'landtopPrice' => $landtopPrice,
                'landtopPriceLabel' => phoneCompareFormatPriceLabel($landtopPrice),
                'sourceUrl' => $sourceUrl,
            ];
        }
    }
    return array_values($products);
}

function phoneCompareFetchVariantProduct(string $brand, string $url, string $productId, string $variantId): ?array
{
    $variantUrl = 'https://www.landtop.com.tw/products/variants?product_id=' . rawurlencode($productId) . '&variant_id=' . rawurlencode($variantId);
    $html = phoneCompareFetchText($variantUrl, 10, [
        'Accept: text/vnd.turbo-stream.html',
        'X-Requested-With: XMLHttpRequest',
        'Referer: ' . $url,
    ]);
    if ($html === '') {
        return null;
    }
    return phoneCompareParseProductVariant($html, $brand, $url);
}

function phoneCompareFetchProductVariantsFromUrl(string $brand, string $url): array
{
    if ($url === '' || !preg_match('~/products/~i', $url)) {
        return ['products' => [], 'via' => 'direct'];
    }
    $page = phoneCompareFetchPage($url);
    $html = $page['html'];
    if ($html === '') {
        return ['products' => [], 'via' => $page['via']];
    }
    if (str_contains($html, 'Markdown Content:')) {
        $markdownProducts = phoneCompareParseProductMarkdownVariants($html, $brand, $url);
        if ($markdownProducts) {
            return ['products' => $markdownProducts, 'via' => $page['via']];
        }
    }

    $variants = [];
    if (preg_match_all('/data-product-id="(\d+)"[\s\S]{0,220}?data-variant-id="(\d+)"[\s\S]{0,200}?<div class="label-price">([^<]+)<\/div>/u', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $label = phoneCompareStripTags($match[3]);
            if (!preg_match('/(\d{3,4}GB|\d{3,4}G|\d+G\/\d+G)/i', $label)) {
                continue;
            }
            $product = phoneCompareFetchVariantProduct($brand, $url, $match[1], $match[2]);
            if ($product) {
                $variants[$product['id']] = $product;
            }
        }
    }

    if ($variants) {
        return ['products' => array_values($variants), 'via' => $page['via']];
    }

    $single = phoneCompareParseProductVariant($html, $brand, $url);
    return ['products' => $single ? [$single] : [], 'via' => $page['via']];
}

function phoneCompareFetchLandtopCatalog(string $query = ''): array
{
    $sources = [
        ['brand' => 'samsung', 'url' => 'https://www.landtop.com.tw/brands?brand=samsung'],
        ['brand' => 'apple', 'url' => 'https://www.landtop.com.tw/brands?brand=apple'],
    ];
    $productSources = [
        [
            'brand' => 'apple',
            'url' => 'https://www.landtop.com.tw/products/apple-iphone-17',
            'productId' => '3313',
            'variants' => ['40', '41'],
        ],
        [
            'brand' => 'samsung',
            'url' => 'https://www.landtop.com.tw/products/samsung-s26-ceab4a58-8c4f-4b86-9fbc-9bc3211457a9',
            'productId' => '3469',
            'variants' => ['396', '432'],
        ],
    ];

    $allProducts = [];
    $warnings = [];
    $fetchedVia = [];
    $sourceUrls = array_map(static fn($s) => $s['url'], array_merge($sources, $productSources));

    foreach ($sources as $source) {
        $page = phoneCompareFetchPage($source['url']);
        $fetchedVia[] = $page['via'];
        if ($page['html'] === '') {
            $warnings[] = '地標網通 ' . $source['brand'] . ' 品牌頁抓取失敗';
            continue;
        }
        foreach (phoneCompareParseBrandProducts($page['html'], $source['brand']) as $product) {
            $allProducts[$product['id']] = $product;
        }
    }

    foreach ($productSources as $source) {
        $staticProducts = [];
        foreach ($source['variants'] as $variantId) {
            $product = phoneCompareFetchVariantProduct($source['brand'], $source['url'], $source['productId'], $variantId);
            if ($product) {
                $staticProducts[] = $product;
            }
        }
        if ($staticProducts) {
            $fetchedVia[] = 'direct';
            foreach ($staticProducts as $product) {
                $allProducts[$product['id']] = $product;
            }
            continue;
        }
        $group = phoneCompareFetchProductVariantsFromUrl($source['brand'], $source['url']);
        $fetchedVia[] = $group['via'];
        foreach ($group['products'] as $product) {
            $allProducts[$product['id']] = $product;
        }
    }

    $matched = array_values(array_filter($allProducts, static fn($p) => phoneCompareMatchesQuery($p, $query)));
    foreach ($matched as $product) {
        $name = (string) ($product['name'] ?? '');
        $hasVariant = (bool) preg_match('/(\d{3,4}GB|\d{3,4}G|\d+G\s+\d+GB|\d+G\/\d+G)/i', $name);
        $sourceUrl = (string) ($product['sourceUrl'] ?? '');
        if ($hasVariant || !preg_match('~/products/~i', $sourceUrl)) {
            continue;
        }
        $group = phoneCompareFetchProductVariantsFromUrl((string) $product['brand'], $sourceUrl);
        foreach ($group['products'] as $variantProduct) {
            $allProducts[$variantProduct['id']] = $variantProduct;
        }
    }

    $products = array_values(array_filter($allProducts, static fn($p) => phoneCompareMatchesQuery($p, $query)));
    usort($products, static function ($a, $b) {
        $aPrice = $a['landtopPrice'] ?? $a['suggestedPrice'] ?? PHP_INT_MAX;
        $bPrice = $b['landtopPrice'] ?? $b['suggestedPrice'] ?? PHP_INT_MAX;
        return $aPrice <=> $bPrice;
    });

    return [
        'source' => '地標網通',
        'sourceUrls' => $sourceUrls,
        'query' => $query,
        'fetchedAt' => date('c'),
        'fetchedVia' => array_values(array_unique(array_filter($fetchedVia))),
        'warnings' => $warnings,
        'total' => count($products),
        'products' => $products,
    ];
}

function phoneCompareNormalizeJyesName(string $value): string
{
    $value = phoneCompareNormalizeSpace($value);
    $value = preg_replace('/^三星/u', 'Samsung', $value) ?? $value;
    $value = preg_replace('/^蘋果/u', 'Apple', $value) ?? $value;
    return phoneCompareNormalizeVariantName($value);
}

function phoneCompareBuildJyesUrl(string $name): string
{
    $slug = preg_replace('/\b(\d{3,4})GB\b/i', '$1G', $name) ?? $name;
    $slug = preg_replace('/^Samsung\s+/i', 'SAMSUNG-', $slug) ?? $slug;
    $slug = preg_replace('/^Apple\s+/i', 'APPLE-', $slug) ?? $slug;
    $slug = preg_replace('/\s+/u', '-', $slug) ?? $slug;
    $slug = preg_replace('/[^A-Za-z0-9-]/', '', $slug) ?? $slug;
    $slug = preg_replace('/-+/', '-', $slug) ?? $slug;
    $slug = trim($slug, '-');
    return 'https://www.jyes.com.tw/product/' . $slug;
}

function phoneCompareParseJyesProducts(string $markdown): array
{
    $products = [];
    $rowPattern = '/^([^\t\n]+?)(?:\n[^\t\n]+)*\n?\t([^\t\n]+)\t([^\t\n]+)\t([^\t\n]+)\t[^\t\n]+$/mu';
    if (!preg_match_all($rowPattern, $markdown, $matches, PREG_SET_ORDER)) {
        // 備用：寬鬆解析「名稱 + 多個價格欄」
        $lines = preg_split('/\n+/', $markdown) ?: [];
        foreach ($lines as $line) {
            if (!preg_match('/(Samsung|Apple|iPhone|三星|蘋果).{0,80}?(\d{1,3}(?:,\d{3})+)/iu', $line, $m)) {
                continue;
            }
            $name = phoneCompareNormalizeJyesName($m[1] . (preg_match('/(Samsung|Apple|iPhone|三星|蘋果)([^\t\d$]+)/iu', $line, $nm) ? $nm[2] : ''));
            if ($name === '') {
                continue;
            }
            $brand = phoneCompareInferBrand($name);
            if ($brand === 'other') {
                continue;
            }
            $price = phoneCompareParsePrice($m[2]);
            $id = 'jyes-' . phoneCompareCreateProductId($brand, $name);
            $products[$id] = [
                'id' => $id,
                'brand' => $brand,
                'name' => $name,
                'suggestedPrice' => null,
                'jyesPrice' => $price,
                'jyesPriceLabel' => phoneCompareFormatPriceLabel($price, '門市洽詢'),
                'jyesUrl' => phoneCompareBuildJyesUrl($name),
            ];
        }
        return array_values($products);
    }

    foreach ($matches as $match) {
        $name = phoneCompareNormalizeJyesName($match[1]);
        $brand = phoneCompareInferBrand($name);
        if ($brand === 'other') {
            continue;
        }
        $suggestedPrice = phoneCompareParsePrice($match[2]);
        $jyesPrice = phoneCompareParsePrice($match[4]);
        $id = phoneCompareCreateProductId('jyes', $name);
        $products[$id] = [
            'id' => $id,
            'brand' => $brand,
            'name' => $name,
            'suggestedPrice' => $suggestedPrice,
            'jyesPrice' => $jyesPrice,
            'jyesPriceLabel' => phoneCompareFormatPriceLabel($jyesPrice, '門市洽詢'),
            'jyesUrl' => phoneCompareBuildJyesUrl($name),
        ];
    }
    return array_values($products);
}

function phoneCompareFetchJyesCatalog(string $query = ''): array
{
    $sourceUrl = 'https://www.jyes.com.tw/product.php';
    $html = phoneCompareFetchText(phoneCompareReaderUrl($sourceUrl), 15);
    $products = [];
    $warning = null;
    if ($html === '') {
        $warning = '傑昇通信資料讀取失敗';
    } else {
        $products = array_values(array_filter(
            phoneCompareParseJyesProducts($html),
            static fn($p) => phoneCompareMatchesQuery($p, $query)
        ));
    }

    return [
        'source' => '傑昇通信',
        'sourceUrl' => $sourceUrl,
        'query' => $query,
        'fetchedAt' => date('c'),
        'total' => count($products),
        'products' => $products,
        'warning' => $warning,
    ];
}

function phoneCompareMergeProducts(array $landtopProducts, array $jyesProducts): array
{
    $jyesByName = [];
    foreach ($jyesProducts as $product) {
        $jyesByName[phoneCompareNormalizeCompareName((string) ($product['name'] ?? ''))] = $product;
    }

    $merged = [];
    foreach ($landtopProducts as $product) {
        $key = phoneCompareNormalizeCompareName((string) ($product['name'] ?? ''));
        $jyesMatch = $jyesByName[$key] ?? null;
        $landtopPrice = isset($product['landtopPrice']) && is_int($product['landtopPrice']) ? $product['landtopPrice'] : null;
        $jyesPrice = ($jyesMatch && isset($jyesMatch['jyesPrice']) && is_int($jyesMatch['jyesPrice'])) ? $jyesMatch['jyesPrice'] : null;
        $candidates = array_values(array_filter([$landtopPrice, $jyesPrice], static fn($v) => is_int($v)));
        sort($candidates);
        $bestPrice = $candidates[0] ?? null;
        $bestSource = null;
        if ($bestPrice !== null) {
            $bestSource = ($bestPrice === $landtopPrice) ? '地標網通' : '傑昇通信';
        }
        $merged[] = array_merge($product, [
            'jyesPrice' => $jyesPrice,
            'jyesPriceLabel' => phoneCompareFormatPriceLabel($jyesPrice, '門市破盤價'),
            'jyesUrl' => $jyesMatch['jyesUrl'] ?? null,
            'bestPrice' => $bestPrice,
            'bestSourceLabel' => $bestSource,
        ]);
    }

    $known = [];
    foreach ($merged as $product) {
        $known[phoneCompareNormalizeCompareName((string) ($product['name'] ?? ''))] = true;
    }

    foreach ($jyesProducts as $product) {
        $key = phoneCompareNormalizeCompareName((string) ($product['name'] ?? ''));
        if (isset($known[$key])) {
            continue;
        }
        $jyesPrice = isset($product['jyesPrice']) && is_int($product['jyesPrice']) ? $product['jyesPrice'] : null;
        $merged[] = [
            'id' => $product['id'] ?? phoneCompareCreateProductId('jyes', (string) ($product['name'] ?? 'item')),
            'brand' => $product['brand'] ?? phoneCompareInferBrand((string) ($product['name'] ?? '')),
            'name' => $product['name'] ?? '',
            'suggestedPrice' => $product['suggestedPrice'] ?? null,
            'landtopPrice' => null,
            'landtopPriceLabel' => '挑戰手機最低價',
            'sourceUrl' => $product['jyesUrl'] ?? null,
            'jyesPrice' => $jyesPrice,
            'jyesPriceLabel' => $product['jyesPriceLabel'] ?? phoneCompareFormatPriceLabel($jyesPrice, '門市洽詢'),
            'jyesUrl' => $product['jyesUrl'] ?? null,
            'bestPrice' => $jyesPrice,
            'bestSourceLabel' => $jyesPrice !== null ? '傑昇通信' : null,
        ];
    }

    usort($merged, static function ($a, $b) {
        $aPrice = $a['bestPrice'] ?? $a['landtopPrice'] ?? $a['jyesPrice'] ?? PHP_INT_MAX;
        $bPrice = $b['bestPrice'] ?? $b['landtopPrice'] ?? $b['jyesPrice'] ?? PHP_INT_MAX;
        return $aPrice <=> $bPrice;
    });

    return $merged;
}

/**
 * 主入口：查詢關鍵字並回傳合併後比價結果。
 */
function phoneCompareLookup(string $query = ''): array
{
    $query = trim($query);
    $landtop = phoneCompareFetchLandtopCatalog($query);
    $jyes = phoneCompareFetchJyesCatalog($query);
    $products = phoneCompareMergeProducts($landtop['products'] ?? [], $jyes['products'] ?? []);
    $warnings = $landtop['warnings'] ?? [];
    if (!empty($jyes['warning'])) {
        $warnings[] = $jyes['warning'];
    }

    $priced = array_values(array_filter(
        array_map(static fn($p) => $p['bestPrice'] ?? $p['landtopPrice'] ?? $p['jyesPrice'] ?? null, $products),
        static fn($v) => is_int($v)
    ));

    return [
        'source' => '手機比價',
        'sourceUrls' => array_values(array_unique(array_merge(
            $landtop['sourceUrls'] ?? [],
            !empty($jyes['sourceUrl']) ? [$jyes['sourceUrl']] : []
        ))),
        'query' => $query,
        'fetchedAt' => date('c'),
        'warnings' => $warnings,
        'total' => count($products),
        'products' => $products,
        'jyesFetchedAt' => $jyes['fetchedAt'] ?? null,
        'landtopFetchedVia' => $landtop['fetchedVia'] ?? [],
        'priceSummary' => [
            'current' => $priced[0] ?? null,
            'low' => $priced ? min($priced) : null,
            'high' => $priced ? max($priced) : null,
            'count' => count($priced),
        ],
        'targets' => [
            '地標網通' => 'https://www.landtop.com.tw/',
            '傑昇通信' => 'https://www.jyes.com.tw/product.php',
            'Google 地標' => 'https://www.google.com/search?q=' . rawurlencode('site:landtop.com.tw ' . $query),
            'Google 傑昇' => 'https://www.google.com/search?q=' . rawurlencode('site:jyes.com.tw ' . $query),
        ],
    ];
}
