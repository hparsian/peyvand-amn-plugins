<?php
/**
 * قالب اصلی داشبورد.
 *
 * @var PAS_Dashboard_Module $module
 * @var array                $nav
 * @var int                  $unread
 * @var WP_User              $user
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="pas-dashboard" dir="rtl">

	<a class="pas-skip-link" href="#pas-main"><?php esc_html_e( 'پرش به محتوا', 'peyvand-amn-dashboard' ); ?></a>

	<?php include PAS_DASHBOARD_DIR . 'templates/header.php'; ?>

	<div class="pas-dashboard-body">
		<?php include PAS_DASHBOARD_DIR . 'templates/sidebar.php'; ?>

		<main class="pas-main" id="pas-main">
			<?php
			$content = $module->render();
			// خروجی ماژول‌ها در همان فایل با esc_html/esc_url ساخته شده است.
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</main>
	</div>

	<?php include PAS_DASHBOARD_DIR . 'templates/footer.php'; ?>
</div>
