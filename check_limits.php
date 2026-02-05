<?php
echo "Loaded php.ini: " . php_ini_loaded_file() . "<br>";
echo "Additional ini files: " . php_ini_scanned_files() . "<br><br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
