<?php
// Creates a small test ZIP with music.csv for testing import_zip_music.php
$zip = new ZipArchive();
$zipPath = __DIR__ . '/test_music_import.zip';
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $csv = '$id,name,file,filetype,category,language' . "\n";
    $csv .= '"test-uuid-001","Test Song Alpha","","mp3","其他","中文"' . "\n";
    $csv .= '"test-uuid-002","Test Song Beta","","mp3","流行","中文"' . "\n";
    $zip->addFromString('music.csv', $csv);
    $zip->close();
    echo "Created: " . $zipPath . " (" . filesize($zipPath) . " bytes)\n";
} else {
    echo "Failed to create ZIP\n";
}
