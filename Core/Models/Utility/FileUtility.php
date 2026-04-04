<?php
namespace Core\Models\Utility;
use \RuntimeException;
class FileUtility{
    public static  function fileVer($filePath): int {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File does not exist: {$filePath}");
        }
        $mtime = filemtime($filePath);
        if ($mtime === false) {
            throw new RuntimeException("Unable to retrieve the last modified time of the file: {$filePath}");
        }
        return $mtime;

    }
}
