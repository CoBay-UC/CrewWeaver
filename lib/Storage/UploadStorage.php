<?php

declare(strict_types=1);

namespace CrewWeaver\Storage;

use RuntimeException;

class UploadStorage
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected string $_path;
    protected int $_maximumBytes;
    protected array $_allowedMimeTypes;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(string $path, int $maximumBytes)
    {
        $this->_path = rtrim($path, DIRECTORY_SEPARATOR);
        $this->_maximumBytes = $maximumBytes;
        $this->_allowedMimeTypes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf','text/plain','text/csv','application/zip','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function store(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) { throw new RuntimeException('Upload did not complete successfully.'); }
        $size = (int) $file['size'];
        if ($size <= 0 || $size > $this->_maximumBytes) { throw new RuntimeException('Upload exceeds the permitted size.'); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!is_string($mime) || !in_array($mime, $this->_allowedMimeTypes, true)) { throw new RuntimeException('This file type is not permitted.'); }
        $storageName = bin2hex(random_bytes(32));
        if (!is_dir($this->_path) && !mkdir($this->_path, 0750, true) && !is_dir($this->_path)) { throw new RuntimeException('Upload storage is unavailable.'); }
        if (!move_uploaded_file($file['tmp_name'], $this->_path . DIRECTORY_SEPARATOR . $storageName)) { throw new RuntimeException('Could not store uploaded file.'); }
        return ['storage_name' => $storageName, 'original_name' => basename((string) $file['name']), 'mime_type' => $mime, 'size_bytes' => $size];
    }

    public function path(string $storageName): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $storageName)) { throw new RuntimeException('Invalid storage identifier.'); }
        return $this->_path . DIRECTORY_SEPARATOR . $storageName;
    }

    public function remove(string $storageName): void
    {
        $path = $this->path($storageName);
        if (is_file($path) && !unlink($path)) {
            clearstatcache(true, $path);
            if (is_file($path)) {
                throw new RuntimeException('Could not remove stored attachment.');
            }
        }
    }
}
