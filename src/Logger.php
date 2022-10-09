<?php

namespace Hizzle\Logger;

/**
 * Main logger class.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main logger class.
 */
class Logger {

	/**
	 * Logger singleton instance.
	 *
	 * @var Logger
	 */
	private static $instance = null;

	/**
	 * Available log handlers.
	 *
	 * @var Handlers\Handler_Interface[]
	 */
	private $handlers = array();

	/**
	 * Minimum log level this logger will process.
	 *
	 * @var int Integer representation of minimum log level to handle.
	 */
	protected $threshold;

	/**
	 * Current module version.
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * Logger constructor.
	 */
	private function __construct() {

		// Set-up the default log handlers.
		$this->handlers = array(
			'email' => new Handlers\Email(),
			'file'  => new Handlers\File(),
			'db'    => new Handlers\DB(),
		);

		// Set the minimum log threshold.
		$minimum_threshold = defined( 'HIZZLE_LOG_THRESHOLD' ) ? constant( 'HIZZLE_LOG_THRESHOLD' ) : 'debug';
		$minimum_threshold = apply_filters( 'hizzle_log_threshold', $minimum_threshold );
		$this->threshold   = Levels::get_level_severity( $minimum_threshold );

		// Install.
		$this->maybe_install();

		// Auto-clear hooks.
		add_action( 'hizzle_log_clear_logs', array( $this, 'clear_expired_logs' ) );

		// Allow users to view logs.
		add_action( 'admin_menu', array( $this, 'add_menu' ) );

		// Fire action after loading.
		do_action( 'hizzle_logger_loaded' );

		// Log fatal errors.
		register_shutdown_function( array( $this, 'log_fatal_error' ) );
	}

	/**
	 * Get the logger singleton instance.
	 *
	 * @return Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add a new log handler.
	 *
	 * @param string                     $key Handler key.
	 * @param Handlers\Handler_Interface $handler Log handler.
	 *
	 * @return Logger
	 */
	public function add_handler( $key, $handler ) {

		if ( ! isset( $this->handlers[ $key ] ) ) {
			$this->handlers[ $key ] = $handler;
		}

		return $this;
	}

	/**
	 * Get a log handler.
	 *
	 * @param string $key Handler key.
	 *
	 * @return Handlers\Handler_Interface|null
	 */
	public function get_handler( $key ) {

		if ( isset( $this->handlers[ $key ] ) ) {
			return $this->handlers[ $key ];
		}

		return null;
	}

	/**
	 * Get all log handlers.
	 *
	 * @return Handlers\Handler_Interface[]
	 */
	public function get_handlers() {
		return $this->handlers;
	}

	/**
	 * Determine whether to handle or ignore log.
	 *
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 * @return bool True if the log should be handled.
	 */
	protected function should_handle( $level ) {
		if ( null === $this->threshold ) {
			return true;
		}
		return $this->threshold <= Levels::get_level_severity( $level );
	}

	/**
	 * Add a log entry.
	 *
	 * @param string $level One of the following:
	 *     'emergency': System is unusable.
	 *     'alert': Action must be taken immediately.
	 *     'critical': Critical conditions.
	 *     'error': Error conditions.
	 *     'warning': Warning conditions.
	 *     'notice': Normal but significant condition.
	 *     'info': Informational messages.
	 *     'debug': Debug-level messages.
	 * @param string $message Log message.
	 * @param string|array  $context Optional. Additional information for log handlers. A string is assumed to be the message source.
	 */
	public function log( $level, $message, $context = array() ) {

		if ( ! Levels::is_valid_level( $level ) ) {
			/* translators: 1: Logger::log 2: level */
			_doing_it_wrong( __METHOD__, sprintf( esc_html__( '%1$s was called with an invalid level "%2$s".', 'hizzle-logger' ), '<code>Logger::log</code>', esc_html( $level ) ), '1.0.0' );
		}

		if ( is_string( $context ) ) {
			$context = array( 'source' => $context );
		}

		if ( $this->should_handle( $level ) ) {
			$timestamp = time();

			foreach ( $this->handlers as $handler ) {
				/**
				 * Filter the logging message. Returning null will prevent logging from occurring.
				 *
				 * @since 1.0.0
				 * @param string $message Log message.
				 * @param string $level   One of: emergency, alert, critical, error, warning, notice, info, or debug.
				 * @param array  $context Additional information for log handlers.
				 * @param object $handler The handler object.
				 */
				$message = apply_filters( 'hizzle_logger_log_message', $message, $level, $context, $handler );

				if ( null !== $message ) {
					$handler->handle( $timestamp, $level, $message, $context );
				}
			}
		}
	}

	/**
	 * Adds an emergency level message.
	 *
	 * System is unusable.
	 *
	 * @see Logger::log
	 *
	 * @param string $message Message to log.
	 * @param array  $context Log context.
	 */
	public function emergency( $message, $context = array() ) {
		$this->log( Levels::EMERGENCY, $message, $context );
	}

	/**
	 * Adds an alert level message.
	 *
	 * Action must be taken immediately.
	 * Example: Entire website down, database unavailable, etc.
	 *
	 * @see Logger::log
	 *
	 * @param string $message Message to log.
	 * @param array  $context Log context.
	 */
	public function alert( $message, $context = array() ) {
		$this->log( Levels::ALERT, $message, $context );
	}

	/**
	 * Adds a critical level message.
	 *
	 * Critical conditions.
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @see Logger::log
	 *
	 * @param string $message Message to log.
	 * @param array  $context Log context.
	 */
	public function critical( $message, $context = array() ) {
		$this->log( Levels::CRITICAL, $message, $context );
	}

