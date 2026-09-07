<?php
/**
 * TTS 引擎：
 * 1) Google Translate TTS（多語、跨平台，預設）
 * 2) Windows SAPI（本機備援，繁中較穩）
 * 可選 Node Edge TTS（tools/edge-tts，連線不穩時自動略過）
 */

require_once __DIR__ . '/media_tools.php';

/** UI lang → Google TTS / Translate code */
function mediaTtsLangMap(): array
{
    return [
        'zh-TW' => ['tts' => 'zh-TW', 'translate' => 'zh-TW', 'label' => '繁體中文'],
        'zh-CN' => ['tts' => 'zh-CN', 'translate' => 'zh-CN', 'label' => '簡體中文'],
        'en-US' => ['tts' => 'en', 'translate' => 'en', 'label' => 'English'],
        'ja-JP' => ['tts' => 'ja', 'translate' => 'ja', 'label' => '日本語'],
        'ko-KR' => ['tts' => 'ko', 'translate' => 'ko', 'label' => '한국어'],
        'yue-HK' => ['tts' => 'yue', 'translate' => 'yue', 'label' => '廣東話'],
    ];
}

function mediaTtsNormalizeLang(string $lang): string
{
    $lang = trim($lang) ?: 'zh-TW';
    $map = mediaTtsLangMap();
    return isset($map[$lang]) ? $lang : 'zh-TW';
}

function mediaTtsGoogleCode(string $lang): string
{
    $lang = mediaTtsNormalizeLang($lang);
    return mediaTtsLangMap()[$lang]['tts'];
}

function mediaTtsTranslateCode(string $lang): string
{
    $lang = mediaTtsNormalizeLang($lang);
    return mediaTtsLangMap()[$lang]['translate'];
}

/**
 * @return list<array{name:string,culture:string,gender:string,age:string}>
 */
function mediaTtsListVoices(): array
{
    $voices = [
        ['name' => 'Google TTS (multi-lang)', 'culture' => 'multi', 'gender' => 'neutral', 'age' => 'Adult'],
    ];
    if (!mediaToolsIsWindows()) {
        return $voices;
    }
    $ps = <<<'PS'
Add-Type -AssemblyName System.Speech
$s = New-Object System.Speech.Synthesis.SpeechSynthesizer
$s.GetInstalledVoices() | ForEach-Object {
  $v = $_.VoiceInfo
  $g = $v.Gender.ToString().ToLower()
  Write-Output ($v.Name + "`t" + $v.Culture.Name + "`t" + $g + "`t" + $v.Age.ToString())
}
PS;
    $out = mediaTtsRunPowerShell($ps, 30);
    foreach (preg_split('/\r\n|\r|\n/', $out['stdout']) as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, "\t")) {
            continue;
        }
        [$name, $culture, $gender, $age] = array_pad(explode("\t", $line, 4), 4, '');
        if ($name === '') {
            continue;
        }
        $voices[] = [
            'name' => $name,
            'culture' => $culture,
            'gender' => $gender ?: 'unknown',
            'age' => $age ?: '',
        ];
    }
    return $voices;
}

/**
 * @return array{ok:bool,stdout:string,stderr:string,exitCode:int}
 */
function mediaTtsRunPowerShell(string $script, int $timeoutSec = 120): array
{
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fengbro_ps_' . bin2hex(random_bytes(4)) . '.ps1';
    // UTF-8 with BOM for PowerShell Chinese
    file_put_contents($tmp, "\xEF\xBB\xBF" . $script);
    $psExe = mediaToolsIsWindows()
        ? (getenv('SystemRoot') ?: 'C:\\Windows') . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe'
        : 'powershell';
    $run = mediaToolsRun([
        $psExe,
        '-NoProfile',
        '-NonInteractive',
        '-ExecutionPolicy', 'Bypass',
        '-File', $tmp,
    ], $timeoutSec);
    @unlink($tmp);
    return [
        'ok' => $run['ok'],
        'stdout' => $run['stdout'],
        'stderr' => $run['stderr'],
        'exitCode' => $run['exitCode'],
    ];
}

