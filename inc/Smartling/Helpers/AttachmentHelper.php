<?php

namespace Smartling\Helpers;

use Smartling\Bootstrap;

/**
 * Class AttachmentHelper
 * @package Smartling\Helpers
 */
class AttachmentHelper
{
    const string FILE_NOT_COPIED                = 'Cannot copy source file.';
    const string CANNOT_ACCESS_SOURCE_FILE      = 'Cannot access source file.';
    const string CANNOT_PREPARE_TARGET_PATH     = 'Cannot prepare target path.';
    const string CANNOT_OVERWRITE_EXISTING_FILE = 'Cannot overwrite existing file.';

    public static function checkIfTargetFileExists($originalFile, $targetPath) {
        $targetFileName = pathinfo($originalFile, PATHINFO_BASENAME);
        $targetFile = pathinfo($targetPath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . $targetFileName;
        return file_exists($targetFile) && is_file($targetFile);
    }

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
        $logger = Bootstrap::getLogger();
        $result = [];
        if (!file_exists($originalFile) || !is_file($originalFile) || !is_readable($originalFile)) {
            $result [] = self::CANNOT_ACCESS_SOURCE_FILE;
        }
        if (!self::buildPath($targetPath)) {
            $result [] = self::CANNOT_PREPARE_TARGET_PATH;
        }
        $targetFileName = pathinfo($originalFile, PATHINFO_BASENAME);
        $targetFile = pathinfo($targetPath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . $targetFileName;
        if (true === $overwrite && static::checkIfTargetFileExists($originalFile, $targetPath)) {
            $logger->debug(vsprintf('Trying to remove existing target file :\'%s\'', [$targetFile]));
            if (false === unlink($targetFile)) {
                $logger->debug(vsprintf('Failed removing target file :\'%s\'', [$targetFile]));
                $result [] = self::CANNOT_OVERWRITE_EXISTING_FILE;
            } else {
                $logger->debug(vsprintf('Removed old target file :\'%s\'', [$targetFile]));
            }
        }

        if ([] === $result) {
            if (is_dir($originalFile) || false === copy($originalFile, $targetFile)) {
                $result [] = self::FILE_NOT_COPIED;
            } else {
                $logger->debug(vsprintf('File \'%s\' copied successfully. Size = %d bytes.', [$originalFile,
                                                                                              filesize($targetFile)]));
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
                Bootstrap::getLogger()->debug(vsprintf('Directory \'%s\' doesn\'t exists. Creating...', [$checkPath]));
                $result = mkdir($checkPath, 0755, true);
                Bootstrap::getLogger()->debug(vsprintf('Directory \'%s\' was%s created', [$checkPath,
                                                                                          ($result ? '' : 'n\'t')]));
            }
        }


        return is_dir($pathDir);
    }
}
