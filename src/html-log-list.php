<?php

	/**
	 * Admin View: Debug logs.
	 *
	 */

	defined( 'ABSPATH' ) || exit;

	$logs_table = new \Hizzle\Logger\List_Table();
?>

<div class="wrap hizzle-logger-page" id="hizzle-logger-wrapper">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Debug Log', 'hizzle-logger' ); ?></h1>

	<form id="hizzle-logger-table" method="POST">
		<?php $logs_table->search_box( __( 'Search', 'hizzle-logger' ), 'search' ); ?>
		<?php $logs_table->display(); ?>
	</form>
</div>