function mediaTtsPickVoice(?string $preferGender = 'female', string $preferCulture = 'zh-TW'): ?string
{
    $voices = mediaTtsListVoices();
    if (!$voices) {
        return null;
    }
    $preferGender = strtolower((string) $preferGender);
    $scored = [];
    foreach ($voices as $v) {
        $score = 0;
        $c = strtolower($v['culture']);
        $g = strtolower($v['gender']);
        if ($c === strtolower($preferCulture)) {
            $score += 50;
        } elseif (str_starts_with($c, 'zh')) {
            $score += 30;
        }
        if ($preferGender && $g === $preferGender) {
            $score += 20;
        }
        // prefer Hanhan / Yun for TW
        if (preg_match('/hanhan|yunjhe|hsiao|zhiwei|tingting/i', $v['name'])) {
            $score += 10;
        }
        $scored[] = ['name' => $v['name'], 'score' => $score];
    }
    usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
    return $scored[0]['name'] ?? $voices[0]['name'];
}

/**
 * Fetch URL body with curl/file_get_contents.
 */
function mediaTtsHttpGet(string $url, int $timeout = 20): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept: */*',
                'Referer: https://translate.google.com/',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($body) || $body === '' || $code >= 400) {
            return '';
        }
        return $body;
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'header' => "User-Agent: Mozilla/5.0\r\nReferer: https://translate.google.com/\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : '';
}

/**
 * Split text for Google TTS (~180 chars, prefer punctuation breaks).
 * @return list<string>
 */
function mediaTtsChunkText(string $text, int $max = 180): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return [];
    }
    if (mb_strlen($text, 'UTF-8') <= $max) {
        return [$text];
    }
    $chunks = [];
    $buf = '';
    $len = mb_strlen($text, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($text, $i, 1, 'UTF-8');
        $buf .= $ch;
        $bl = mb_strlen($buf, 'UTF-8');
        $break = preg_match('/[。！？!?,，；;、\s]/u', $ch);
        if ($bl >= $max || ($break && $bl >= (int) ($max * 0.55))) {
            $chunks[] = trim($buf);
            $buf = '';
        }
    }
    if (trim($buf) !== '') {
        $chunks[] = trim($buf);
    }
    return $chunks ?: [$text];
}

/**
 * Google Translate TTS → MP3 file (may concat chunks with ffmpeg).
 */
function mediaTtsGoogleToMp3(string $text, string $outMp3, string $lang = 'zh-TW'): void
{
    $text = trim($text);
    if ($text === '') {
        throw new InvalidArgumentException('語音文字為空');
    }
    $tl = mediaTtsGoogleCode($lang);
    $parts = mediaTtsChunkText($text, 160);
    $work = dirname($outMp3);
    $mp3s = [];
    foreach ($parts as $i => $part) {
        $url = 'https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob'
            . '&tl=' . rawurlencode($tl)
            . '&q=' . rawurlencode($part);
        $bin = mediaTtsHttpGet($url, 25);
        if ($bin === '' || strlen($bin) < 200) {
            // yue fallback
            if ($tl === 'yue') {
                $url = 'https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl=zh-TW&q=' . rawurlencode($part);
                $bin = mediaTtsHttpGet($url, 25);
            }
        }
        if ($bin === '' || strlen($bin) < 200) {
            throw new RuntimeException('Google TTS 失敗：' . mb_substr($part, 0, 40, 'UTF-8'));
        }
        $seg = $work . DIRECTORY_SEPARATOR . 'gtts_' . $i . '_' . bin2hex(random_bytes(2)) . '.mp3';
        file_put_contents($seg, $bin);
        $mp3s[] = $seg;
    }
    if (count($mp3s) === 1) {
        if (!@rename($mp3s[0], $outMp3) && !@copy($mp3s[0], $outMp3)) {
            throw new RuntimeException('無法寫入 TTS 檔案');
        }
        @unlink($mp3s[0]);
        return;
    }
    $tools = mediaToolsResolve();
    if (empty($tools['ffmpeg'])) {
        // join binary mp3 roughly by concat demuxer
        throw new RuntimeException('TOOLS_MISSING: 長句 Google TTS 需要 ffmpeg 合併');
    }
    $list = $work . DIRECTORY_SEPARATOR . 'gtts_list.txt';
    $lines = [];
    foreach ($mp3s as $m) {
        $safe = str_replace('\\', '/', $m);
        $lines[] = "file '" . str_replace("'", "'\\''", $safe) . "'";
    }
    file_put_contents($list, implode("\n", $lines) . "\n");
    $run = mediaToolsRun([
        $tools['ffmpeg'], '-y', '-f', 'concat', '-safe', '0', '-i', $list,
        '-c', 'copy', $outMp3,
    ], 60, $work);
    if (!$run['ok'] || !is_file($outMp3)) {
        $run2 = mediaToolsRun([
            $tools['ffmpeg'], '-y', '-f', 'concat', '-safe', '0', '-i', $list,
            '-c:a', 'libmp3lame', '-q:a', '4', $outMp3,
        ], 60, $work);
        if (!$run2['ok'] || !is_file($outMp3)) {
            throw new RuntimeException('Google TTS 合併失敗');
        }
    }
    foreach ($mp3s as $m) {
        @unlink($m);
    }
    @unlink($list);
}

