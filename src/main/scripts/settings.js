jQuery(function () {

	let selectFrame;

	jQuery('.add-watermark-image-select').click(function (event) {
		event.preventDefault();
		let base = jQuery(event.target).parents('.add-watermark-image');
		if (!selectFrame) {
			selectFrame = wp.media({
				title: jQuery(this).data('dialog_title'),
				button: { text: jQuery(this).data('uploader_button_text') },
				multiple: false
			});
			selectFrame.on('select', function () {
				let selected = selectFrame.state().get('selection').first();
				base.find('.add-watermark-image-path').text(selected.attributes.name);
				base.find('.add-watermark-image-url').val(selected.attributes.url);
				base.find('.add-watermark-image-id').val(selected.attributes.id);
				base.find('.add-watermark-image-preview').attr('src', selected.attributes.url);

				// update width and height fields
				jQuery('.add-watermark-settings input[name=add-watermark-width]').val(selected.attributes.width + '')
				jQuery('.add-watermark-settings select[name=add-watermark-width-unit]').val('px')
				jQuery('.add-watermark-settings input[name=add-watermark-height]').val(selected.attributes.height + '')
				jQuery('.add-watermark-settings select[name=add-watermark-height-unit]').val('px')

				reloadPreview();
			});
		}
		selectFrame.open();
	});

	function reloadPreview() {
		function val(name) {
			return jQuery('.add-watermark-settings *[name=add-watermark-' + name + ']').val();
		}

		let size = val('size').split('-');
		let hpos = val('horizontal-pos-from') == 'left' ? '0%' : val('horizontal-pos-from') == 'right' ? '100%' : '50%';
		let vpos = val('vertical-pos-from') == 'top' ? '0%' : val('vertical-pos-from') == 'bottom' ? '100%' : '50%';
		let imageUrl = val("image-url");
		let css = {
			"background-size": size[0] == 'full' ? '100% 100%' : size[0],
			"left": val('horizontal-pos') + val('horizontal-pos-unit'),
			"top": val('vertical-pos') + val('vertical-pos-unit'),
		}
		let css_pos2 = {
			"left": '-' + hpos,
			"top": '-' + vpos,
		}
		let css_pos = {
			"left": hpos,
			"top": vpos,
			"min-width": val('width-min') + val('width-min-unit'),
			"max-width": val('width-max') + val('width-max-unit'),
			"width": val('width') + val('width-unit'),
			"min-height": val('height-min') + val('height-min-unit'),
			"max-height": val('height-max') + val('height-max-unit'),
			"height": val('height') + val('height-unit'),
		}
		if (imageUrl) {
			css["background-image"] = 'url("' + imageUrl + '")'
		}
		if (size.length > 1) {
			const align = size[1];
			if (align == 'left') css["background-position"] = "center left";
			if (align == 'top') css["background-position"] = "top center";
			if (align == 'right') css["background-position"] = "center right";
			if (align == 'bottom') css["background-position"] = "bottom center";
		}
		if (size == 'contain') {
			css["background-position"] = hpos + ' ' + vpos;
		}
		jQuery('.add-watermark-preview .watermark').css(css);
		jQuery('.add-watermark-preview .watermark-pos').css(css_pos);
		jQuery('.add-watermark-preview .watermark-pos2').css(css_pos2);
	}
	jQuery('.add-watermark-settings').find('input, select').on('change input', reloadPreview)
	reloadPreview();
});
