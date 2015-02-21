<?php
/*
	Plugin Name: Add watermark
	Plugin URI: http://wordpress.org/extend/plugins/
	Description: Adds watermarks to selected images without changing the original image.
	Author: Michael Zangl
	Version: 1.0
	Author URI: http://wordpress.org/extend/plugins/
	Text Domain: add-watermark
	Domain Path: /lang
 */

function add_watermark_option($name) {
	$defaults = array(
		'default-active' => '0',
		'horizontal-pos' => '5',
		'horizontal-pos-unit' => 'px',
		'horizontal-pos-from' => 'left',
		'vertical-pos' => '-5',
		'vertical-pos-unit' => 'px',
		'vertical-pos-from' => 'bottom',
		'size' => 'contain',
		'image' => '',
		'width' => '200',
		'width-unit' => 'px',
		'width-max' => '80',
		'width-max-unit' => '%',
		'width-min' => '10',
		'width-min-unit' => '%',
		'height' => '100',
		'height-unit' => 'px',
		'height-max' => '80',
		'height-max-unit' => '%',
		'height-min' => '10',
		'height-min-unit' => '%',
	);

	if (!isset($defaults[$name])) {
		die("Unknown option: $name");
	}

	return get_option("add-watermark-$name", $defaults[$name]);
}

class AddWatermarksSettings {
	
	static function register() {
		$settings = new AddWatermarksSettings();
		add_action( 'admin_init', array($settings, 'loadPositionSettings') );

		$optionsPage = new AddWatermarkOptionsPage();
		add_action( 'admin_menu', array($optionsPage, 'registerMenu'));
		add_action( 'admin_enqueue_scripts', array($optionsPage, 'scripts') );

		add_filter('init', array($settings, 'htaccess'));

		add_filter('load-settings_page_add-watermark-menu', array($settings, 'onSettingsLoad'));

		add_filter('manage_media_columns', array($settings, 'addWatermarkColumn'));
		add_filter('manage_media_custom_column', array($settings, 'outputWatermarkColumn'));
		add_filter('admin_footer-upload.php', array($settings, 'outputAdminFooter'));
		add_action( 'load-upload.php', array($settings, 'doBulkAction'));
	}

	function addWatermarkColumn($columns) {
		$columns['add-watermark'] = "Wasserzeichen";
		return $columns;
	}

	function watermarkYes($post_id) {
		update_metadata('post', $post_id, 'add-watermark', 'yes');
	}

	function watermarkNo($post_id) {
		update_metadata('post', $post_id, 'add-watermark', 'no');
	}

	function watermarkUnset($post_id) {
		delete_metadata('post', $post_id, 'add-watermark');
	}

