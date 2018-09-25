
You need an apache Server with mod_rewrite installed and a recent php version with gd enabled. ~90% of the web hosting providers out there provide this by default.

Install and activate this plugin.

Then go to Settings->Add watermark. You can upload or chose your watermark image there. For a first test, you can leave the position settings as they are.

= Manual uninstall =

Mind that the plugin adds a .htaccess file for processing the images.

If you deactivate / uninstall it, the hooks will automatically be deleted.

If that fails (or you want to uninstall manually), delete:

* The plugin directory (as you would with every other plugin)
* Delete wp-content/uploads/.htaccess
* Delete wp-content/uploads/watermark-cache/