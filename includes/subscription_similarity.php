<?php
/**
 * 訂閱相似服務邏輯（純函式）— 對齊 Appwrite lib/subscriptionSimilarity.ts。
 * - 去除「(複製)」後綴
 * - 雙向對稱：自己的名稱/備註包含對方關鍵字、或對方包含自己的關鍵字都算
 * - 同 `/` 路徑前綴視為家族（身心科/門診 ↔ 身心科/處方籤）
 */

function fengbroSubStripCopySuffix($value)
{
    $term = trim((string) $value);
    while (preg_match('/\s*[（(]\s*(?:複製|copy)\s*[）)]\s*$/iu', $term)) {
        $term = trim(preg_replace('/\s*[（(]\s*(?:複製|copy)\s*[）)]\s*$/iu', '', $term));
    }
    return $term !== '' ? $term : trim((string) $value);
}

function fengbroSubNormalizeText($value)
{
    $value = preg_replace('/\s+/u', ' ', trim((string) $value));
    return function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
}

/** 路徑家族詞：`身心科/門診` → [身心科, 身心科/門診] */
function fengbroSubFamilyTerms($value)
{
    $term = fengbroSubStripCopySuffix($value);
    if ($term === '') {
        return [];
    }
    $segments = array_values(array_filter(array_map('trim', preg_split('/[\/／]/u', $term)), static fn($s) => $s !== ''));
    $terms = [];
    for ($i = 1; $i <= count($segments); $i++) {
        $terms[] = implode('/', array_slice($segments, 0, $i));
    }
    return $terms;
}

function fengbroSubContainsTerm($name, $note, $term)
{
    $term = fengbroSubNormalizeText($term);
    if ($term === '') {
        return false;
    }
    $nameText = fengbroSubNormalizeText($name);
    if ($nameText !== '' && str_contains($nameText, $term)) {
        return true;
    }
    $noteText = fengbroSubNormalizeText($note);
    return $noteText !== '' && str_contains($noteText, $term);
}

/** 雙向＋路徑前綴的相似判斷（id 相同視為自己，回 false）。 */
function fengbroSubIsSimilar(array $a, array $b): bool
{
    if (($a['id'] ?? '') !== '' && ($a['id'] ?? '') === ($b['id'] ?? '')) {
        return false;
    }
    $aTerms = fengbroSubFamilyTerms($a['name'] ?? '');
    if ($aTerms) {
        $bTermSet = array_flip(fengbroSubFamilyTerms($b['name'] ?? ''));
        foreach ($aTerms as $term) {
            if (isset($bTermSet[$term])) {
                return true;
            }
        }
    }
    $aTerm = fengbroSubStripCopySuffix($a['name'] ?? '');
    $bTerm = fengbroSubStripCopySuffix($b['name'] ?? '');
    if ($aTerm !== '' && fengbroSubContainsTerm($b['name'] ?? '', $b['note'] ?? '', $aTerm)) {
        return true;
    }
    if ($bTerm !== '' && fengbroSubContainsTerm($a['name'] ?? '', $a['note'] ?? '', $bTerm)) {
        return true;
    }
    return false;
}

/** 替群組挑選最能涵蓋全組的關鍵字：覆蓋最多筆，平手取較短詞。 */
function fengbroSubPickTerm(array $self, array $similar): string
{
    $rows = array_merge([$self], $similar);
    $candidates = [];
    foreach ($rows as $row) {
        foreach (fengbroSubFamilyTerms($row['name'] ?? '') as $term) {
            $candidates[$term] = true;
        }
        $candidates[fengbroSubStripCopySuffix($row['name'] ?? '')] = true;
    }
    $candidates = array_values(array_filter(array_keys($candidates), function ($term) use ($self) {
        return $term !== '' && fengbroSubContainsTerm($self['name'] ?? '', $self['note'] ?? '', $term);
    }));
    if (!$candidates) {
        return fengbroSubStripCopySuffix($self['name'] ?? '');
    }
    usort($candidates, function ($left, $right) use ($rows) {
        $coverage = function ($term) use ($rows) {
            $count = 0;
            foreach ($rows as $row) {
                if (fengbroSubContainsTerm($row['name'] ?? '', $row['note'] ?? '', $term)) {
                    $count++;
                }
            }
            return $count;
        };
        $diff = $coverage($right) - $coverage($left);
        if ($diff !== 0) {
            return $diff;
        }
        return (function_exists('mb_strlen') ? mb_strlen($left, 'UTF-8') : strlen($left))
            - (function_exists('mb_strlen') ? mb_strlen($right, 'UTF-8') : strlen($right));
    });
    return $candidates[0];
}
