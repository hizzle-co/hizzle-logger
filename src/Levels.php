<?php

namespace Hizzle\Logger;

/**
 * Log levels class.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log levels class.
 *
 * @link https://tools.ietf.org/html/rfc5424
 */
abstract class Levels {

	/**
	 * System is unusable.
	 */
	const EMERGENCY = 'emergency';

	/**
	 * Action must be taken immediately.
	 */
	const ALERT = 'alert';

	/**
	 * Critical conditions.
	 */
	const CRITICAL = 'critical';

	/**
	 * Error conditions.
	 */
	const ERROR = 'error';

	/**
	 * Warning conditions.
	 */
	const WARNING = 'warning';

	/**
	 * Normal but significant condition.
	 */
	const NOTICE = 'notice';

	/**
	 * Informational messages.
	 */
	const INFO = 'info';

	/**
	 * Debug-level messages.
	 */
	const DEBUG = 'debug';

	/**
	 * Level strings mapped to integer severity.
	 *
	 * @var array
	 */
	protected static $level_to_severity = array(
		self::EMERGENCY => 800,
		self::ALERT     => 700,
		self::CRITICAL  => 600,
		self::ERROR     => 500,
		self::WARNING   => 400,
		self::NOTICE    => 300,
		self::INFO      => 200,
		self::DEBUG     => 100,
	);

	/**
	 * Severity integers mapped to level strings.
	 *
	 * This is the inverse of $level_severity.
	 *
	 * @var array
	 */
	protected static $severity_to_level = array(
		800 => self::EMERGENCY,
		700 => self::ALERT,
		600 => self::CRITICAL,
		500 => self::ERROR,
		400 => self::WARNING,
		300 => self::NOTICE,
		200 => self::INFO,
		100 => self::DEBUG,
	);

	/**
	 * Validate a level string.
	 *
	 * @param string $level Log level.
	 * @return bool True if $level is a valid level.
	 */
	public static function is_valid_level( $level ) {
		return array_key_exists( strtolower( $level ), self::$level_to_severity );
	}

	/**
	 * Translate level string to integer.
	 *
	 * @param string $level Log level, options: emergency|alert|critical|error|warning|notice|info|debug.
	 * @return int 100 (debug) - 800 (emergency) or 0 if not recognized
	 */
	public static function get_level_severity( $level ) {
		return self::is_valid_level( $level ) ? self::$level_to_severity[ strtolower( $level ) ] : 0;
	}

	/**
	 * Translate severity integer to level string.
	 *
	 * @param int $severity Severity level.
	 * @return bool|string False if not recognized. Otherwise string representation of level.
	 */
	public static function get_severity_level( $severity ) {
		if ( ! array_key_exists( $severity, self::$severity_to_level ) ) {
			return false;
		}
		return self::$severity_to_level[ $severity ];
	}

	/**
	 * Returns the level lables.
	 *
	 * @return array
	 */
	public static function get_levels() {
		return array(
			self::EMERGENCY => __( 'Emergency', 'hizzle-logger' ),
			self::ALERT     => __( 'Alert', 'hizzle-logger' ),
			self::CRITICAL  => __( 'Critical', 'hizzle-logger' ),
			self::ERROR     => __( 'Error', 'hizzle-logger' ),
			self::WARNING   => __( 'Warning', 'hizzle-logger' ),
			self::NOTICE    => __( 'Notice', 'hizzle-logger' ),
			self::INFO      => __( 'Info', 'hizzle-logger' ),
			self::DEBUG     => __( 'Debug', 'hizzle-logger' ),
		);
	}

}
