<?php

namespace Hizzle\Logger\Handlers;

/**
 * Abstract log handler class.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Abstract log handler class.
 *
 * @version 1.0.0
 */
abstract class Handler implements Handler_Interface {

	/**
	 * Handle a log entry.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message Log message.
	 * @param array  $context {
	 *      Additional information for log handlers.
	 *
	 *     @type string $source Optional. Source will be available in log table.
	 *                  If no source is provided, attempt to provide sensible default.
	 * }
	 *
	 * @see Handler::get_log_source() for default source.
	 *
	 * @return bool False if value was not handled and true if value was handled.
	 */
	public function handle( $timestamp, $level, $message, $context = array() ) {

		if ( isset( $context['source'] ) && $context['source'] ) {
			$source = $context['source'];
		} else {
			$source = $this->get_log_source();
		}

		return $this->add( $timestamp, $level, $message, $source, $context );

	}

	/**
	 * Adds a new log entry.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message Log message.
	 * @param string $source Log source. Useful for filtering and sorting.
	 * @param array  $context Context will be serialized and stored in database.
	 *
	 * @return bool True if write was successful.
	 */
	abstract protected function add( $timestamp, $level, $message, $source, $context );

	/**
	 * Formats a timestamp for use in log messages.
	 *
	 * @param int $timestamp Log timestamp.
	 * @return string Formatted time for use in log entry.
	 */
	protected static function format_time( $timestamp ) {
		return gmdate( 'c', $timestamp );
	}

	/**
	 * Builds a log entry text from level, timestamp and message.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message Log message.
	 * @param array  $context Additional information for log handlers.
	 *
	 * @return string Formatted log entry.
	 */
	protected static function format_entry( $timestamp, $level, $message, $context ) {
		$time_string  = self::format_time( $timestamp );
		$level_string = strtoupper( $level );
		$entry        = "{$time_string} {$level_string} {$message}";
		$extra        = (array) $context;

		// Remove the source from the context.
		if ( isset( $extra['source'] ) ) {
			unset( $extra['source'] );
		}

		// Add the extra information to the entry.
		if ( $extra ) {
			$entry .= "\n\n" . print_r( $extra, true );
		}

		return apply_filters(
			'hizzle_logger_format_log_entry',
			$entry,
			array(
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
				'context'   => $context,
			)
		);
	}

	/**
	 * Get appropriate source based on file name.
	 *
	 * Try to provide an appropriate source in case none is provided.
	 *
	 * @return string Text to use as log source. "" (empty string) if none is found.
	 */
	protected function get_log_source() {

		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ); // @codingStandardsIgnoreLine.
		foreach ( $trace as $t ) {

			// Skip our classes.
			if ( isset( $t['class'] ) && false !== strpos( $t['class'], 'Hizzle\Logger' ) ) {
				continue;
			}

			// Return the class name.
			if ( isset( $t['class'] ) ) {
				return $t['class'];
			}

			// Return the file name.
			if ( isset( $t['file'] ) ) {
				return pathinfo( $t['file'], PATHINFO_FILENAME );
			}

			// Return the function name.
			if ( isset( $t['function'] ) ) {
				return $t['function'];
			}
		}

		return '';
	}

}
