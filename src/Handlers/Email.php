<?php

namespace Hizzle\Logger\Handlers;

/**
 * Sends log entries to an email address.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles log entries by sending an email.
 *
 * WARNING!
 * This log handler has known limitations.
 *
 * Log messages are aggregated and sent once per request (if necessary). If the site experiences a
 * problem, the log email may never be sent. This handler should be used with another handler which
 * stores logs in order to prevent loss.
 *
 * It is not recommended to use this handler on a high traffic site. There will be a maximum of 1
 * email sent per request per handler, but that could still be a dangerous amount of emails under
 * heavy traffic. Do not confuse this handler with an appropriate monitoring solution!
 *
 * If you understand these limitations, feel free to use this handler or borrow parts of the design
 * to implement your own!
 *
 * @version 1.0.0
 */
class Email extends Handler {

	/**
	 * Minimum log level this handler will process.
	 *
	 * @var int Integer representation of minimum log level to handle.
	 */
	protected $threshold;

	/**
	 * Stores email recipients.
	 *
	 * @var array
	 */
	protected $recipients = array();

	/**
	 * Stores log messages.
	 *
	 * @var array
	 */
	protected $logs = array();

	/**
	 * Stores integer representation of maximum logged level.
	 *
	 * @var int
	 */
	protected $max_severity = null;

	/**
	 * Constructor for log handler.
	 *
	 * @param string|array $recipients Optional. Email(s) to receive log messages. Defaults to site admin email.
	 * @param string       $threshold Optional. Minimum level that should receive log messages.
	 *           Default 'alert'. One of: emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public function __construct( $recipients = null, $threshold = 'alert' ) {
		if ( null === $recipients ) {
			$recipients = get_option( 'admin_email' );
		}

		if ( is_array( $recipients ) ) {
			foreach ( $recipients as $recipient ) {
				$this->add_email( $recipient );
			}
		} else {
			$this->add_email( $recipients );
		}

		$this->set_threshold( $threshold );
		add_action( 'shutdown', array( $this, 'send_log_email' ) );
	}

	/**
	 * Adds an email to the list of recipients.
	 *
	 * @param string $email Email address to add.
	 */
	public function add_email( $email ) {
		array_push( $this->recipients, $email );
	}

	/**
	 * Set handler severity threshold.
	 *
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public function set_threshold( $level ) {
		$this->threshold = \Hizzle\Logger\Levels::get_level_severity( $level );
	}

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

		if ( $this->should_handle( $level ) ) {
			$this->logs[] = $this->format_entry( $timestamp, $level, $message, $context );

			$log_severity = \Hizzle\Logger\Levels::get_level_severity( $level );
			if ( $this->max_severity < $log_severity ) {
				$this->max_severity = $log_severity;
			}
			return true;
		}

		return false;
	}

	/**
	 * Determine whether handler should handle log.
	 *
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 * @return bool True if the log should be handled.
	 */
	protected function should_handle( $level ) {
		return $this->threshold <= \Hizzle\Logger\Levels::get_level_severity( $level );
	}

	/**
	 * Send log email.
	 *
	 * @return bool True if email is successfully sent otherwise false.
	 */
	public function send_log_email() {
		$result = false;

		if ( ! empty( $this->logs ) ) {
			$subject = $this->get_subject();
			$body    = $this->get_body();
			$result  = wp_mail( $this->recipients, $subject, $body );
			$this->clear_logs();
		}

		return $result;
	}

	/**
	 * Build subject for log email.
	 *
	 * @return string subject
	 */
	protected function get_subject() {
		$site_name = get_bloginfo( 'name' );
		$max_level = strtoupper( \Hizzle\Logger\Levels::get_severity_level( $this->max_severity ) );
		$log_count = count( $this->logs );

		return sprintf(
			/* translators: 1: Site name 2: Maximum level 3: Log count */
			_n(
				'[%1$s] %2$s: %3$s log message',
				'[%1$s] %2$s: %3$s log messages',
				$log_count,
				'hizzle-logger'
			),
			$site_name,
			$max_level,
			$log_count
		);
	}

	/**
	 * Build body for log email.
	 *
	 * @return string body
	 */
	protected function get_body() {
		$site_name = get_bloginfo( 'name' );
		$entries   = implode( PHP_EOL, $this->logs );
		$log_count = count( $this->logs );
		return _n(
			'You have received the following log message:',
			'You have received the following log messages:',
			$log_count,
			'hizzle-logger'
		) . PHP_EOL
			. PHP_EOL
			. $entries
			. PHP_EOL
			. PHP_EOL
			/* translators: %s: Site name */
			. sprintf( __( 'Visit %s admin area:', 'hizzle-logger' ), $site_name )
			. PHP_EOL
			. admin_url();
	}

	/**
	 * Clear log messages.
	 */
	protected function clear_logs() {
		$this->logs = array();
	}

}