	function doBulkAction() {
		$wp_list_table = _get_list_table('WP_Media_List_Table');
		$doaction = $wp_list_table->current_action();

		if ( isset( $_REQUEST['media'] ) ) {
			$post_ids = $_REQUEST['media'];
		} elseif ( isset( $_REQUEST['ids'] ) ) {
			$post_ids = explode( ',', $_REQUEST['ids'] );
		}

		$location = 'upload.php';
		if ( $referer = wp_get_referer() ) {
			if ( false !== strpos( $referer, 'upload.php' ) )
				$location = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'message', 'ids', 'posted' ), $referer );
		}
		
		if ($doaction == 'add_watermark_yes') {
			$action = array($this, 'watermarkYes');
		} else if ($doaction == 'add_watermark_no') {
			$action = array($this, 'watermarkNo');
		} else if ($doaction == 'add_watermark_unset') {
			$action = array($this, 'watermarkUnset');
		}

		if ( isset($action)) {
			if ( !isset( $post_ids ) )
				return;
			check_admin_referer('bulk-media');

			array_map($action, $post_ids);

			wp_redirect( $location );
		}
		return;
	}

	function outputAdminFooter() {
?>
<script type="text/javascript">
jQuery(function() {
	jQuery('<option value="add_watermark_yes">').text("<?php echo html_entity_decode('Wasserzeichen einfügen') ?>").appendTo("select[name=\'action\']");
	jQuery('<option value="add_watermark_yes">').text("<?php echo html_entity_decode('Wasserzeichen einfügen') ?>").appendTo("select[name=\'action2\']");
	jQuery('<option value="add_watermark_no">').text("<?php echo html_entity_decode('Wasserzeichen entfernen') ?>").appendTo("select[name=\'action\']");
	jQuery('<option value="add_watermark_no">').text("<?php echo html_entity_decode('Wasserzeichen entfernen') ?>").appendTo("select[name=\'action2\']");
	jQuery('<option value="add_watermark_unset">').text("<?php echo html_entity_decode('Wasserzeichen auf Standard setzen') ?>").appendTo("select[name=\'action\']");
	jQuery('<option value="add_watermark_unset">').text("<?php echo html_entity_decode('Wasserzeichen auf Standard setzen') ?>").appendTo("select[name=\'action2\']");
});
</script>
<?php
	}

	function outputWatermarkColumn($column) {
		if ($column == 'add-watermark') {
			$meta = get_metadata('post', get_the_ID(), 'add-watermark', true);
			$actions = array(
				'yes' => "Schützen",
				'no' => "Nicht Schützen",
				'unset' => "Standard"
			);
			if ($meta == 'yes') {
				$current = "Schützen";
				unset($actions['yes']);
			} else if ($meta == 'no') {
				$current = "Nicht schützen";
				unset($actions['no']);
			} else {
				$current = "Standardeinstellung";
				unset($actions['unset']);
			}
			echo '<div>' . esc_html($current) . '</div>';
			echo '<div class="row-actions">';
			// TODO: echo implode(' | ', array_map(array($this, 'generateActionLink'), array_keys($actions), array_values($actions)));
			echo '</div>';
		}
	}

	function generateActionLink($action, $text) {
		return sprintf('<a href="upload.php?action=add_watermark_%s&ids=%d">%s</a>', $action, get_the_ID(), $text);
	}

	function htaccess() {
		add_rewrite_rule('wp-content/uploads/(.*?\\.(png|jpe?g))', 'wp-admin/admin-ajax.php?action=watermark_image&path=$1');
	}

	function onSettingsLoad() {
		if(isset($_GET['settings-updated']) && $_GET['settings-updated']) {
			flush_rewrite_rules();
			AddWatermarkRequest::emptyCache();
		}
		wp_enqueue_media();
		wp_enqueue_script( 'add-watermark-js', plugin_dir_url( __FILE__ ) . 'js/settings.js');
	}

	function loadPositionSettings() {
		register_setting( 'add-watermark-settings', 'add-watermark-default-active' );
		register_setting( 'add-watermark-settings', 'add-watermark-image' );
		register_setting( 'add-watermark-settings', 'add-watermark-size' );
		$this->registerUnitSelect('add-watermark-horizontal-pos');
		register_setting( 'add-watermark-settings', 'add-watermark-horizontal-pos-from' );
		$this->registerUnitSelect('add-watermark-vertical-pos');
		register_setting( 'add-watermark-settings', 'add-watermark-vertical-pos-from' );
		$this->registerMinMaxSize('add-watermark-width');
		$this->registerMinMaxSize('add-watermark-height');

		add_settings_section( 'add-watermark-default-active', 'General settings', null, 'add-watermark-settings');
		add_settings_field( 'add-watermark-default-active', 'Aktivierungsmodus falls nicht angegeben', array($this, 'outputDefaultSelect'), 'add-watermark-settings', 'add-watermark-default-active');
		add_settings_section( 'add-watermark-image', 'Watermark image', null, 'add-watermark-settings');
		add_settings_field( 'add-watermark-image', 'Bild', array($this, 'outputImageSelect'), 'add-watermark-settings', 'add-watermark-image');
		add_settings_section( 'add-watermark-position', 'Watermark position', array($this, 'addPositionDescription'), 'add-watermark-settings');
		add_settings_field( 'add-watermark-size', 'Zuschneiden', array($this, 'addSizeSelect'), 'add-watermark-settings', 'add-watermark-position');
		add_settings_field( 'add-watermark-horizontal-pos', 'Horizontale Position', array($this, 'outputHorizontalPos'), 'add-watermark-settings', 'add-watermark-position');
		add_settings_field( 'add-watermark-width', 'Breite', array($this, 'outputWidth'), 'add-watermark-settings', 'add-watermark-position');
		add_settings_field( 'add-watermark-vertical-pos', 'Vertikale Position', array($this, 'outputVerticalPos'), 'add-watermark-settings', 'add-watermark-position');
		add_settings_field( 'add-watermark-height', 'Höhe', array($this, 'outputHeight'), 'add-watermark-settings', 'add-watermark-position');
	}


	function addPositionDescription() {
?>
<div class="add-watermark-preview" style="background: white;width: 400px; height: 300px; position: relative; overflow: hidden; resize: both">
<div class="watermark-pos"><div class="watermark-pos2"><div class="watermark" style="background #ccc"></div></div></div></div>
<?php
	}

	function outputDefaultSelect() {
		$setting = add_watermark_option("default-active") * 1;
?>
<select name="add-watermark-default-active">
<option value="1"<?php if ($setting) echo ' selected="selected"'; ?>>Wasserzeichen anzeigen</option>
<option value="0"<?php if (!$setting) echo ' selected="selected"'; ?>>Kein Wasserzeichen anzeigen</option>
</select>

<?php
	}

	function addSizeSelect() {
		$setting = add_watermark_option("size", 'contain');
?>
<select name="add-watermark-size">
<!--<option value="cover-bottom"<?php if ($setting == 'cover-bottom') echo ' selected="selected"'; ?>>Cover the area, clamp (bottom)</option>
<option value="cover-left"<?php if ($setting == 'cover-left') echo ' selected="selected"'; ?>>Cover the area, clamp (left)</option>
<option value="cover-top"<?php if ($setting == 'cover-top') echo ' selected="selected"'; ?>>Cover the area, clamp (top)</option>
<option value="cover-right"<?php if ($setting == 'cover-right') echo ' selected="selected"'; ?>>Cover the area, clamp (right)</option>-->
<option value="contain"<?php if ($setting == 'contain') echo ' selected="selected"'; ?>>Shrink more, keep aspect</option>
<option value="full"<?php if ($setting == 'auto') echo ' selected="selected"'; ?>>stretch</option>
</select>
<?php
}

	function outputImageSelect() {
		$setting = add_watermark_option("image", "") * 1;
?>
<div class="add-watermark-image">
<input class="add-watermark-image-id" type="hidden" name="add-watermark-image" value="<?php echo esc_attr($setting) ?>"/>
<input class="add-watermark-image-url" type="hidden" name="add-watermark-image-url" value="<?php echo esc_attr(wp_get_attachment_url($setting)) ?>"/>
<img class="add-watermark-image-preview" style="width: auto; height: auto; max-width: 300px; max-height: 200px;" src="<?php echo esc_attr(wp_get_attachment_url($setting)) ?>"/>
<div class="add-watermark-image-path"></div>
<div><a class="add-watermark-image-select" href="#">Bild wählen</a></div>
</div>

<?php
	}

	function outputHorizontalPos() {
		$setting = add_watermark_option("horizontal-pos-from");
?>
Align to
<select name="add-watermark-horizontal-pos-from">
<option value="left"<?php if ($setting == 'left') echo ' selected="selected"'; ?>>Left</option>
<option value="center"<?php if ($setting == 'center') echo ' selected="selected"'; ?>>Center</option>
<option value="right"<?php if ($setting == 'right') echo ' selected="selected"'; ?>>Right</option>
</select>
then move
<?php $this->outputUnitSelect('horizontal-pos'); ?>
to the right.
<?php
	}

	function outputWidth() {
		$this->outputMinMaxSize('width');
	}

	function outputVerticalPos() {
		$setting = add_watermark_option("vertical-pos-from");
?>
Align to
<select name="add-watermark-vertical-pos-from">
<option value="top"<?php if ($setting == 'top') echo ' selected="selected"'; ?>>Top</option>
<option value="center"<?php if ($setting == 'center') echo ' selected="selected"'; ?>>Center</option>
<option value="bottom"<?php if ($setting == 'bottom') echo ' selected="selected"'; ?>>Bottom</option>
</select>
then move
<?php $this->outputUnitSelect('vertical-pos'); ?>
to the bottom.
<?php
	}

	function outputHeight() {
		$this->outputMinMaxSize('height');
	}

	function registerUnitSelect($name) {
		register_setting( 'add-watermark-settings', $name );
		register_setting( 'add-watermark-settings', "$name-unit" );
	}

	function registerMinMaxSize($name) {
		$this->registerUnitSelect($name);
		$this->registerUnitSelect("$name-min");
		$this->registerUnitSelect("$name-max");
	}

	function outputMinMaxSize($name) {
		echo '<span style="width: 5em; display: inline-block;"></span>';
		$this->outputUnitSelect("$name");
		echo '<br/><span style="width: 5em; display: inline-block;">Min:</span>';
		$this->outputUnitSelect("$name-min");
		echo '<br/><span style="width: 5em; display: inline-block;">Max:</span>';
		$this->outputUnitSelect("$name-max");
	}

	function outputUnitSelect($name) {
		$setting = add_watermark_option("$name");
		$unit = add_watermark_option("$name-unit");
?>
<input type="text" name="add-watermark-<?php echo $name ?>" value="<?php echo esc_attr($setting) ?>"/>
<select name="add-watermark-<?php echo $name ?>-unit">
<option value="px"<?php if ($unit != '%') echo ' selected="selected"'; ?>>Pixel</option>
<option value="%"<?php if ($unit == '%') echo ' selected="selected"'; ?>>%</option>
</select>

<?php
	}
}

