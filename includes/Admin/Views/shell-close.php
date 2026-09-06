<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
		</main><!-- #vlt-app-main -->
	</div><!-- .vlt-app-content -->

	<div class="vlt-toast-host" id="vlt-toast-host" aria-live="polite" aria-relevant="additions"></div>

	<div class="vlt-modal" id="vlt-confirm-modal" hidden aria-hidden="true">
		<div class="vlt-modal-backdrop" data-vlt-modal-dismiss></div>
		<div class="vlt-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vlt-confirm-title" tabindex="-1">
			<h2 class="vlt-modal-title" id="vlt-confirm-title"></h2>
			<p class="vlt-modal-body" id="vlt-confirm-body"></p>
			<div class="vlt-modal-actions">
				<button type="button" class="vlt-btn vlt-btn--ghost" data-vlt-modal-dismiss>
					<?php esc_html_e( 'Cancel', 'video-lead-tracker' ); ?>
				</button>
				<button type="button" class="vlt-btn vlt-btn--danger" id="vlt-confirm-ok">
					<?php esc_html_e( 'Confirm', 'video-lead-tracker' ); ?>
				</button>
			</div>
		</div>
	</div>

</div><!-- .vlt-app -->
