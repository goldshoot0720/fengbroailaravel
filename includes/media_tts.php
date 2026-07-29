<?php
/**
 * 本機 TTS：Windows System.Speech (SAPI)，產出 WAV 供 ffmpeg 合成。
 * 無 Python / edge-tts 時的可攜方案。
 */

require_once __DIR__ . '/media_tools.php';

/**
 * @return list<array{name:string,culture:string,gender:string,age:string}>
 */
function mediaTtsListVoices(): array
{
    if (!mediaToolsIsWindows()) {
        return [];
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
    $voices = [];
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
 * Speak text to a WAV file via SAPI.
 *
 * @param float $rate -10..10 SAPI rate (we map UI -2..2 → -4..4)
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
    // Escape text for PowerShell single-quoted string
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
        throw new RuntimeException('TTS 合成失敗：' . trim($run['stderr'] . ' ' . $run['stdout']));
    }
}

/**
 * @param list<string> $lines
 * @return array{wavPath:string,srtPath:string,workDir:string,durations:list<float>,totalDuration:float,voice:string}
 */
function mediaTtsBuildScriptAudio(array $lines, ?string $gender = 'female', int $rateUi = 0, int $volume = 100): array
{
    $lines = array_values(array_filter(array_map(static fn($l) => trim((string) $l), $lines), static fn($l) => $l !== ''));
    if (!$lines) {
        throw new InvalidArgumentException('請至少輸入一行語音稿');
    }
    if (count($lines) > 60) {
        throw new InvalidArgumentException('一次最多 60 行');
    }

    $workDir = mediaToolsTempDir('fengbro_tts_');
    $voice = mediaTtsPickVoice($gender === 'male' ? 'male' : 'female', 'zh-TW') ?: '';
    // UI -2..2 → SAPI roughly -4..4
    $sapiRate = (int) round(max(-2, min(2, $rateUi)) * 2);

    $segmentWavs = [];
    $durations = [];
    foreach ($lines as $i => $line) {
        $wav = $workDir . DIRECTORY_SEPARATOR . sprintf('seg_%02d.wav', $i + 1);
        mediaTtsSynthesizeToWav($line, $wav, $voice, $sapiRate, $volume);
        $dur = mediaTtsProbeDuration($wav);
        if ($dur <= 0) {
            // fallback estimate ~4 chars/sec for CJK
            $dur = max(1.2, mb_strlen($line, 'UTF-8') / 4.0);
        }
        $durations[] = $dur;
        $segmentWavs[] = $wav;
    }

    // concat wavs with ffmpeg
    $tools = mediaToolsResolve();
    if (empty($tools['ffmpeg'])) {
        throw new RuntimeException('TOOLS_MISSING: 找不到 ffmpeg');
    }
    $listFile = $workDir . DIRECTORY_SEPARATOR . 'audio_list.txt';
    $listLines = [];
    foreach ($segmentWavs as $w) {
        $safe = str_replace('\\', '/', $w);
        $listLines[] = "file '" . str_replace("'", "'\\''", $safe) . "'";
    }
    file_put_contents($listFile, implode("\n", $listLines) . "\n");
    $fullWav = $workDir . DIRECTORY_SEPARATOR . 'full.wav';
    $run = mediaToolsRun([
        $tools['ffmpeg'], '-y',
        '-f', 'concat', '-safe', '0',
        '-i', $listFile,
        '-c', 'copy',
        $fullWav,
    ], 120, $workDir);
    if (!$run['ok'] || !is_file($fullWav)) {
        // fallback re-encode concat
        $run2 = mediaToolsRun([
            $tools['ffmpeg'], '-y',
            '-f', 'concat', '-safe', '0',
            '-i', $listFile,
            '-c:a', 'pcm_s16le',
            $fullWav,
        ], 120, $workDir);
        if (!$run2['ok'] || !is_file($fullWav)) {
            throw new RuntimeException('合併語音失敗：' . trim($run['stderr'] . "\n" . $run2['stderr']));
        }
    }

    // pause gap already none; add 0.25s silence between? optional skip
    $srtPath = $workDir . DIRECTORY_SEPARATOR . 'subs.srt';
    mediaTtsWriteSrt($lines, $durations, $srtPath, 0.25);
    $total = array_sum($durations) + max(0, count($durations) - 1) * 0.25;

    return [
        'wavPath' => $fullWav,
        'srtPath' => $srtPath,
        'workDir' => $workDir,
        'durations' => $durations,
        'totalDuration' => $total,
        'voice' => $voice,
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
    string $orientation = 'auto'
): array {
    $tools = mediaToolsResolve();
    if (empty($tools['ffmpeg'])) {
        throw new RuntimeException('TOOLS_MISSING: 找不到 ffmpeg');
    }
    if (!is_file($imagePath)) {
        throw new InvalidArgumentException('缺少封面圖片');
    }

    $audio = mediaTtsBuildScriptAudio($lines, $gender, $rateUi, 100);
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
