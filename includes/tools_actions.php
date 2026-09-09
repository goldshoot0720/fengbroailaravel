<?php

/**
 * 鋒兄工具的 POST 動作（Tube 頻道管理、金融標的管理、CSV 匯出／匯入）。
 *
 * 每個分支最後都會送出 header()：重導或 CSV 下載。index.php 會先 include
 * header.php／sidebar.php 才 include 頁面檔，所以這些動作必須在 HTML 輸出之前
 * 執行，否則會噴 "headers already sent"，重導失效、CSV 也會混進 HTML 裡。
 */

require_once __DIR__ . '/fengbro_tube.php';
require_once __DIR__ . '/fengbro_finance.php';

function fengbroToolsHandlePostActions($toolSubpage)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if ($toolSubpage === 'tube' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tube_action'] ?? '') !== '') {
        $channels = fengbroTubeChannels();
        $action = (string) ($_POST['tube_action'] ?? '');
        $index = isset($_POST['channel_index']) ? (int) $_POST['channel_index'] : -1;
        $channel = [
            'name' => trim((string) ($_POST['channel_name'] ?? '')),
            'url' => trim((string) ($_POST['channel_url'] ?? '')),
        ];

        if ($action === 'reset') {
            fengbroTubeResetChannels();
        } elseif ($action === 'delete' && isset($channels[$index])) {
            array_splice($channels, $index, 1);
            fengbroTubeSaveChannels($channels);
        } elseif ($action === 'bulk_delete') {
            $indexes = array_map('intval', (array) ($_POST['channel_indexes'] ?? []));
            rsort($indexes);
            foreach ($indexes as $bulkIndex) {
                if (isset($channels[$bulkIndex])) {
                    array_splice($channels, $bulkIndex, 1);
                }
            }
            fengbroTubeSaveChannels($channels);
        } elseif ($action === 'save' && $channel['url'] !== '') {
            if ($index >= 0 && isset($channels[$index])) {
                $channels[$index] = $channel;
            } else {
                $channels[] = $channel;
            }
            fengbroTubeSaveChannels($channels);
        } elseif ($action === 'export_csv') {
            $channels = fengbroTubeChannels();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="fengbro-tube-channels.csv"');
            echo "\xEF\xBB\xBF";
            echo "alias,sourceUrl\n";
            foreach ($channels as $ch) {
                $alias = str_replace('"', '""', (string) ($ch['name'] ?? ''));
                $url = str_replace('"', '""', (string) ($ch['url'] ?? ''));
                echo '"' . $alias . '","' . $url . "\"\n";
            }
            exit;
        } elseif ($action === 'import_csv' && !empty($_FILES['tube_csv']['tmp_name'])) {
            $raw = (string) file_get_contents($_FILES['tube_csv']['tmp_name']);
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
            $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
            $imported = [];
            $start = 0;
            if ($lines && preg_match('/alias|sourceurl|網址|名稱/i', $lines[0])) {
                $start = 1;
            }
            for ($i = $start; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if ($line === '') {
                    continue;
                }
                // simple CSV split respecting quotes
                if (preg_match('/^"(.*)"\s*,\s*"(.*)"\s*$/u', $line, $m)) {
                    $alias = str_replace('""', '"', $m[1]);
                    $url = str_replace('""', '"', $m[2]);
                } else {
                    $parts = str_getcsv($line);
                    $alias = trim((string) ($parts[0] ?? ''));
                    $url = trim((string) ($parts[1] ?? $parts[0] ?? ''));
                    if (count($parts) < 2) {
                        $alias = '';
                    }
                }
                $url = trim($url);
                if ($url === '') {
                    continue;
                }
                $imported[] = ['name' => trim($alias), 'url' => $url];
                if (count($imported) >= 80) {
                    break;
                }
            }
            if ($imported) {
                // merge by URL
                $map = [];
                foreach (fengbroTubeChannels() as $ch) {
                    $u = trim((string) ($ch['url'] ?? ''));
                    if ($u !== '') {
                        $map[$u] = $ch;
                    }
                }
                foreach ($imported as $ch) {
                    $map[$ch['url']] = $ch;
                }
                fengbroTubeSaveChannels(array_values($map));
                header('Location: index.php?page=tools&tool=tube&refresh=1#tube-channel-manager');
                exit;
            }
            // CSV 有內容但沒有任何可匯入的頻道（對齊 Appwrite：已下架預設頻道明確報錯，不靜默略過）
            header('Location: index.php?page=tools&tool=tube&tube_import_error=1#tube-channel-manager');
            exit;
        }

        header('Location: index.php?page=tools&tool=tube&refresh=1#tube-channel-manager');
        exit;
    }
    if ($toolSubpage === 'finance' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['finance_action'] ?? '') !== '') {
        $action = (string) ($_POST['finance_action'] ?? '');
        $config = fengbroFinanceReadConfig();

        if ($action === 'reset') {
            fengbroFinanceResetConfig();
        } elseif ($action === 'save_defaults') {
            $ids = isset($_POST['default_ids']) && is_array($_POST['default_ids']) ? $_POST['default_ids'] : [];
            fengbroFinanceSaveDefaultIds($ids);
        } elseif ($action === 'remove_default') {
            $removeId = trim((string) ($_POST['instrument_id'] ?? ''));
            $ids = array_values(array_filter($config['defaultIds'], static fn($id) => $id !== $removeId));
            fengbroFinanceSaveDefaultIds($ids);
        } elseif ($action === 'add_default') {
            $addId = trim((string) ($_POST['instrument_id'] ?? ''));
            $ids = $config['defaultIds'];
            if ($addId !== '' && !in_array($addId, $ids, true)) {
                $ids[] = $addId;
            }
            fengbroFinanceSaveDefaultIds($ids);
        } elseif ($action === 'save_custom') {
            $custom = $config['custom'];
            $instrument = fengbroFinanceNormalizeCustomInstrument([
                'name' => $_POST['custom_name'] ?? '',
                'symbol' => $_POST['custom_symbol'] ?? '',
                'provider' => $_POST['custom_provider'] ?? 'yahoo',
                'group' => $_POST['custom_group'] ?? 'US',
                'imageUrlsText' => $_POST['custom_image_urls'] ?? '',
                'youtubeUrl' => $_POST['custom_youtube_url'] ?? '',
                'bilibiliUrl' => $_POST['custom_bilibili_url'] ?? '',
                'relatedLinksText' => $_POST['custom_related_links'] ?? '',
            ], count($custom));
            if ($instrument) {
                $replaced = false;
                foreach ($custom as $i => $row) {
                    $sameId = ($row['id'] ?? '') === ($instrument['id'] ?? '');
                    $sameSym = strtoupper((string) ($row['symbol'] ?? '')) === strtoupper((string) ($instrument['symbol'] ?? ''))
                        && ($instrument['symbol'] ?? '') !== '';
                    if ($sameId || $sameSym) {
                        // Keep stable custom id when updating by symbol
                        if (!empty($row['id'])) {
                            $instrument['id'] = $row['id'];
                        }
                        $custom[$i] = $instrument;
                        $replaced = true;
                        break;
                    }
                }
                if (!$replaced) {
                    $custom[] = $instrument;
                }
                fengbroFinanceSaveCustomInstruments($custom);
                // Persist image map for card display / overrides
                if (!empty($instrument['id'])) {
                    fengbroFinanceSaveImagesForId(
                        (string) $instrument['id'],
                        $instrument['imageUrls'] ?? []
                    );
                }
            }
        } elseif ($action === 'delete_custom' || $action === 'bulk_delete_custom') {
            $deleteIds = $action === 'bulk_delete_custom'
                ? array_values(array_filter(array_map('trim', (array) ($_POST['instrument_ids'] ?? []))))
                : [trim((string) ($_POST['instrument_id'] ?? ''))];
            $deleteSet = array_flip(array_filter($deleteIds, static fn($id) => $id !== ''));
            $custom = array_values(array_filter(
                $config['custom'],
                static fn($row) => !isset($deleteSet[(string) ($row['id'] ?? '')])
            ));
            fengbroFinanceSaveCustomInstruments($custom);
            if ($deleteSet) {
                $cfg = fengbroFinanceReadConfig();
                foreach (array_keys($deleteSet) as $deleteId) {
                    unset($cfg['imageById'][$deleteId]);
                }
                fengbroFinanceWriteConfig($cfg);
            }
        } elseif ($action === 'set_images') {
            $imgId = trim((string) ($_POST['instrument_id'] ?? ''));
            $imgText = (string) ($_POST['image_urls'] ?? '');
            if ($imgId !== '') {
                fengbroFinanceSaveImagesForId($imgId, $imgText);
            }
        } elseif ($action === 'export_csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="fengbro-finance.csv"');
            echo "\xEF\xBB\xBF";
            // id,name,symbol,provider,group,imageUrls,youtubeUrl,bilibiliUrl,relatedLinks,featured
            echo fengbroFinanceBuildCsv();
            exit;
        } elseif ($action === 'toggle_featured') {
            $fid = trim((string) ($_POST['instrument_id'] ?? ''));
            if ($fid !== '') {
                fengbroFinanceToggleFeatured($fid);
            }
        } elseif ($action === 'import_csv' && !empty($_FILES['finance_csv']['tmp_name'])) {
            $raw = (string) file_get_contents($_FILES['finance_csv']['tmp_name']);
            fengbroFinanceImportCsv($raw);
        }

        $redirectHash = ($action === 'set_images') ? '' : '#finance-instrument-manager';
        header('Location: index.php?page=tools&tool=finance&refresh=1' . $redirectHash);
        exit;
    }
}
