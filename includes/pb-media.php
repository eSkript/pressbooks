<?php
/**
 * @author  Pressbooks <code@pressbooks.com>
 * @license GPLv2 (or any later version)
 */
namespace PressBooks\Media;

/**
 * Filter to alter the list of acceptable file extensions
 * @see \PressBooks\Export\Epub3
 *
 * @param array $existing_mimes
 *
 * @return array
 */
function addMimeTypes( $existing_mimes = array() ) {

	$add_mimes = array(
		'mp4' => 'video/mp4',
		'webm' => 'video/webm',
		'ogv' => 'video/ogg',
		'ogg' => 'audio/ogg',
		'mp3' => 'audio/mpeg',
		'aac' => 'audio/x-aac',
		'vorbis' => 'audio/vorbis',
	);

	return array_merge( $add_mimes, $existing_mimes );
}

/**
 * Checks for file validity on import.
 *
 * @param string $pathToFile
 * @param string $filename
 *
 * @return boolean
 */
function is_valid_media( $pathToFile, $filename ) {

	$mimes = addMimeTypes();

	$validate = wp_check_filetype( $filename, $mimes );

	// check the file extension
	if ( ! array_key_exists( $validate['ext'], $mimes ) ) {
		return false;
	}

	// check the mimetype
	if ( ! in_array( $validate['type'], $mimes ) ) {
		return false;
	}

	return true;
}

/**
 * @param string $content
 *
 * @return string
 */
function force_wrap_images( $content ) {

	$pattern = [
		'#<p[^>]*>\s*?(<img class=\"([a-z0-9\- ]*).*?>)?\s*</p>#',
		'#<p[^>]*>\s*?(<a .*?><img class=\"([a-z0-9\- ]*).*?></a>)?\s*</p>#',
	];
	$replacement = '<div class="wp-nocaption $2">$1</div>';

	return preg_replace( $pattern, $replacement, $content );
}
