<?php declare(strict_types=1);

namespace FileDownloader;

class UsageException extends \LogicException {
	public static function noSourceFile()
	{
		return new self('Cannot create file download without source file!');
	}


	public static function sourceFileNotReadable()
	{
		return new self('Source file is not readable.');
	}
};
abstract class RuntimeException extends \RuntimeException {};

/** @deprecated */
final class FileDownloaderException extends \Exception
{
}

/**
 * When downloader cannot be used for some reason.
 */
final class DownloaderNotSupported extends RuntimeException {}

class FilesystemException extends RuntimeException {
	public static function realpathPermissions()
	{
		return new self ('Cannot perform realpath, check your filesystem permissions.');
	}
}

	final class CannotSeekToPosition extends FilesystemException {}

class CouldNotProcessRequest extends RuntimeException {

}

	class MultipartRequestNotSupported extends CouldNotProcessRequest {

	}

	class RangeNotSatifiableException extends CouldNotProcessRequest {

	}