/**
 * Speak text to a WAV file via SAPI.
 */
function mediaTtsSynthesizeToWav(string $text, string $outWav, ?string $voiceName = null, int $rate = 0, int $volume = 100): void
{
    if (!mediaToolsIsWindows()) {
        throw new RuntimeException('TTS_UNAVAILABLE: 目前僅支援 Windows SAPI 本機語音');
    }
    $text = trim($text);
    if ($text === '') {
        throw new InvalidArgumentException('語音文字為空');
    }
    if (!$voiceName) {
        $voiceName = mediaTtsPickVoice('female', 'zh-TW') ?: '';
    }
    $rate = max(-10, min(10, $rate));
    $volume = max(0, min(100, $volume));
    $outWavEsc = str_replace("'", "''", $outWav);
    $voiceEsc = str_replace("'", "''", $voiceName);
    $textEsc = str_replace("'", "''", $text);

    $ps = "Add-Type -AssemblyName System.Speech\n";
    $ps .= "\$s = New-Object System.Speech.Synthesis.SpeechSynthesizer\n";
    $ps .= "\$s.Rate = $rate\n";
    $ps .= "\$s.Volume = $volume\n";
    if ($voiceName !== '') {
        $ps .= "try { \$s.SelectVoice('$voiceEsc') } catch {}\n";
    }
    $ps .= "\$s.SetOutputToWaveFile('$outWavEsc')\n";
    $ps .= "\$s.Speak('$textEsc')\n";
    $ps .= "\$s.Dispose()\n";

    $run = mediaTtsRunPowerShell($ps, 180);
    if (!$run['ok'] || !is_file($outWav) || filesize($outWav) < 100) {
        throw new RuntimeException('SAPI TTS 合成失敗：' . trim($run['stderr'] . ' ' . $run['stdout']));
    }
}

/**
 * Synthesize one line to audio file (mp3 or wav). Returns engine used.
 */
function mediaTtsSynthesizeLine(string $text, string $outPath, string $lang = 'zh-TW', ?string $gender = 'female', int $rateUi = 0): string
{
    $lang = mediaTtsNormalizeLang($lang);
    $engine = 'google';
    // Prefer Google for multi-lang or non-Windows; SAPI only for zh on Windows as optional
    try {
        $mp3 = preg_match('/\.mp3$/i', $outPath) ? $outPath : ($outPath . '.mp3');
        mediaTtsGoogleToMp3($text, $mp3, $lang);
        if ($mp3 !== $outPath) {
            // convert to wav if requested
            if (preg_match('/\.wav$/i', $outPath)) {
                $tools = mediaToolsResolve();
                if (empty($tools['ffmpeg'])) {
                    @rename($mp3, preg_replace('/\.wav$/i', '.mp3', $outPath) ?: $mp3);
                    return 'google-mp3';
                }
                mediaToolsRun([$tools['ffmpeg'], '-y', '-i', $mp3, '-c:a', 'pcm_s16le', $outPath], 60);
                @unlink($mp3);
            }
        }
        return 'google';
    } catch (Throwable $e) {
        if (mediaToolsIsWindows() && in_array($lang, ['zh-TW', 'zh-CN', 'yue-HK'], true)) {
            $voice = mediaTtsPickVoice($gender === 'male' ? 'male' : 'female', $lang === 'zh-CN' ? 'zh-CN' : 'zh-TW');
            $sapiRate = (int) round(max(-2, min(2, $rateUi)) * 2);
            $wav = preg_match('/\.wav$/i', $outPath) ? $outPath : ($outPath . '.wav');
            mediaTtsSynthesizeToWav($text, $wav, $voice, $sapiRate, 100);
            if ($wav !== $outPath && preg_match('/\.mp3$/i', $outPath)) {
                $tools = mediaToolsResolve();
                if (!empty($tools['ffmpeg'])) {
                    mediaToolsRun([$tools['ffmpeg'], '-y', '-i', $wav, '-c:a', 'libmp3lame', '-q:a', '4', $outPath], 60);
                    @unlink($wav);
                }
            }
            return 'sapi';
        }
        throw $e;
    }
}

