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

// Added inside the uploads directory
define('ADD_WATERMARK_CACHE_DIR', 'watermark-cache');

function add_watermark_defaults() {
	static $defaults = array(
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
		'exclude' => '^wpcf7.*',
		// Servers supporting this would make the plugin more secure/portable.
		'supports_document_root' => false,
		'supports_end' => false,
		// Do not use in production.
		'debug' => false
	);
	
	return $defaults;
}

function add_watermark_option($name) {
	$defaults = add_watermark_defaults();

	if (!isset($defaults[$name])) {
		die("Unknown option: $name");
	}

	return get_option("add-watermark-$name", $defaults[$name]);
}

function add_watermark_header($type) {
	if (add_watermark_option('debug')) {
		echo "Would now add header for $type\n";
	} else {
		header("Content-Type: $type");
	}
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
		add_action('load-upload.php', array($settings, 'doBulkAction'));
		add_action('plugins_loaded', array($settings, 'loadTextdomain'));
		// Does not seem to work :-(
		//add_action('update_attached_file ', array($settings, 'removeAttachmentFromCache'));
		add_action('delete_attachment', array('AddWatermarksSettings', 'removeAttachmentFromCache'));
	}

	function loadTextdomain() {
		load_plugin_textdomain('add-watermark', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
	}
	
	static function pluginActivate() {
		$settings = new AddWatermarksSettings();
		$settings->writeHtaccessFile();
	}

	static function pluginDeactivate() {
		$settings = new AddWatermarksSettings();
		$settings->removeHtaccessFile();
		AddWatermarkRequest::emptyCache();
	}

	static function pluginUninstall() {
		foreach (add_watermark_defaults() as $name => $value) {
			delete_option('add-watermark-' . $name);
		}
	}
	
	static function removeAttachmentFromCache($attachmentId) {
		$meta = wp_get_attachment_metadata($attachmentId);
		if ($meta) { // < No meta for non-images.
			AddWatermarkRequest::removeFileFromCache($meta['file']);
			foreach ($meta['sizes'] as $name => $size) {
				AddWatermarkRequest::removeFileFromCache(dirname($meta['file']) . "/" . $size['file']);
			}
		}
	}

	function addWatermarkColumn($columns) {
		$columns['add-watermark'] = __("Watermark", 'add-watermark');
		return $columns;
	}

	function watermarkYes($post_id) {
		update_metadata('post', $post_id, 'add-watermark', 'yes');
		self::removeAttachmentFromCache($post_id);
	}

	function watermarkNo($post_id) {
		update_metadata('post', $post_id, 'add-watermark', 'no');
		self::removeAttachmentFromCache($post_id);
	}

	function watermarkUnset($post_id) {
		delete_metadata('post', $post_id, 'add-watermark');
		self::removeAttachmentFromCache($post_id);
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
window.addEventListener('load', function() {
	jQuery('<option value="add_watermark_yes">').text("<?php echo html_entity_decode(__('Insert watermark', 'add-watermark')) ?>").appendTo("select[name=\'action\']");
	jQuery('<option value="add_watermark_yes">').text("<?php echo html_entity_decode(__('Insert watermark', 'add-watermark')) ?>").appendTo("select[name=\'action2\']");
	jQuery('<option value="add_watermark_no">').text("<?php echo html_entity_decode(__('Remove watermark', 'add-watermark')) ?>").appendTo("select[name=\'action\']");
	jQuery('<option value="add_watermark_no">').text("<?php echo html_entity_decode(__('Remove watermark', 'add-watermark')) ?>").appendTo("select[name=\'action2\']");
	jQuery('<option value="add_watermark_unset">').text("<?php echo html_entity_decode(__('Set watermark to default', 'add-watermark')) ?>").appendTo("select[name=\'action\']");
	jQuery('<option value="add_watermark_unset">').text("<?php echo html_entity_decode(__('Set watermark to default', 'add-watermark')) ?>").appendTo("select[name=\'action2\']");
}, true);
</script>
<?php
	}

	function outputWatermarkColumn($column) {
		if ($column == 'add-watermark') {
			$meta = get_metadata('post', get_the_ID(), 'add-watermark', true);
			$actions = array(
				'yes' => __('protect', 'add-watermark'),
				'no' => __('unprotect', 'add-watermark'),
				'unset' => __('default', 'add-watermark')
			);
			if ($meta == 'yes') {
				$current = __('Protected', 'add-watermark');
				unset($actions['yes']);
			} else if ($meta == 'no') {
				$current = __('Unprotected', 'add-watermark');
				unset($actions['no']);
			} else {
				$current = __('Use default', 'add-watermark');
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
//		add_rewrite_rule('wp-content/uploads/(.*?\\.(png|jpe?g))', 'wp-admin/admin-ajax.php?action=watermark_image&path=$1');
	}

	function getUploadPath($fileName) {
		// Gets the uploads direcotry
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . "/" . $fileName;
	}
	
	/**
	 * Write the htaccess file in the uploads directory.
	 */
	function writeHtaccessFile() {
		$file = $this->getUploadPath('.htaccess');
		$content = is_file($file) ? file_get_contents($file) : '';
		$content = preg_replace('=^\\n?### WATERMARK START([\w\W]*)### WATERMARK END=m', '', $content);

		$upload_dir = wp_upload_dir();
		$upload_root = parse_url($upload_dir['baseurl']);
		if ( isset( $upload_root['path'] ) ) {
			$upload_root = trailingslashit($upload_root['path']);
		} else {
			$upload_root = '/';
		}

		$cache_root = $upload_root . ADD_WATERMARK_CACHE_DIR . '/';

		$exclude = add_watermark_option('exclude');
		$exclude = preg_replace('/[\\s\\n]/', '\\s', $exclude);
		if (add_watermark_option('supports_document_root')) {
			// nice server.
			$docRoot = '%{DOCUMENT_ROOT}';
		} else {
			// We hope this is the same as our document root.
			// For bad hosters (like 1und1)
			$docRoot = $_SERVER['DOCUMENT_ROOT'];
		}
		// $cacheDir = $docRoot . $cache_root;
		$cacheDir = trailingslashit(AddWatermarkRequest::getCacheDir());
		
		$content .= "\n### WATERMARK START";
		$content .= "\n# Document root: $docRoot";
		$content .= "\n# Cache dir: $cacheDir";

		$content .= "\nRewriteEngine On";
		$content .= "\n" . 'RewriteBase "' . $upload_root . '"';
		
		// Use cached files if they are there
		$content .= "\n" . 'RewriteCond "%{REQUEST_FILENAME}" -f';
		$content .= "\n" . 'RewriteCond $0 ^/?(.*\\.(jpe?g|png))$';
		$content .= "\n" . 'RewriteCond "' . $cacheDir . '%1" -f';
		$end = add_watermark_option('supports_end') ? ',END' : '';
		$content .= "\n" . 'RewriteRule (.*) "' . $cache_root . '$1" [L' . $end . ']';
		
		// If there is no don't watermark flag and it is an image, handle it.
		$content .= "\n" . 'RewriteCond $0 ^/?(.*\\.(jpe?g|png))$';
		$content .= "\n" . 'RewriteCond "' . $cacheDir . '%1.nowm" !-f';
		// Avoid recursing ito the cache directory
		$content .= "\n" . 'RewriteCond $0 "!^' . preg_quote(ADD_WATERMARK_CACHE_DIR) . '"';

		// User can set a regexp that should not get watermarked.
		if ($exclude) {
			$content .= "\n" . 'RewriteCond $0 "!' . $exclude . '"';
		}

		// Note: We hardcode the admin directory. User can update this: the .htaccess is regenerated after a cache refresh.
		$content .= "\n" . 'RewriteRule "(.*)" "' . get_admin_url() . 'admin-ajax.php?action=watermark_image&path=$1" [L]';
		$content .= "\n### WATERMARK END";
		$content .= "\n";
		file_put_contents($file, $content);
	}
	
	function removeHtaccessFile() {
		$file = $this->getUploadPath('.htaccess');
		@unlink($file);
	}
	
	function storeSettings() {
			flush_rewrite_rules();
			$this->writeHtaccessFile();
			AddWatermarkRequest::emptyCache();
	}

	function onSettingsLoad() {
		if(isset($_GET['settings-updated']) && $_GET['settings-updated']) {
			$this->storeSettings();
		}
		wp_enqueue_media();
		wp_enqueue_script( 'add-watermark-js', plugin_dir_url( __FILE__ ) . 'js/settings.js');
	}

	function loadPositionSettings() {
		register_setting( 'add-watermark-settings', 'add-watermark-default-active' );
		register_setting( 'add-watermark-settings', 'add-watermark-exclude' );
		register_setting( 'add-watermark-settings', 'add-watermark-image' );
		register_setting( 'add-watermark-settings', 'add-watermark-size' );
		$this->registerUnitSelect('add-watermark-horizontal-pos');
		register_setting( 'add-watermark-settings', 'add-watermark-horizontal-pos-from' );
		$this->registerUnitSelect('add-watermark-vertical-pos');
		register_setting( 'add-watermark-settings', 'add-watermark-vertical-pos-from' );
		$this->registerMinMaxSize('add-watermark-width');
		$this->registerMinMaxSize('add-watermark-height');

		add_settings_section( 'add-watermark-general', __('General settings', 'add-watermark'), null, 'add-watermark-settings');
		add_settings_field( 'add-watermark-default-active', __('Watermark images that do not have an explicit setting', 'add-watermark'), array($this, 'outputDefaultSelect'), 'add-watermark-settings', 'add-watermark-general');
		add_settings_field( 'add-watermark-exclude', __('Files to exclude (regexp, relative to uploads dir)', 'add-watermark'), array($this, 'outputExclude'), 'add-watermark-settings', 'add-watermark-general');
		add_settings_section( 'add-watermark-image', __('Watermark image', 'add-watermark'), null, 'add-watermark-settings');
		add_settings_field( 'add-watermark-image', __('Image', 'add-watermark'), array($this, 'outputImageSelect'), 'add-watermark-settings', 'add-watermark-image');
		add_settings_section( 'add-watermark-position', __('Watermark position', 'add-watermark'), array($this, 'addPositionDescription'), 'add-watermark-settings');
		add_settings_field( 'add-watermark-size', __('Fit the image', 'add-watermark'), array($this, 'addSizeSelect'), 'add-watermark-settings', 'add-watermark-position');
		add_settings_field( 'add-watermark-horizontal-pos', __('Horizontal position', 'add-watermark'), array($this, 'outputHorizontalPos'), 'add-watermark-settings', 'add-watermark-position');
		add_settings_field( 'add-watermark-width', __('Width', 'add-watermark'), array($this, 'outputWidth'), 'add-watermark-settings', 'add-watermark-position');
		add_settings_field( 'add-watermark-vertical-pos', __('Vertical Position', 'add-watermark'), array($this, 'outputVerticalPos'), 'add-watermark-settings', 'add-watermark-position');
		add_settings_field( 'add-watermark-height', __('Height', 'add-watermark'), array($this, 'outputHeight'), 'add-watermark-settings', 'add-watermark-position');
	}


	function addPositionDescription() {
?>
<div class="add-watermark-preview">
	<div class="watermark-pos">
		<div class="watermark-pos2">
			<div class="watermark" style=""></div>
		</div>
	</div>
</div>
<?php
	}

	function outputExclude() {
		$setting = add_watermark_option("exclude");
?>
<input type="text" name="add-watermark-exclude" value="<?php echo esc_attr($setting); ?>" />
<?php
	}

	function outputDefaultSelect() {
		$setting = add_watermark_option("default-active") * 1;
?>
<select name="add-watermark-default-active">
	<option value="1" <?php if ($setting) echo ' selected="selected"'; ?>><?php echo __('Add watermark to all images', 'add-watermark') ?></option>
	<option value="0" <?php if (!$setting) echo ' selected="selected"'; ?>><?php echo __('Do not add watermark', 'add-watermark') ?></option>
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
	<option value="contain"
		<?php if ($setting == 'contain') echo ' selected="selected"'; ?>><?php echo __('Shrink to keep aspect', 'add-watermark') ?></option>
	<option value="full"
		<?php if ($setting == 'auto') echo ' selected="selected"'; ?>><?php echo __('Stretch to area', 'add-watermark') ?></option>
</select>
<?php
}

	function outputImageSelect() {
		$setting = add_watermark_option("image", "");
		$image = preg_match("/^\\d+$/", $setting) ? wp_get_attachment_url($setting * 1) : '';
?>
<div class="add-watermark-image">
	<input class="add-watermark-image-id" type="hidden"
		name="add-watermark-image" value="<?php echo esc_attr($setting) ?>" />
	<input class="add-watermark-image-url" type="hidden"
		name="add-watermark-image-url"
		value="<?php echo esc_attr($image) ?>" /> <img
		class="add-watermark-image-preview"
		style="width: auto; height: auto; max-width: 300px; max-height: 200px;"
		src="<?php echo esc_attr($image) ?>" />
	<div class="add-watermark-image-path"></div>
	<div>
		<a class="add-watermark-image-select" href="#"><?php echo __('Choose image', 'add-watermark') ?></a>
	</div>
</div>

<?php
	}

	function outputHorizontalPos() {
		$setting = add_watermark_option("horizontal-pos-from");
?>
<?php echo __('Align to', 'add-watermark') ?>
<select name="add-watermark-horizontal-pos-from">
	<option value="left"
		<?php if ($setting == 'left') echo ' selected="selected"'; ?>><?php echo __('Left', 'add-watermark') ?></option>
	<option value="center"
		<?php if ($setting == 'center') echo ' selected="selected"'; ?>><?php echo __('Center', 'add-watermark') ?></option>
	<option value="right"
		<?php if ($setting == 'right') echo ' selected="selected"'; ?>><?php echo __('Right', 'add-watermark') ?></option>
</select>
<?php echo __('then move', 'add-watermark') ?>
<?php $this->outputUnitSelect('horizontal-pos'); ?>
<?php echo __('to the right.', 'add-watermark') ?>
<?php
	}

	function outputWidth() {
		$this->outputMinMaxSize('width');
	}

	function outputVerticalPos() {
		$setting = add_watermark_option("vertical-pos-from");
?>
<?php echo __('Align to', 'add-watermark') ?>
<select name="add-watermark-vertical-pos-from">
	<option value="top"
		<?php if ($setting == 'top') echo ' selected="selected"'; ?>><?php echo __('Top', 'add-watermark') ?></option>
	<option value="center"
		<?php if ($setting == 'center') echo ' selected="selected"'; ?>><?php echo __('Center', 'add-watermark') ?></option>
	<option value="bottom"
		<?php if ($setting == 'bottom') echo ' selected="selected"'; ?>><?php echo __('Bottom', 'add-watermark') ?></option>
</select>
<?php echo __('then move', 'add-watermark') ?>
<?php $this->outputUnitSelect('vertical-pos'); ?>
<?php echo __('to the bottom.', 'add-watermark') ?>
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
		echo '<span style="width: 5em; display: inline-block;">' . __('Desired:', 'add-watermark') . '</span>';
		$this->outputUnitSelect("$name");
		echo '<br/><span style="width: 5em; display: inline-block;">' . __('Min:', 'add-watermark') . '</span>';
		$this->outputUnitSelect("$name-min");
		echo '<br/><span style="width: 5em; display: inline-block;">' . __('Max:', 'add-watermark') . '</span>';
		$this->outputUnitSelect("$name-max");
	}

	function outputUnitSelect($name) {
		$setting = add_watermark_option("$name");
		$unit = add_watermark_option("$name-unit");
?>
<input type="text" name="add-watermark-<?php echo $name ?>"
	value="<?php echo esc_attr($setting) ?>" />
<select name="add-watermark-<?php echo $name ?>-unit">
	<option value="px"
		<?php if ($unit != '%') echo ' selected="selected"'; ?>><?php echo __('Pixel', 'add-watermark') ?></option>
	<option value="%"
		<?php if ($unit == '%') echo ' selected="selected"'; ?>><?php echo __('%', 'add-watermark') ?></option>
</select>

<?php
	}
}

class AddWatermarkOptionsPage {
	function registerMenu() {
		$page = add_options_page( __('Add watermark', 'add-watermark'), __('Add watermark', 'add-watermark'), 'manage_options', 'add-watermark-menu', array($this, 'generateOptions') );
	}

	function generateOptions() {
?>
<div class="wrap add-watermark-settings">
	<h2><?php echo __('Add watermark settings', 'add-watermark') ?></h2>
	<form method="post" action="options.php"> 
<?php if(!function_exists('imagecreatefromjpeg')) { ?>
	<p style="color:red"><?php echo __('GD not found. Install the phpX-gd package (debian/ubuntu) or ask your hoster to enable that module.', 'add-watermark') ?></php>
<?php } /* imagecreatefromjpeg */ ?>

<?php settings_fields( 'add-watermark-settings' ); 
do_settings_sections( 'add-watermark-settings' );
?>

<?php submit_button(__('Submit and empty Cache', 'add-watermark')); ?>
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
		@ini_set( 'memory_limit', apply_filters( 'image_memory_limit', WP_MAX_MEMORY_LIMIT ) );
		if (add_watermark_option('debug')) {
			echo "Loading image $imagepath of type {$type['type']} having memory: " . ini_get('memory_limit') . "\n";
		}
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
			if (add_watermark_option('debug')) {
				echo "xxx\n";
			}
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

		if (add_watermark_option('debug')) {
			echo "Adding watermark at ($x, $y) with size ($width, $height).\n";
		}
		imagecopyresampled($this->image, $watermark, (int) $x, (int) $y, 0, 0, (int) $width, (int) $height, imagesx($watermark), imagesy($watermark));
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
		add_watermark_header($this->type['type']);
		switch ($this->type['type']) {
		case 'image/jpeg':
			@imagejpeg($this->image, $filename);
			break;
		case 'image/png':
			@imagepng($this->image, $filename);
			break;
		}
	}

}
class AddWatermarkRequest {
	static function runAjax() {
		$m = new AddWatermarkRequest();
		$m->searchAttachments();
		if ($m->shouldAddWatermark()) {
			if (add_watermark_option('debug')) {
				echo "Should add watermark: yes.\n";
			}
			$m->addWatermarkCached();
		} else {
			if (add_watermark_option('debug')) {
				echo "Should add watermark: no.\n";
			}
			$m->linkOriginalInCache();
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
			if (add_watermark_option('debug')) {
				echo "No such file: " . $upload_dir['basedir'] . "/" . $paths['thumbupload'];
			}
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

		if (add_watermark_option('debug')) {
			echo "Paths found for the image:\n";
			print_r($this->paths);
		}
		
		$myquery = new WP_Query(array(
			'post_type' => 'attachment',
			'post_status' => 'any',
			'meta_key' => '_wp_attached_file',
			'meta_value' => $this->paths['attachmentupload']));

		$attachments = $myquery->get_posts();
		
		if (count($attachments) != 1) {
			//print_r($attachments);
			// Second attempt: scan for a post that has that thumbnail.
			$myquery = new WP_Query(array(
					'post_type' => 'attachment',
					'post_status' => 'any',
					'meta_query' => array(array(
						'key' => '_wp_attachment_metadata',
						'value' => '"' . basename($this->paths['thumbupload']) . '"',
						'compare' => 'LIKE'
					),
					array(
						'key' => '_wp_attached_file',
						'value' => preg_quote(dirname($this->paths['thumbupload'])) . '/.*',
						'compare' => 'REGEXP'
					))
				));
			$attachments = $myquery->get_posts();
		}
		if (count($attachments) != 1) {
			$this->error404();
		}
		$this->attachment = $attachments[0];
		if (add_watermark_option('debug')) {
			echo "Found this attachment:\n";
			print_r($this->attachment);
		}

		// handle the size
		if ($this->paths['attachmentfile'] != $this->paths['thumbfile']) {
			$meta = wp_get_attachment_metadata($this->attachment->ID);
			if (add_watermark_option('debug')) {
				echo "This is no full size image. Scanning meta sizes:\n";
				print_r($meta);
			}
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
			if (add_watermark_option('debug')) {
				echo "There was a problem generating the watermark: $e";
			}
			$this->error404();
		}
	}

	function outputOriginal($path = null) {
		if ($path == null) {
			$path = $this->paths['thumbabs'];
		}
		$type = wp_check_filetype($this->paths['thumbabs']);
		add_watermark_header($type['type']);
		readfile($path);
		exit;
	}
	
	function linkOriginalInCache() {
		$cacheFile = $this->getCachePath(true);
		$this->removeFromCache();
		@touch("$cacheFile.nowm");
	}
	

	function error404() {
		if (add_watermark_option('debug')) {
			debug_print_backtrace();
		}
		status_header( 404 );
		nocache_headers();
		include( get_query_template( '404' ) );
		die();
	}

	function removeFromCache() {
		self::removeFileFromCache($this->paths['thumbupload']);
	}
	
	/**
	 * Removes a file from the cache
	 * @param relativeFile The relative filename inside the uploads dir.
	 */
	static function removeFileFromCache($relativeFile) {
		$cacheFile = self::getCacheDir() . "/" . $relativeFile;
		@unlink("$cacheFile");
		@unlink("$cacheFile.nowm");
	}

	function getCachePath($createDir = false) {
		$path = self::getCacheDir() . "/" . $this->paths['thumbupload'];
		if ($createDir) {
			if (!is_dir(dirname($path))) {
				@mkdir(dirname($path), 0777, true);
				// Servers not supporting END cannot support chache dir protection.
				if (!is_file(self::getCacheDir() . '/.htaccess') && add_watermark_option('supports_end')) {
					// We could also use deny from all
					// This might cause an internal server error if order is not allowed on that server.
					// So we use mod_rewrite instead.
					file_put_contents(self::getCacheDir() . '/.htaccess', "RewriteEngine ON\nRewriteRule .* / [F]");
				}
			}
		}
		return $path;
	}

	function addWatermarkCached() {
		$file = $this->getCachePath(true);
		if (!is_file($file)) {
			if (add_watermark_option('debug')) {
				echo "Generating watermark\n";
			}
			$wm = $this->addWatermark();
			if (add_watermark_option('debug')) {
				echo "Updating cache\n";
			}
			$wm->sendFile("$file-temp");
			$this->removeFromCache();
			@rename("$file-temp", $file);
		} else {
			if (add_watermark_option('debug')) {
				echo "There is a cached version of this file. Send it.\n";
			}
			// Some race condition htaccess did not catch.
			$this->outputOriginal($file);
		}
	}

	static function getCacheDir() {
		//alternative: plugin_dir_path( __FILE__ ) . "cache"
		return trailingslashit(wp_upload_dir()['basedir']) . ADD_WATERMARK_CACHE_DIR;
	}
	
	static function emptyCache() {
		$dir = self::getCacheDir ();
		self::deleteContents($dir);
	}
	
	static function deleteContents($dir) {
		if (is_dir($dir)) {
			$files = array_diff(scandir($dir), ['.', '..']);
			foreach ($files as $file) {
				if (is_dir("$dir/$file")) {
					self::deleteContents("$dir/$file");
					rmdir("$dir/$file");
				} else {
					unlink("$dir/$file");
				}
			}
		}
	}
}

if ( is_admin() ){
	AddWatermarksSettings::register();
}
add_action( 'wp_ajax_watermark_image', ['AddWatermarkRequest', 'runAjax'] );
add_action( 'wp_ajax_nopriv_watermark_image', ['AddWatermarkRequest', 'runAjax'] );

$pluginFile = WP_PLUGIN_DIR . '/add-watermark/add-watermark.php';
register_activation_hook($pluginFile, ['AddWatermarksSettings', 'pluginActivate']);
register_deactivation_hook($pluginFile, ['AddWatermarksSettings', 'pluginDeactivate']);
register_uninstall_hook($pluginFile, ['AddWatermarksSettings', 'pluginUninstall']);
