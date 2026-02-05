<?php
/**
 * Pure PHP ZIP creation class - streaming version for large files
 */
class PureZip
{
    private $files = [];
    private $centralDir = [];
    private $offset = 0;

    public function addFile($content, $name)
    {
        $name = str_replace('\\', '/', $name);
        // 確保使用 UTF-8 編碼
        if (function_exists('mb_convert_encoding')) {
            $name = mb_convert_encoding($name, 'UTF-8', 'auto');
        }
        $size = strlen($content);

        // For small files, use compression
        if ($size < 1024 * 1024) { // Less than 1MB
            $crc = crc32($content);
            $zdata = gzcompress($content);
            $zdata = substr($zdata, 2, -4);
            $csize = strlen($zdata);
            $method = 8; // Deflate
        } else {
            // For large files, store without compression
            $crc = crc32($content);
            $zdata = $content;
            $csize = $size;
            $method = 0; // Store
        }

        // Local file header with UTF-8 flag (bit 11 = 0x0800)
        $header = "\x50\x4b\x03\x04";
        $header .= "\x14\x00";
        $header .= "\x00\x08"; // UTF-8 flag
        $header .= pack('v', $method);
        $header .= "\x00\x00\x00\x00";
        $header .= pack('V', $crc);
        $header .= pack('V', $csize);
        $header .= pack('V', $size);
        $header .= pack('v', strlen($name));
        $header .= pack('v', 0);
        $header .= $name;

        $this->files[] = ['header' => $header, 'data' => $zdata];

        // Central directory entry with UTF-8 flag
        $cdr = "\x50\x4b\x01\x02";
        $cdr .= "\x14\x00";
        $cdr .= "\x14\x00";
        $cdr .= "\x00\x08"; // UTF-8 flag
        $cdr .= pack('v', $method);
        $cdr .= "\x00\x00\x00\x00";
        $cdr .= pack('V', $crc);
        $cdr .= pack('V', $csize);
        $cdr .= pack('V', $size);
        $cdr .= pack('v', strlen($name));
        $cdr .= pack('v', 0);
        $cdr .= pack('v', 0);
        $cdr .= pack('v', 0);
        $cdr .= pack('v', 0);
        $cdr .= pack('V', 32);
        $cdr .= pack('V', $this->offset);
        $cdr .= $name;

        $this->centralDir[] = $cdr;
        $this->offset += strlen($header) + $csize;
    }

    public function addFileFromPath($path, $name)
    {
        if (file_exists($path)) {
            $this->addFile(file_get_contents($path), $name);
        }
    }

    public function getZipContent()
    {
        $data = '';
        foreach ($this->files as $file) {
            $data .= $file['header'] . $file['data'];
        }
        $cdr = implode('', $this->centralDir);
        $cdrSize = strlen($cdr);

        $eocd = "\x50\x4b\x05\x06";
        $eocd .= pack('v', 0);
        $eocd .= pack('v', 0);
        $eocd .= pack('v', count($this->files));
        $eocd .= pack('v', count($this->files));
        $eocd .= pack('V', $cdrSize);
        $eocd .= pack('V', $this->offset);
        $eocd .= pack('v', 0);

        return $data . $cdr . $eocd;
    }

    public function download($filename)
    {
        $content = $this->getZipContent();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
    }
}

/**
 * Streaming ZIP class for very large files - writes directly to output
 */
class StreamingZip
{
    private $centralDir = [];
    private $offset = 0;
    private $fileCount = 0;

    public function begin($filename)
    {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        ob_end_flush();
        flush();
    }

