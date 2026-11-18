<?php
/**
 * قالب اصلی داشبورد — پوستهٔ ۲.
 *
 * @var PAS_Dashboard_Module $module
 * @var array                $nav
 * @var int                  $unread
 * @var WP_User              $user
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="pvn-shell pas-dashboard" dir="rtl">

	<a class="pas-skip-link" href="#pas-main"><?php esc_html_e( 'پرش به محتوا', 'peyvand-amn-dashboard' ); ?></a>

	<div class="pvn-frame">
		<?php include PAS_DASHBOARD_DIR . 'templates/header.php'; ?>

		<div class="pvn-body">
			<?php include PAS_DASHBOARD_DIR . 'templates/sidebar.php'; ?>

			<main class="pvn-main pas-main" id="pas-main" tabindex="-1">
				<div class="pvn-main-inner">
					<div class="pvn-pagehead">
						<div>
							<h1 class="pvn-pagehead__title"><?php echo esc_html( $module->title ); ?></h1>
							<?php if ( $module->description !== '' ) : ?>
								<p class="pvn-pagehead__sub"><?php echo esc_html( $module->description ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<?php
					$content = $module->render();
					// خروجی ماژول‌ها در همان فایل با esc_html/esc_url ساخته شده است.
					echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			</main>
		</div>

		<?php include PAS_DASHBOARD_DIR . 'templates/footer.php'; ?>
	</div>

	<div class="pvn-scrim" data-pvn-close-nav aria-hidden="true"></div>
</div>
