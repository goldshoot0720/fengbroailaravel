<?php
// Check what the appwrite-music ZIP actually contains
$zip = new ZipArchive();
$zipPath = 'C:/Users/chbon/Downloads/appwrite-music (1).zip';

echo "Opening: $zipPath\n";
echo "File size: " . number_format(filesize($zipPath)) . " bytes\n";

$result = $zip->open($zipPath);
if ($result !== true) {
    echo "ERROR: ZipArchive::open failed with code: $result\n";
    switch ($result) {
        case ZipArchive::ER_INCONS:
            echo "Inconsistent archive\n";
            break;
        case ZipArchive::ER_INVAL:
            echo "Invalid argument\n";
            break;
        case ZipArchive::ER_MEMORY:
            echo "Memory allocation failure\n";
            break;
        case ZipArchive::ER_NOENT:
            echo "No such file\n";
            break;
        case ZipArchive::ER_NOZIP:
            echo "Not a ZIP file\n";
            break;
        case ZipArchive::ER_OPEN:
            echo "Cannot open file\n";
            break;
        case ZipArchive::ER_READ:
            echo "Read error\n";
            break;
        case ZipArchive::ER_SEEK:
            echo "Seek error\n";
            break;
        default:
            echo "Unknown error code: $result\n";
    }
    exit;
}

echo "SUCCESS! Num files: " . $zip->numFiles . "\n";
echo "Comment: " . $zip->comment . "\n\n";
echo "First 30 entries:\n";
for ($i = 0; $i < min(30, $zip->numFiles); $i++) {
    $stat = $zip->statIndex($i);
    echo "  [$i] " . $stat['name'] . " (size=" . number_format($stat['size']) . ", comp=" . number_format($stat['comp_size']) . ")\n";
}

// Look for CSV
echo "\nLooking for CSV files:\n";
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'csv') {
        echo "  CSV Found: $name\n";
        // Read first 500 chars
        $content = $zip->getFromIndex($i);
        echo "  First 500 chars: " . substr($content, 0, 500) . "\n";
    }
}

$zip->close();
echo "\nDone.\n";
