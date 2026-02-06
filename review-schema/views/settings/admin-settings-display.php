<div class="wpwrap rtrs-settings">
	<?php
	$settings_url = admin_url( 'admin.php?page=rtrs-settings' );
	$get_help     = admin_url( 'admin.php?page=rtrs-reviews-get-help' );
	?>
	<?php require_once RTRS_PATH . 'views/setting-sections/settings-header.php'; ?>
	<div class="rtrs-settings-form-wrapper">
		<form method="post" action="">
			<div class="rtrs-settings-content">
				<div class="rtrs-settings-sidebar">

					<ul class="rtrs-settings-menu">
						<?php
						foreach ( $this->tabs as $slug => $tabTitle ) :
							$classes = [ 'nav-' . $slug ];
							if ( $this->active_tab === $slug ) {
								$classes[] = 'nav-tab-active';
							}
							$url = add_query_arg(
								[
									'tab' => $slug,
								],
								$settings_url
							);
							?>
							<li class="rtrs-menu-item">
								<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
									<?php echo esc_html( $tabTitle ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>

				</div>

				<div class="rtrs-settings-content-tab-fields">
					<div class="rtrs-settings-content-tab-<?php echo esc_attr( $this->active_tab ); ?>">
						<?php
						if ( ! empty( $this->subtabs ) ) :
							$array_keys = array_keys( $this->subtabs );
							$last_key   = end( $array_keys );
							?>
							<ul class="subsubsub">
								<?php
								foreach ( $this->subtabs as $subtabs_id => $label ) :
									$url        = add_query_arg(
										[
											'page'    => 'rtrs-settings',
											'tab'     => $this->active_tab,
											'section' => sanitize_title( $subtabs_id ),
										],
										admin_url( 'admin.php' )
									);
									$is_current = ( $this->current_section === $subtabs_id ) ? 'current' : '';
									$separator  = ( $subtabs_id === $last_key ) ? '' : '|';
									?>
									<li>
										<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $is_current ); ?>">
											<?php echo esc_html( $label ); ?>
										</a>
										<?php echo esc_html( $separator ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
							<br class="clear" />
						<?php endif; ?>

						<?php
							do_action( 'rtrs_admin_settings_groups', $this->active_tab, $this->current_section );
							wp_nonce_field( 'rtrs-settings' );
						?>
					</div>
					<?php
					if ( 'support' != $this->active_tab ) {
						submit_button();
					}
					?>
				</div>
				<?php require_once RTRS_PATH . 'views/setting-sections/promo-section.php'; ?>
			</div>
		</form>
	</div>
</div>