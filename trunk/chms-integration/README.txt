Installation Instructions:

Get the latest version of the project files at
http://redmine.refreshcache.com/projects/npmiwpintegration

On your WordPress system:

* Copy the "chms-authentication" folder to your WordPress installation's
  wp-content/plugins folder
* Make sure the logs folder is writable by apache
* Log into your WordPress admin account. Under the "Plugins" section, you should
  see "ChMS Web Services Integration" and "ChMS Dynamic Grid" listed. Click "Activate" on both plugins. You should
  now see an "ChMS Integration" option under the "Settings" menu on the
  bottom/left.
* Enter the path to your web service in the *Service Path* 
  (e.g. - "http://your-public-arena-install/api.svc/").
  The service should work fine over HTTPS if needed.
* Enter your *Arena Organization ID* (it's probably "1").
* Enter the *Arena Security Roles* you want to limit to (e.g. - Arena 
  Administrator, Blog Editor), or leave it blank if you want any Arena user to
  be able to log in.
* Enter the *Default WordPress Role* for newly created accounts (e.g. - Author).
  Note: When a user logs into WordPress that *does not* have an account yet,
  this plugin will create a WordPress user for them with the "Default WordPress
  Role" setting.
* Enter the *Arena API Key* found in the API Applications tab on your Arena install.
* Enter the *Arena API Secret* found in the API Applications tab on your Arena install.