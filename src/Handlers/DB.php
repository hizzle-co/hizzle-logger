<?php

namespace Hizzle\Logger\Handlers;

/**
 * Handles log entries by writing to the database.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles log entries by writing to the database.
 *
 * @version 1.0.0
 */
class DB extends Handler {

	/**
	 * Adds a new log entry.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message Log message.
	 * @param string $source Log source. Useful for filtering and sorting.
	 * @param array  $context Context will be json encode and stored in database.
	 *
	 * @return bool True if write was successful.
	 */
	protected function add( $timestamp, $level, $message, $source, $context ) {
		global $wpdb;

		$insert = array(
			'timestamp' => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'level'     => \Hizzle\Logger\Levels::get_level_severity( $level ),
			'message'   => $message,
			'source'    => $source,
			'context'   => is_scalar( $context ) ? (string) $context : (string) wp_json_encode( $context ),
		);

		$format = array(
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
		);

		return false !== $wpdb->insert( "{$wpdb->prefix}hizzle_log", $insert, $format );
	}

	/**
	 * Clear all logs from the DB.
	 *
	 * @return bool True if flush was successful.
	 */
	public static function flush() {
		global $wpdb;

		return $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}hizzle_log" );
	}

	/**
	 * Clear entries for a chosen handle/source.
	 *
	 * @param string $source Log source.
	 * @return bool
	 */
	public static function clear( $source ) {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}hizzle_log WHERE source = %s",
				$source
			)
		);
	}

	/**
	 * Delete selected logs from DB.
	 *
	 * @param int|string|array $log_ids Log ID or array of Log IDs to be deleted.
	 *
	 * @return bool
	 */
	public static function delete( $log_ids ) {
		global $wpdb;

		$log_ids  = wp_parse_id_list( $log_ids );
		$format   = array_fill( 0, count( $log_ids ), '%d' );
		$query_in = '(' . implode( ',', $format ) . ')';
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}hizzle_log WHERE log_id IN {$query_in}", $log_ids ) ); // @codingStandardsIgnoreLine.
	}

	/**
	 * Delete all logs older than a defined timestamp.
	 *
	 * @since 1.0.0
	 * @param integer $timestamp Timestamp to delete logs before.
	 */
	public static function delete_logs_before_timestamp( $timestamp = 0 ) {
		if ( ! $timestamp ) {
			return;
		}

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}hizzle_log WHERE timestamp < %s",
				gmdate( 'Y-m-d H:i:s', $timestamp )
			)
		);
	}

}