/**
 * Google Translate (unofficial client=gtx).
 * @param list<string> $lines
 * @return list<string>
 */
function mediaTtsTranslateLines(array $lines, string $targetLang = 'en-US', string $sourceLang = 'auto'): array
{
    $target = mediaTtsTranslateCode($targetLang);
    $source = $sourceLang === 'auto' ? 'auto' : mediaTtsTranslateCode($sourceLang);
    $out = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            $out[] = '';
            continue;
        }
        if ($source !== 'auto' && $source === $target) {
            $out[] = $line;
            continue;
        }
        $url = 'https://translate.googleapis.com/translate_a/single?client=gtx'
            . '&sl=' . rawurlencode($source)
            . '&tl=' . rawurlencode($target)
            . '&dt=t&q=' . rawurlencode($line);
        $raw = mediaTtsHttpGet($url, 15);
        $translated = $line;
        if ($raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $parts = [];
                foreach ($data[0] as $seg) {
                    if (is_array($seg) && isset($seg[0])) {
                        $parts[] = (string) $seg[0];
                    }
                }
                $joined = trim(implode('', $parts));
                if ($joined !== '') {
                    $translated = $joined;
                }
            }
        }
        // yue fallback
        if ($translated === $line && $target === 'yue') {
            $url2 = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=' . rawurlencode($source)
                . '&tl=zh-TW&dt=t&q=' . rawurlencode($line);
            $raw2 = mediaTtsHttpGet($url2, 15);
            $data2 = json_decode($raw2, true);
            if (is_array($data2) && isset($data2[0]) && is_array($data2[0])) {
                $parts = [];
                foreach ($data2[0] as $seg) {
                    if (is_array($seg) && isset($seg[0])) {
                        $parts[] = (string) $seg[0];
                    }
                }
                $joined = trim(implode('', $parts));
                if ($joined !== '') {
                    $translated = $joined;
                }
            }
        }
        $out[] = $translated;
        usleep(80000);
    }
    return $out;
}

/**
 * @param list<string> $lines
 * @return array{wavPath:string,srtPath:string,workDir:string,durations:list<float>,totalDuration:float,voice:string,engine:string,lines:list<string>}
 */
function mediaTtsBuildScriptAudio(array $lines, ?string $gender = 'female', int $rateUi = 0, int $volume = 100, string $lang = 'zh-TW'): array
{
    $lines = array_values(array_filter(array_map(static fn($l) => trim((string) $l), $lines), static fn($l) => $l !== ''));
    if (!$lines) {
        throw new InvalidArgumentException('請至少輸入一行語音稿');
    }
    if (count($lines) > 60) {
        throw new InvalidArgumentException('一次最多 60 行');
    }

    $workDir = mediaToolsTempDir('fengbro_tts_');
    $lang = mediaTtsNormalizeLang($lang);
    $engineUsed = 'google';
    $voiceLabel = 'Google TTS / ' . $lang;

    $segmentFiles = [];
    $durations = [];
    foreach ($lines as $i => $line) {
        $seg = $workDir . DIRECTORY_SEPARATOR . sprintf('seg_%02d.mp3', $i + 1);
        $engineUsed = mediaTtsSynthesizeLine($line, $seg, $lang, $gender, $rateUi);
        if (!is_file($seg)) {
            // maybe wav
            $segWav = $workDir . DIRECTORY_SEPARATOR . sprintf('seg_%02d.wav', $i + 1);
            if (is_file($segWav)) {
                $seg = $segWav;
            }
        }
        $dur = mediaTtsProbeDuration($seg);
        if ($dur <= 0) {
            $dur = max(1.2, mb_strlen($line, 'UTF-8') / 4.0);
        }
        $durations[] = $dur;
        $segmentFiles[] = $seg;
    }

    $tools = mediaToolsResolve();
    if (empty($tools['ffmpeg'])) {
        throw new RuntimeException('TOOLS_MISSING: 找不到 ffmpeg');
    }
    $listFile = $workDir . DIRECTORY_SEPARATOR . 'audio_list.txt';
    $listLines = [];
    foreach ($segmentFiles as $w) {
        $safe = str_replace('\\', '/', $w);
        $listLines[] = "file '" . str_replace("'", "'\\''", $safe) . "'";
    }
    file_put_contents($listFile, implode("\n", $listLines) . "\n");
    $fullWav = $workDir . DIRECTORY_SEPARATOR . 'full.wav';
    $run = mediaToolsRun([
        $tools['ffmpeg'], '-y',
        '-f', 'concat', '-safe', '0',
        '-i', $listFile,
        '-c:a', 'pcm_s16le',
        $fullWav,
    ], 120, $workDir);
    if (!$run['ok'] || !is_file($fullWav)) {
        throw new RuntimeException('合併語音失敗：' . trim($run['stderr']));
    }

    $srtPath = $workDir . DIRECTORY_SEPARATOR . 'subs.srt';
    mediaTtsWriteSrt($lines, $durations, $srtPath, 0.25);
    $total = array_sum($durations) + max(0, count($durations) - 1) * 0.25;

    return [
        'wavPath' => $fullWav,
        'srtPath' => $srtPath,
        'engine' => $engineUsed,
        'voice' => $voiceLabel . ' (' . $engineUsed . ')',
        'workDir' => $workDir,
        'durations' => $durations,
        'totalDuration' => $total,
        'lines' => $lines,
    ];
}

