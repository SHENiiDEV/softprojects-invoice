/**
 * JavaScript for WooCommerce PDF Invoice Batch Generator Admin Page
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// 1. File Input Drag & Drop and Change Display
		const $fileInput = $('#csv_file_input');
		const $dropzone  = $('.wc-pibg-dropzone');
		const $fileName  = $('#file-chosen-name');

		if ($fileInput.length) {
			$fileInput.on('change', function(e) {
				if (this.files && this.files.length > 0) {
					$fileName.text(this.files[0].name);
				} else {
					$fileName.text('');
				}
			});

			$dropzone.on('dragover dragenter', function() {
				$dropzone.addClass('dragover');
			});

			$dropzone.on('dragleave dragend drop', function() {
				$dropzone.removeClass('dragover');
			});
		}

		// 2. Master Checkbox Select All / Deselect All
		const $selectAllHead = $('#cb-select-all');
		const $selectAllFoot = $('#cb-select-all-foot');
		const $rowCheckboxes = $('.row-checkbox');
		const $selectedCount = $('#selected-count');
		const $generateForm  = $('#wc-pibg-generate-form');
		const $modalOverlay  = $('#wc-pibg-modal-overlay');

		function updateSelectedCount() {
			const checkedCount = $rowCheckboxes.filter(':checked').length;
			if ($selectedCount.length) {
				$selectedCount.text(checkedCount);
			}

			const allChecked = checkedCount === $rowCheckboxes.length;
			$selectAllHead.prop('checked', allChecked);
			$selectAllFoot.prop('checked', allChecked);
		}

		if ($selectAllHead.length || $selectAllFoot.length) {
			$selectAllHead.on('change', function() {
				const isChecked = $(this).is(':checked');
				$rowCheckboxes.prop('checked', isChecked);
				$selectAllFoot.prop('checked', isChecked);
				updateSelectedCount();
			});

			$selectAllFoot.on('change', function() {
				const isChecked = $(this).is(':checked');
				$rowCheckboxes.prop('checked', isChecked);
				$selectAllHead.prop('checked', isChecked);
				updateSelectedCount();
			});

			$rowCheckboxes.on('change', function() {
				updateSelectedCount();
			});
		}

		// 3. Form Validation and Progress Modal
		if ($generateForm.length) {
			$generateForm.on('submit', function(e) {
				const checkedCount = $rowCheckboxes.filter(':checked').length;
				if (checkedCount === 0) {
					e.preventDefault();
					const msg = (typeof wcPibgVars !== 'undefined' && wcPibgVars.selectAtLeastOne) 
						? wcPibgVars.selectAtLeastOne 
						: 'Пожалуйста, выберите хотя бы одну запись.';
					alert(msg);
					return false;
				}

				// Show loading overlay
				if ($modalOverlay.length) {
					$modalOverlay.fadeIn(150);

					// Since file download doesn't reload the page, hide overlay after a reasonable delay
					setTimeout(function() {
						$modalOverlay.fadeOut(200);
					}, 5000);
				}
			});
		}
	});
})(jQuery);
