<?php

namespace Hizzle\Logger\Handlers;

/**
 * Log handler interface.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log Handler Interface
 *
 * Functions that must be defined to correctly fulfill log handler API.
 *
 * @version 1.0.0
 */
interface Handler_Interface {

	/**
	 * Handle a log entry.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message Log message.
	 * @param array  $context Additional information for log handlers.
	 *
	 * @return bool False if value was not handled and true if value was handled.
	 */
	public function handle( $timestamp, $level, $message, $context = array() );
}