class AddWatermarkOptionsPage {
	function registerMenu() {
		$page = add_options_page( 'Add watermark', 'Add watermark', 'manage_options', 'add-watermark-menu', array($this, 'generateOptions') );
	}

	function generateOptions() {
?>
<div class="wrap add-watermark-settings">
<h2>Add watermark settings</h2>
<form method="post" action="options.php"> 

<?php settings_fields( 'add-watermark-settings' ); 
do_settings_sections( 'add-watermark-settings' );
?>

<?php submit_button('Submit and empty Cache'); ?>
</form>
</div>
<?php
	}

	function scripts() {
	}
}

class AddWatermarkMarker {
	function __construct($imagepath) {
		$this->type = wp_check_filetype($imagepath);
		$this->image = $this->createImage($imagepath);
		if (!$this->image) {
			throw new RuntimeException("Could not load base image.");
		}
	}

	function createImage($imagepath) {
		$type = wp_check_filetype($imagepath);
		switch ($type['type']) {
		case 'image/jpeg':
			return @imagecreatefromjpeg($imagepath);
			break;
		case 'image/png':
			return @imagecreatefrompng($imagepath);
			break;
		default:
			throw new RuntimeException("Could not convert type {$type['type']}.");
		}
	}

	function addWatermark() {
		$watermarkId = add_watermark_option("image");
		if (!$watermarkId) {
			throw new RuntimeException("No watermark selected.");
		}

		$watermark = $this->createImage(get_attached_file($watermarkId));
		if (!$watermark) {
			throw new RuntimeException("Could not load watermark image.");
		}
		
		$width = 1.0 * $this->getUnitValueClamped("width", imagesx($this->image), 100);
		$height = 1.0 * $this->getUnitValueClamped("height", imagesy($this->image), 100);

		$setting = add_watermark_option("size");
		if ($setting == 'contain') {
			$originalAspect = 1.0 * imagesx($watermark) / imagesy($watermark);
			$currentAspect = $width / $height;
			if ($currentAspect < $originalAspect) {
				// Make currentAspect bigger by decreasing height
				$height = $width / $originalAspect;
			} else if ($currentAspect < $originalAspect) {
				// Make currentAspect smaller by decreasing width
				$width = $height * $originalAspect;
			}
		}

		switch (add_watermark_option("horizontal-pos-from")) {
			case 'right':
				$horizontal = 1.0;
				break;
			case 'center':
				$horizontal = 0.5;
				break;
			default:
				$horizontal = 0;
				break;
		}
		switch (add_watermark_option("vertical-pos-from")) {
			case 'bottom':
				$vertical = 1.0;
				break;
			case 'center':
				$vertical = 0.5;
				break;
			default:
				$vertical = 0;
				break;
		}
		$x = (imagesx($this->image) - $width) * $horizontal;
		$y = (imagesy($this->image) - $height) * $vertical;

		$x += $this->getUnitOption("horizontal-pos", imagesx($this->image), 0);
		$y += $this->getUnitOption("vertical-pos", imagesy($this->image), 0);

		imagecopyresized($this->image, $watermark, (int) $x, (int) $y, 0, 0, (int) $width, (int) $height, imagesx($watermark), imagesy($watermark));
	}