    public function addLargeFile($path, $name)
    {
        if (!file_exists($path))
            return;

        $name = str_replace('\\', '/', $name);
        // 確保使用 UTF-8 編碼
        if (function_exists('mb_convert_encoding')) {
            $name = mb_convert_encoding($name, 'UTF-8', 'auto');
        }
        $size = filesize($path);
        $crc = $this->fileCrc32($path);

        // Local file header (no compression for streaming)
        // 使用 UTF-8 標記 (bit 11 = 0x0800)
        $header = "\x50\x4b\x03\x04";
        $header .= "\x14\x00";
        $header .= "\x00\x08"; // General purpose bit flag: bit 11 set for UTF-8
        $header .= "\x00\x00"; // Store method
        $header .= "\x00\x00\x00\x00";
        $header .= pack('V', $crc);
        $header .= pack('V', $size);
        $header .= pack('V', $size);
        $header .= pack('v', strlen($name));
        $header .= pack('v', 0);
        $header .= $name;

        echo $header;
        flush();

        // Stream file content
        $handle = fopen($path, 'rb');
        while (!feof($handle)) {
            echo fread($handle, 1024 * 1024); // 1MB chunks
            flush();
        }
        fclose($handle);

        // Central directory entry
        // 使用 UTF-8 標記 (bit 11 = 0x0800)
        $cdr = "\x50\x4b\x01\x02";
        $cdr .= "\x14\x00";
        $cdr .= "\x14\x00";
        $cdr .= "\x00\x08"; // General purpose bit flag: bit 11 set for UTF-8
        $cdr .= "\x00\x00"; // Store method
        $cdr .= "\x00\x00\x00\x00";
        $cdr .= pack('V', $crc);
        $cdr .= pack('V', $size);
        $cdr .= pack('V', $size);
        $cdr .= pack('v', strlen($name));
        $cdr .= pack('v', 0);
        $cdr .= pack('v', 0);
        $cdr .= pack('v', 0);
        $cdr .= pack('v', 0);
        $cdr .= pack('V', 32);
        $cdr .= pack('V', $this->offset);
        $cdr .= $name;

        $this->centralDir[] = $cdr;
        $this->offset += strlen($header) + $size;
        $this->fileCount++;
    }

    public function finish()
    {
        $cdr = implode('', $this->centralDir);
        echo $cdr;

        $eocd = "\x50\x4b\x05\x06";
        $eocd .= pack('v', 0);
        $eocd .= pack('v', 0);
        $eocd .= pack('v', $this->fileCount);
        $eocd .= pack('v', $this->fileCount);
        $eocd .= pack('V', strlen($cdr));
        $eocd .= pack('V', $this->offset);
        $eocd .= pack('v', 0);

        echo $eocd;
        flush();
    }

    private function fileCrc32($path)
    {
        $hash = hash_file('crc32b', $path);
        return hexdec($hash);
    }
}

/**
 * Pure PHP ZIP extraction class
 */
class PureZipExtract
{
    private $zipData;
    private $files = [];

    public function open($path)
    {
        if (!file_exists($path)) {
            return false;
        }
        $this->zipData = file_get_contents($path);
        return $this->parse();
    }

    private function parse()
    {
        $offset = 0;
        $len = strlen($this->zipData);

        while ($offset < $len - 4) {
            $sig = substr($this->zipData, $offset, 4);

            if ($sig === "\x50\x4b\x03\x04") {
                $header = unpack(
                    'Vsig/vversion/vflag/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vsize/vnamelen/vextralen',
                    substr($this->zipData, $offset, 30)
                );

                $offset += 30;
                $name = substr($this->zipData, $offset, $header['namelen']);
                $offset += $header['namelen'] + $header['extralen'];

                $cdata = substr($this->zipData, $offset, $header['csize']);
                $offset += $header['csize'];

                if ($header['method'] === 8) {
                    $data = @gzinflate($cdata);
                } else {
                    $data = $cdata;
                }

                $this->files[$name] = $data;
            } elseif ($sig === "\x50\x4b\x01\x02" || $sig === "\x50\x4b\x05\x06") {
                break;
            } else {
                $offset++;
            }
        }

        return count($this->files) > 0;
    }

    public function extractTo($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        foreach ($this->files as $name => $content) {
            $filePath = $path . DIRECTORY_SEPARATOR . $name;
            $dir = dirname($filePath);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (substr($name, -1) !== '/') {
                file_put_contents($filePath, $content);
            }
        }

        return true;
    }

    public function getFiles()
    {
        return array_keys($this->files);
    }
}
