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

// مدیر سایت، چیدمان فشرده‌تر «اتاق کنترل» می‌گیرد؛ درمانگر همان پوسته
// «میز کار» را می‌بیند. تفاوت فقط در کلاس و تراکم است، نه در مجوز.
$pas_console = PAS_Dashboard_Security::can_manage();
$pas_group   = PAS_Dashboard_Module::group_for( $module->id );
$pas_home    = add_query_arg(
	array(
		'page'   => PAS_DASHBOARD_SLUG,
		'module' => PAS_Dashboard_Permissions::default_module(),
	),
	admin_url( 'admin.php' )
);
?>
<div class="pvn-shell pas-dashboard<?php echo $pas_console ? ' pvn-shell--console' : ''; ?>" dir="rtl">

	<a class="pas-skip-link" href="#pas-main"><?php esc_html_e( 'پرش به محتوا', 'peyvand-amn-dashboard' ); ?></a>

	<div class="pvn-frame">
		<?php include PAS_DASHBOARD_DIR . 'templates/header.php'; ?>

		<div class="pvn-body">
			<?php include PAS_DASHBOARD_DIR . 'templates/sidebar.php'; ?>

			<main class="pvn-main pas-main" id="pas-main" tabindex="-1">
				<div class="pvn-main-inner">
					<div class="pvn-pagehead">
						<div class="pvn-pagehead__text">
							<nav class="pvn-breadcrumb" aria-label="<?php esc_attr_e( 'مسیر صفحه', 'peyvand-amn-dashboard' ); ?>">
								<a href="<?php echo esc_url( $pas_home ); ?>"><?php esc_html_e( 'داشبورد', 'peyvand-amn-dashboard' ); ?></a>
								<?php if ( $pas_group !== '' ) : ?>
									<span aria-hidden="true">/</span>
									<span><?php echo esc_html( $pas_group ); ?></span>
								<?php endif; ?>
								<span aria-hidden="true">/</span>
								<b aria-current="page"><?php echo esc_html( $module->title ); ?></b>
							</nav>

							<h1 class="pvn-pagehead__title"><?php echo esc_html( $module->title ); ?></h1>
							<?php if ( $module->description !== '' ) : ?>
								<p class="pvn-pagehead__sub"><?php echo esc_html( $module->description ); ?></p>
							<?php endif; ?>
						</div>

						<div class="pvn-pagehead__aside">
							<span class="pvn-pagehead__chip">
								<?php echo PAS_Dashboard_Module::svg( $module->id, 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php echo esc_html( $pas_group !== '' ? $pas_group : $module->title ); ?>
							</span>
						</div>
					</div>

					<?php echo PAS_Dashboard_Flash::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

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
