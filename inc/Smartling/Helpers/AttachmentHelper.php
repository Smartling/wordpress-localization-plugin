<?php

namespace Smartling\Helpers;

/**
 * Class AttachmentHelper
 * @package Smartling\Helpers
 */
class AttachmentHelper
{
    const FILE_NOT_COPIED                = 'Cannot copy source file.';
    const CANNOT_ACCESS_SOURCE_FILE      = 'Cannot access source file.';
    const CANNOT_PREPARE_TARGET_PATH     = 'Cannot prepare target path.';
    const CANNOT_OVERWRITE_EXISTING_FILE = 'Cannot overwrite existing file.';

    /**
     * Creates file clone on filesystem
     *
     * @param string $originalFile
     * @param string $targetPath
     * @param bool   $overwrite
     *
     * @return array
     */
    public static function cloneFile($originalFile, $targetPath, $overwrite = false)
    {
        $result = [];
        if (!file_exists($originalFile) || !is_file($originalFile) || !is_readable($originalFile)) {
            $result [] = self::CANNOT_ACCESS_SOURCE_FILE;
        }
        if (!self::buildPath($targetPath)) {
            $result [] = self::CANNOT_PREPARE_TARGET_PATH;
        }
        $targetFileName = pathinfo($originalFile, PATHINFO_BASENAME);
        $targetFile = pathinfo($targetPath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . $targetFileName;
        if (true === $overwrite && is_file($targetFile)) {
            if (false === unlink($targetFile)) {
                $result [] = self::CANNOT_OVERWRITE_EXISTING_FILE;
            }
        }
        if ([] === $result) {
            if (is_dir($originalFile) || false === copy($originalFile, $targetFile)) {
                $result [] = self::FILE_NOT_COPIED;
            }
        }

        return $result;
    }

    /**
     * @param $path
     *
     * @return bool on success
     */
    private static function buildPath($path)
    {
        $pathDir = pathinfo($path, PATHINFO_DIRNAME);
        $pathParts = explode(DIRECTORY_SEPARATOR, $pathDir);
        $checkPath = '';

        foreach ($pathParts as $part) {
            if (StringHelper::isNullOrEmpty($part)) {
                continue;
            }

            $checkPath .= DIRECTORY_SEPARATOR . $part;

            if (!is_dir($checkPath)) {

                mkdir($checkPath);
            }
        }


        return is_dir($pathDir);
    }
}