	/**
	 * Adds an error level message.
	 *
	 * Runtime errors that do not require immediate action but should typically be logged
	 * and monitored.
	 *
	 * @see Logger::log
	 *
	 * @param string $message Message to log.
	 * @param array  $context Log context.
	 */
	public function error( $message, $context = array() ) {
		$this->log( Levels::ERROR, $message, $context );
	}

	/**
	 * Adds a warning level message.
	 *
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things that are not
	 * necessarily wrong.
	 *
	 * @see Logger::log
	 *
	 * @param string $message Message to log.
	 * @param array  $context Log context.
	 */
	public function warning( $message, $context = array() ) {
		$this->log( Levels::WARNING, $message, $context );
	}

	/**
	 * Adds a notice level message.
	 *
	 * Normal but significant events.
	 *
	 * @see Logger::log
	 *
	 * @param string $message Message to log.
	 * @param array  $context Log context.
	 */
	public function notice( $message, $context = array() ) {
		$this->log( Levels::NOTICE, $message, $context );
	}

	/**
	 * Adds a info level message.
	 *
	 * Interesting events.
	 * Example: User logs in, SQL logs.
	 *
	 * @see Logger::log
	 *
	 * @param string $message Message to log.
	 * @param array  $context Log context.
	 */
	public function info( $message, $context = array() ) {
		$this->log( Levels::INFO, $message, $context );
	}

	/**
	 * Adds a debug level message.
	 *
	 * Detailed debug information.
	 *
	 * @see Levels::log
	 *
	 * @param string $message Message to log.
	 * @param array  $context Log context.
	 */
	public function debug( $message, $context = array() ) {
		$this->log( Levels::DEBUG, $message, $context );
	}

	/**
	 * Clear entries for a chosen file/source.
	 *
	 * @param string $source Source/handle to clear.
	 * @return bool
	 */
	public function clear( $source = '' ) {
		if ( ! $source ) {
			return false;
		}
		foreach ( $this->handlers as $handler ) {
			if ( is_callable( array( $handler, 'clear' ) ) ) {
				call_user_func( array( $handler, 'clear' ), $source );
			}
		}
		return true;
	}

	/**
	 * Clear all logs older than a defined number of days. Defaults to 30 days.
	 *
	 * @since 1.0.0
	 */
	public function clear_expired_logs() {
		$days      = absint( apply_filters( 'hizzle_logger_days_to_retain_logs', 30 ) );
		$timestamp = strtotime( "-{$days} days" );

		foreach ( $this->handlers as $handler ) {
			if ( is_callable( array( $handler, 'delete_logs_before_timestamp' ) ) ) {
				call_user_func( array( $handler, 'delete_logs_before_timestamp' ), $timestamp );
			}
		}
	}

	/**
	 * Installs the logger.
	 *
	 */
	public function maybe_install() {

		if ( get_option( 'hizzle_logger_version' ) === $this->version ) {
			return;
		}

		// Clear expired logs.
		wp_clear_scheduled_hook( 'hizzle_log_clear_logs' );
		wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), 'daily', 'hizzle_log_clear_logs' );

		// Create the log directory if it doesn't exist.
		$this->create_files();

		// Create db table if it doesn't exist.
		$this->create_db_tables();

		// Update the version.
		update_option( 'hizzle_logger_version', $this->version );
	}

	/**
	 * Create files/directories.
	 */
	private static function create_files() {

		// Bypass if filesystem is read-only and/or non-standard upload system is used.
		if ( apply_filters( 'hizzle_logger_install_skip_create_files', false ) ) {
			return;
		}

		// Install files and folders for uploading files and prevent hotlinking.
		$files = array(
			array(
				'base'    => Handlers\File::$log_dir,
				'file'    => '.htaccess',
				'content' => 'deny from all',
			),
			array(
				'base'    => Handlers\File::$log_dir,
				'file'    => 'index.html',
				'content' => '',
			),
		);

		foreach ( $files as $file ) {
			if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
				$file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen
				if ( $file_handle ) {
					fwrite( $file_handle, $file['content'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
					fclose( $file_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
				}
			}
		}

	}

	/**
	 * Create database tables.
	 */
	private static function create_db_tables() {
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$sql = "CREATE TABLE {$wpdb->prefix}hizzle_log (
			log_id bigint UNSIGNED NOT NULL AUTO_INCREMENT,
			timestamp datetime NOT NULL,
			level smallint(4) NOT NULL,
			source varchar(200) NOT NULL,
			message longtext NOT NULL,
			context longtext NULL,
			PRIMARY KEY (log_id),
			KEY level (level)
		) $collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Ensures fatal errors are logged so they can be picked up in the status report.
	 *
	 * @since 1.0.0
	 */
	public function log_fatal_error() {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			$this->critical(
				/* translators: 1: error message 2: file name and path 3: line number */
				sprintf( __( '%1$s in %2$s on line %3$s', 'hizzle-logger' ), $error['message'], $error['file'], $error['line'] ) . PHP_EOL,
				array(
					'source' => 'fatal-errors',
				)
			);
		}
	}

	/**
     * Add a menu item to the tools section.
     */
    public function add_menu() {

		if ( apply_filters( 'hizzle_logger_admin_show_menu', false ) ) {
			add_submenu_page(
				'tools.php',
				__( 'Debug Log', 'hizzle-logger' ),
				__( 'Debug Log', 'hizzle-logger' ),
				'manage_options',
				'hizzle-logger',
				array( $this, 'render_admin_page' )
			);
		}
    }

	/**
     * Render the admin page.
     */
    public function render_admin_page() {
		// Display the list of available log messages.
		require_once plugin_dir_path( __FILE__ ) . 'html-log-list.php';
    }
}
