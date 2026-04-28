# Change Log
All changes to `Delete Inactive Members` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [1.2.0] - 2025-11-25

### Added

### Changed
-	Minimum version of phpBB to 3.3.4
-	PHP Compatibility to minimum version of PHP 8.0.30 and maximum version of 8.5.x
-	The condition under which the cron task is active (is_runnable) from always true to depending on activation of the functionality within the ACP settings page
-	All constructor declarations to [Constructor Property Promotion](https://www.php.net/releases/8.0/de.php#constructor-property-promotion) (a new PHP feature starting with PHP 8.0)
-	All function declarations into parameters with type declarations

### Fixed
-	A missing plural language variable in `language/xx/mot_dim_main.php`

### Removed


## [1.1.1] - 2024-11-23

### Added

### Changed
-	PHP Compatibility now includes PHP 8.4.x

### Fixed

### Removed


## [1.1.0] - 2024-09-05

### Added
-	An `ORDER BY u.user_id ASC` to the SQL query in `cron/task/mot_dim_cron.php` in order to get oldest users first
-	Improved the logging by discerning the log messages by the deleted user type (suggested by LukeWcs)

### Changed
-	Some variable names in `cron/task/mot_dim_cron.php` from camel case to snake case

### Fixed

### Removed


## [1.0.2] - 2024-08-25

### Added

### Changed

### Fixed
-	The wrong sequence of function calls to delete users and then try to get usernames for deleted user_ids for logging in `cron/task/mot_dim_cron.php` (bug reported by LukeWCS)

### Removed


## [1.0.1] - 2024-08-21

### Added
-	An additional condition to check for the USER_TABLE's `user_lastpost_time` and POSTS_TABLE's `post_visibility` columns to prevent zeroposters from being deleted if they
	posted once and this post has not been approved when the user was due for deletion (suggested by LukeWCS)

### Changed

### Fixed

### Removed


## [1.0.0] - 2024-04-14

### Added
-	Added two switches to the ACP settings page to incorporate sleepers and zeroposters into the deletion

### Changed
-	The building of the SQL query within `cron/task/mot_dim_cron.php`
-	The last column of the "test settings" window from displaying only not activated members to the reason why members are displayed

### Fixed

### Removed
-	The comment that disabled the actual deletion of the selected users within `cron/task/mot_dim_cron.php`


## [0.2.0] - 2024-04-10

### Added
-	A warning notice to the language key which explains the enabling switch
-	A new function to display those users who would be deleted while applying the current settings with the new files `config/routing.yml`, `controller/mot_dim_result_check.php`
	and `styles/all/template/mot_dim_result_check.html`

### Changed
-	The config value for enabling the extension from `true` (enabled) to `false` (disabled) to prevent an inadvertent usage
-	Renamed the language file `mot_dim_log.php` into `mot_dim_main.php`

### Fixed

### Removed


## [0.1.0] - 2024-04-09

### Added
-	The basic directory and file structure necessary for a phpBB 3.3.x extension
-	`CHANGELOG.md` and `README.md` files
-	The `ext.php` file and its necessary language files (`language/de/mot_dim_ext_enable_error.php`, `language/de_x_sie/mot_dim_ext_enable_error.php`,
	`language/en/mot_dim_ext_enable_error.php`)
-	The files necessary for creating the ACP (`acp/mot_dim_acp_info.php`, `acp/mot_dim_acp_module.php`, `adm/style/acp_mot_dim_settings.html`, `adm/style/mot_dim_acp.css`,
	`adm/style/mot_dim_acp.js`, `config/services.yml`, `controller/mot_dim_acp.php`, `language/de/info_acp_mot_dim.php`, `language/de_x_sie/info_acp_mot_dim.php`,
	`language/en/info_acp_mot_dim.php` and `migrations/v_0_1_0.php`)
-	A listener file `event/mot_dim_listener.php` to load the language array for logging user deletion

### Changed

### Fixed

### Removed