	/**
 	 * Get a pixel or percent value.
	 */
	function getUnitOption($name, $relativeTo, $default=10) {
		$value = add_watermark_option("$name") * 1;
		$unit = add_watermark_option("$name-unit");
		if ($unit == '%') {
			$value = $value / 100.0 * $relativeTo;
		}
		return $value;
	}

	// Respects min/max flags.
	function getUnitValueClamped($name, $relativeTo, $default = 50) {
		$min = $this->getUnitOption("$name-min", $relativeTo, 0);
		$max = $this->getUnitOption("$name-max", $relativeTo, $relativeTo);
		$default = $this->getUnitOption("$name", $relativeTo, $default);
		if ($min > $default) {
			return $min;
		} else if ($max < $default) {
			return $max;
		} else {
			return $default;
		}
	}

	function sendFile($filename = null) {
		header("Content-Type: {$this->type['type']}");
		switch ($this->type['type']) {
		case 'image/jpeg':
			imagejpeg($this->image, $filename);
			break;
		case 'image/png':
			imagepng($this->image, $filename);
			break;
		}
	}

}
class AddWatermarkRequest {
	static function runAjax() {
		$m = new AddWatermarkRequest();
		$m->searchAttachments();
		if ($m->shouldAddWatermark()) {
			$m->addWatermarkCached();
		} else {
			$m->outputOriginal();
		}
	}