function mediaTtsProbeDuration(string $mediaPath): float
{
    $tools = mediaToolsResolve();
    $ffprobe = null;
    if (!empty($tools['ffmpeg'])) {
        $dir = dirname($tools['ffmpeg']);
        $cand = $dir . DIRECTORY_SEPARATOR . (mediaToolsIsWindows() ? 'ffprobe.exe' : 'ffprobe');
        if (is_file($cand)) {
            $ffprobe = $cand;
        }
    }
    if (!$ffprobe) {
        $ffprobe = mediaToolsWhich('ffprobe');
    }
    if (!$ffprobe) {
        return 0.0;
    }
    $run = mediaToolsRun([
        $ffprobe, '-v', 'error',
        '-show_entries', 'format=duration',
        '-of', 'default=noprint_wrappers=1:nokey=1',
        $mediaPath,
    ], 30);
    $d = (float) trim($run['stdout']);
    return $d > 0 ? $d : 0.0;
}

/**
 * @param list<string> $lines
 * @param list<float> $durations
 */
function mediaTtsWriteSrt(array $lines, array $durations, string $srtPath, float $gap = 0.2): void
{
    $t = 0.0;
    $blocks = [];
    foreach ($lines as $i => $line) {
        $dur = max(0.4, (float) ($durations[$i] ?? 1.5));
        $start = $t;
        $end = $t + $dur;
        $blocks[] = ($i + 1) . "\n" . mediaTtsSrtTime($start) . ' --> ' . mediaTtsSrtTime($end) . "\n" . $line . "\n";
        $t = $end + $gap;
    }
    file_put_contents($srtPath, implode("\n", $blocks));
}

function mediaTtsSrtTime(float $sec): string
{
    if ($sec < 0) {
        $sec = 0;
    }
    $h = (int) floor($sec / 3600);
    $m = (int) floor(fmod($sec, 3600) / 60);
    $s = (int) floor(fmod($sec, 60));
    $ms = (int) round(($sec - floor($sec)) * 1000);
    if ($ms >= 1000) {
        $ms = 0;
        $s++;
    }
    return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
}

/**
 * Full pipeline: image + script lines → MP4 with embedded audio + burned subtitles.
 *
 * @param list<string> $lines
 * @return array{workDir:string,filePath:string,filename:string,mime:string,size:int,logs:list<string>,voice:string}
 */
