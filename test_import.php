<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/functions.php';
require_once 'includes/PureZip.php';

echo "<h2>Import ZIP Test</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $zipFile = $_FILES['file']['tmp_name'];
    echo "<p>File uploaded: " . $_FILES['file']['name'] . "</p>";
    echo "<p>Temp path: " . $zipFile . "</p>";
    echo "<p>File size: " . filesize($zipFile) . " bytes</p>";

    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_' . uniqid();
    mkdir($tempDir);
    echo "<p>Temp dir: " . $tempDir . "</p>";

    $zip = new PureZipExtract();
    if ($zip->open($zipFile)) {
        echo "<p style='color:green;'>ZIP opened successfully</p>";
        echo "<p>Files in ZIP:</p><ul>";
        foreach ($zip->getFiles() as $f) {
            echo "<li>" . htmlspecialchars($f) . "</li>";
        }
        echo "</ul>";

        $zip->extractTo($tempDir);
        echo "<p>Extracted to: $tempDir</p>";

        echo "<p>Files in temp dir:</p><ul>";
        foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*') as $f) {
            echo "<li>" . htmlspecialchars(basename($f)) . " - " . (is_file($f) ? filesize($f) . " bytes" : "DIR") . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red;'>Failed to open ZIP</p>";
    }

    // Cleanup
    $cleanup = function($dir) use (&$cleanup) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
            is_dir($file) ? $cleanup($file) : unlink($file);
        }
        @rmdir($dir);
    };
    $cleanup($tempDir);
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="file" accept=".zip">
    <button type="submit">Test Upload</button>
</form>
