<?php
/**
 * پابرگ داشبورد.
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="pas-footer">
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: site name */
				__( 'پیشخوان یکپارچه‌ی %s — داده‌ها فقط از APIهای تأییدشده خوانده می‌شوند.', 'peyvand-amn-dashboard' ),
				get_bloginfo( 'name' )
			)
		);
		?>
	</p>
</footer>