	function getPaths() {
		$request = $_REQUEST['path'];
		$upload_dir = wp_upload_dir();

		if (preg_match('=^(.*/)((?<name>[^/]+?)(-(?<x>\d+)x(?<y>\d+))?(?<ext>.\w+))$=', $request, $matches) === false) {
			$this->error404();
		}

		$paths = array();
		// thumb: The file we want. Attachment: The wordpress DB entry.
		$paths['thumbfile'] = $matches[2];
		$paths['attachmentfile'] = $matches['name'] . $matches['ext'];
		$paths['thumbupload'] = $matches[1] . $paths['thumbfile'];
		$paths['attachmentupload'] = $matches[1] . $paths['attachmentfile'];

		// Get the absolute path
		$paths['thumbabs'] = realpath($upload_dir['basedir'] . "/" . $paths['thumbupload']);

		if (!$paths['thumbabs']) {
			$this->error404();
		}

		// We don't really need this since we do a DB query first.
		//$path = realpath($upload_dir['basedir'] . "/" . $request);
		//$realUpload = realpath($upload_dir['basedir']) . "/";
		//if (strpos($path, $realUpload) !== 0) {
			// Someone tried to trick us.
		//	$this->error404();
		//}
		// guid = $upload_dir['baseurl'] . substr($path, strlen($realUpload));
		//$path = substr($path, strlen($realUpload));
		return $paths;
	}

	function searchAttachments() {
		$this->paths = $this->getPaths();
		
		$myquery = new WP_Query(array(
			'post_type' => 'attachment',
			'post_status' => 'any',
			'meta_key' => '_wp_attached_file',
			'meta_value' => $this->paths['attachmentupload']));

		$attachments = $myquery->get_posts();
		
		if (count($attachments) != 1) {
			$this->error404();
		}
		$this->attachment = $attachments[0];

		// handle the size
		if ($this->paths['attachmentfile'] != $this->paths['thumbfile']) {
			$meta = wp_get_attachment_metadata($this->attachment->ID);
			foreach ($meta['sizes'] as $name => $size) {
				if ($size['file'] == $this->paths['thumbfile']) {
					$this->size = $name;
				}
			}
			if (!$this->size) {
				$this->error404();
			}
		}
	}

	function shouldAddWatermark() {
		$meta = get_metadata('post', $this->attachment->ID, 'add-watermark', true);
		if ($meta == 'yes') {
			return true;
		} else if ($meta == 'no') {
			return false;
		} else {
			return add_watermark_option("default-active", "1") * 1;
		}
	}

	function addWatermark() {
		try {
			$wm = new AddWatermarkMarker($this->paths['thumbabs']);
			$wm->addWatermark();
			$wm->sendFile();
			return $wm;
		} catch (Exception $e) {
			$this->error404();
		}
	}

	function outputOriginal($path = null) {
		if ($path == null) {
			$path = $this->paths['thumbabs'];
		}
		$type = wp_check_filetype($this->paths['thumbabs']);
		header("Content-Type: {$type['type']}");
		readfile($path);
		exit;
	}

	function error404() {
		//debug_print_backtrace();
		status_header( 404 );
		nocache_headers();
		include( get_query_template( '404' ) );
		die();
	}

	function getCachePath($create = false) {
		$dir = self::getCacheDir();
		if ($create) {
			@mkdir($dir, 0777, true);
		}
		return $dir . "/" . md5($this->paths['thumbupload']);
	}

	function addWatermarkCached() {
		$file = $this->getCachePath(true);
		if (!is_file($file)) {
			$wm = $this->addWatermark();
			$wm->sendFile("$file-temp");
			rename("$file-temp", $file);
		} else {
			$this->outputOriginal($file);
		}
	}

	static function getCacheDir() {
		return plugin_dir_path( __FILE__ ) . "/cache";
	}

	static function emptyCache() {
		$dir = self::getCacheDir();
		array_map('unlink', glob("$dir/*"));
	}
}

if ( is_admin() ){
	AddWatermarksSettings::register();
}
add_action( 'wp_ajax_watermark_image', array('AddWatermarkRequest', 'runAjax') );

