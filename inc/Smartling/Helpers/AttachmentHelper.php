<?php

namespace Smartling\Helpers;

use Smartling\Bootstrap;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class AttachmentHelper
 *
 * @package Smartling\Helpers
 */
class AttachmentHelper {

	/**
	 * Is returned on success
	 */
	const CODE_SUCCESS = 0;

	/**
	 * Is returned if error occurred while copying
	 */
	const CODE_FILE_NOT_COPIED = 1;

	/**
	 * Is returned if cannot read source file
	 */
	const CODE_CANNOT_ACCESS_SOURCE_FILE = 2;

	/**
	 * Is returned if cannot prepare target path
	 */
	const CODE_CANNOT_PREPARE_TARGET_PATH = 4;

	/**
	 * Is returned if cannot remove existing file before copying
	 */
	const CODE_CANNOT_OVERWRITE_EXISTING_FILE = 8;

	/**
	 * @param $path
	 *
	 * @return bool on success
	 */
	private static function buildPath ( $path ) {
		$pathDir   = pathinfo( $path, PATHINFO_DIRNAME );
		$pathParts = explode( DIRECTORY_SEPARATOR, $pathDir );
		$checkPath = '';

		foreach ($pathParts as $part)
		{
			if (StringHelper::isNullOrEmpty($part))
			{
				continue;
			}

			$checkPath.=DIRECTORY_SEPARATOR.$part;

			if ( ! is_dir( $checkPath ) ) {

				mkdir( $checkPath );
			}
		}




		return is_dir( $pathDir );
	}

	/**
	 * Creates file clone on filesystem
	 *
	 * @param string $originalFile
	 * @param string $targetPath
	 * @param bool   $overwrite
	 *
	 * @return int
	 */
	public static function cloneFile ( $originalFile, $targetPath, $overwrite = false ) {
		$result = self::CODE_SUCCESS;
		if ( ! is_readable( $originalFile ) ) {
			$result |= self::CODE_CANNOT_ACCESS_SOURCE_FILE;
		}
		if ( ! self::buildPath( $targetPath ) ) {
			$result |= self::CODE_CANNOT_PREPARE_TARGET_PATH;
		}
		$targetFileName = pathinfo( $originalFile, PATHINFO_BASENAME );
		$targetFile     = pathinfo( $targetPath, PATHINFO_DIRNAME ) . DIRECTORY_SEPARATOR . $targetFileName;
		if ( is_file( $targetFile ) && true === $overwrite ) {
			if ( false === unlink( $targetFile ) ) {
				$result |= self::CODE_CANNOT_OVERWRITE_EXISTING_FILE;
			}
		}
		if ( self::CODE_SUCCESS === $result ) {
			if ( false === copy( $originalFile, $targetFile ) ) {
				$result |= self::CODE_FILE_NOT_COPIED;
			}
		}

		return $result;
	}

	public static function fixAttachment(SubmissionEntity $submission)
	{

	}
}