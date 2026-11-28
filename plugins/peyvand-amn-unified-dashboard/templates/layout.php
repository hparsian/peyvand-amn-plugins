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

$pas_console = PAS_Dashboard_Security::can_manage();
$pas_group   = PAS_Dashboard_Module::group_for( $module->id );
$pas_home    = add_query_arg(
	array(
		'page'   => PAS_DASHBOARD_SLUG,
		'module' => PAS_Dashboard_Permissions::default_module(),
	),
	admin_url( 'admin.php' )
);

$pas_new_case_url = add_query_arg(
	array(
		'page'   => PAS_DASHBOARD_SLUG,
		'module' => 'case_files',
		'new'    => '1',
	),
	admin_url( 'admin.php' )
);

$pas_cases_url = add_query_arg(
	array(
		'page'   => PAS_DASHBOARD_SLUG,
		'module' => 'case_files',
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
							<?php if ( in_array( $module->id, array( 'overview', 'case_files' ), true ) && PAS_Dashboard_Permissions::can( 'case_files' ) ) : ?>
								<a class="pas-btn pas-btn-primary" href="<?php echo esc_url( $pas_new_case_url ); ?>"><?php esc_html_e( 'پروندهٔ جدید', 'peyvand-amn-dashboard' ); ?></a>
							<?php endif; ?>
							<?php if ( $module->id !== 'case_files' && PAS_Dashboard_Permissions::can( 'case_files' ) ) : ?>
								<a class="pas-btn" href="<?php echo esc_url( $pas_cases_url ); ?>"><?php esc_html_e( 'پرونده‌های بالینی', 'peyvand-amn-dashboard' ); ?></a>
							<?php endif; ?>
						</div>
					</div>

					<?php echo PAS_Dashboard_Flash::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					<?php
					$content = $module->render();
					echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			</main>
		</div>

		<?php include PAS_DASHBOARD_DIR . 'templates/footer.php'; ?>
	</div>

	<div class="pvn-scrim" data-pvn-close-nav aria-hidden="true"></div>
</div>