function mediaTtsImageScriptToVideo(
    string $imagePath,
    array $lines,
    string $gender = 'female',
    int $rateUi = 0,
    string $orientation = 'auto',
    string $lang = 'zh-TW',
    string $translateTo = ''
): array {
    $tools = mediaToolsResolve();
    if (empty($tools['ffmpeg'])) {
        throw new RuntimeException('TOOLS_MISSING: 找不到 ffmpeg');
    }
    if (!is_file($imagePath)) {
        throw new InvalidArgumentException('缺少封面圖片');
    }

    $lang = mediaTtsNormalizeLang($lang);
    $speakLines = $lines;
    $subLines = $lines;
    if ($translateTo !== '' && mediaTtsNormalizeLang($translateTo) !== $lang) {
        $speakLines = mediaTtsTranslateLines($lines, $translateTo, $lang);
        // subtitles follow spoken language
        $subLines = $speakLines;
        $lang = mediaTtsNormalizeLang($translateTo);
    }

    $audio = mediaTtsBuildScriptAudio($speakLines, $gender, $rateUi, 100, $lang);
    // rewrite srt with final subtitle lines (same durations)
    mediaTtsWriteSrt($subLines, $audio['durations'], $audio['srtPath'], 0.25);
    $workDir = mediaToolsTempDir('fengbro_ivv_full_');
    $imgExt = pathinfo($imagePath, PATHINFO_EXTENSION) ?: 'jpg';
    $img = $workDir . DIRECTORY_SEPARATOR . 'cover.' . preg_replace('/[^a-z0-9]/i', '', $imgExt);
    $wav = $workDir . DIRECTORY_SEPARATOR . 'voice.wav';
    $srt = $workDir . DIRECTORY_SEPARATOR . 'subs.srt';
    copy($imagePath, $img);
    copy($audio['wavPath'], $wav);
    copy($audio['srtPath'], $srt);

    // orientation scale
    $vf = [];
    if ($orientation === 'portrait') {
        $vf[] = 'scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2';
    } elseif ($orientation === 'landscape') {
        $vf[] = 'scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2';
    } else {
        $vf[] = 'scale=trunc(iw/2)*2:trunc(ih/2)*2';
    }
    // burn subtitles — escape path for ffmpeg subtitles filter on Windows
    $srtForFilter = str_replace('\\', '/', $srt);
    $srtForFilter = str_replace(':', '\\:', $srtForFilter);
    $srtForFilter = str_replace("'", "\\'", $srtForFilter);
    $vf[] = "subtitles='" . $srtForFilter . "':force_style='FontName=Microsoft JhengHei,FontSize=22,PrimaryColour=&H00FFFFFF,OutlineColour=&H80000000,BorderStyle=3,Outline=2,Shadow=0,MarginV=48,Alignment=2'";

    $outPath = $workDir . DIRECTORY_SEPARATOR . 'output.mp4';
    $args = [
        $tools['ffmpeg'], '-y',
        '-loop', '1',
        '-i', $img,
        '-i', $wav,
        '-vf', implode(',', $vf),
        '-c:v', 'libx264',
        '-tune', 'stillimage',
        '-c:a', 'aac',
        '-b:a', '192k',
        '-pix_fmt', 'yuv420p',
        '-shortest',
        '-movflags', '+faststart',
        $outPath,
    ];
    $run = mediaToolsRun($args, 300, $workDir);
    $logs = [
        'voice=' . $audio['voice'],
        'lines=' . count($audio['lines']),
        trim($run['stdout'] . "\n" . $run['stderr']),
    ];

    // if subtitles filter fails (font), retry without subtitles
    if (!$run['ok'] || !is_file($outPath)) {
        $argsNoSub = [
            $tools['ffmpeg'], '-y',
            '-loop', '1',
            '-i', $img,
            '-i', $wav,
            '-vf', $vf[0],
            '-c:v', 'libx264',
            '-tune', 'stillimage',
            '-c:a', 'aac',
            '-b:a', '192k',
            '-pix_fmt', 'yuv420p',
            '-shortest',
            '-movflags', '+faststart',
            $outPath,
        ];
        $run2 = mediaToolsRun($argsNoSub, 300, $workDir);
        $logs[] = 'retry-no-subs: ' . trim($run2['stdout'] . "\n" . $run2['stderr']);
        if (!$run2['ok'] || !is_file($outPath)) {
            mediaToolsCleanupDir($workDir);
            mediaToolsCleanupDir($audio['workDir']);
            throw new RuntimeException("圖+語音合成失敗\n" . implode("\n", $logs));
        }
    }

    mediaToolsCleanupDir($audio['workDir']);
    return [
        'workDir' => $workDir,
        'filePath' => $outPath,
        'filename' => 'image-voice-video.mp4',
        'mime' => 'video/mp4',
        'size' => (int) filesize($outPath),
        'logs' => $logs,
        'voice' => $audio['voice'],
    ];
}
