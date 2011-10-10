=== Refresh Cache ===
Contributors: russell.todd
Tags: arena, chms
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: trunk

Use your Arena ChMS installation for user authentication and data retrieval.

== Description ==

This plugin is a demonstration to show how you can use WordPress as a front-end CMS and allow Arena to manage user data. It utilizes WP's built-in extension mechanisms to override core WP functionality and provide additional features to your WordPress site. 

== Installation ==

1. Upload the entire 'refreshcache' folder to the '/wp-content/plugins/' directory.
2. Create a file in '/wp-content/plugins/refreshcache' called 'rc.log' and make certain it is writable by the web server. In a production environment you'll need to rotate this file and/or turn off logging.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Go to the Arena API Plugin menu item under 'Settings' and enter the URL, API Key and API Secret from your Arena API Application.
