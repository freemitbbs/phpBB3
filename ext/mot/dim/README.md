# phpBB Delete Inactive Members Extension

![Version: 1.2.0](https://img.shields.io/badge/Version-1.2.0-green)  
  
![phpBB >=3.3.4,< 3.4.0@dev](https://img.shields.io/badge/phpBB->=3.3.4,%20<3.4.0@dev-009BDF)  

![PHP >= 8.0.30, < 8.6.0@dev](https://img.shields.io/badge/PHP->=8.0.30,%20<8.6.0@dev-blueviolet)
  
[![Build Status](https://github.com/Mike-on-Tour/dim/workflows/Tests/badge.svg)](https://github.com/Mike-on-Tour/dim/actions)

## Description
This extension lets the administrator automatically delete any users who have neither activated their account (inactive) nor visited the site after activation (sleepers) nor
posted anything (zeroposters).  
In the ACP the administrator can select the number of days since registration after which the approbriate users will be deleted. Protection from deletion can be defined for single
users by identifying them by their username or for groups in which users are a member, these groups must be the default group of the users to be protected.
Users are getting deleted by a cron job which intervals between two runs can be defined within the ACP for either hours or days. With each run the cron job handles a maximum of 1,000
users to prevent a heavy load on the database. So if you have a big number of users which are either inactive, sleepers or zeroposters it would be best to run the cron job every
hour or so to get rid of a large chunk of users in a short period of time. After cleaning the database you might want to set the interval to one or even more days.  
**Warning:** This extension deletes the users selected by the settings without further warning after enabling it on its ACP settings page. This can inadvertently affect users
you might want to keep if you do not pay attention to the settings you choose. Handle this extenson with care! Use it at your own risk!
  
## Install

1. Download the latest release.
2. Unzip the downloaded file.
3. Copy the unzipped folder to `/ext/` (if done correctly, you'll have the main extension class at `(your forum root)/ext/mot/dim/composer.json`).
4. Navigate in the ACP to `Customise -> Manage extensions`.
5. Look for `Delete Inactive Members` under the Disabled Extensions list, and click its `Enable` link.

## Update

1. Navigate in the ACP to `Customise -> Extension Management -> Extensions`.
2. Look for `Delete Inactive Members` under the Enabled Extensions list, and click its `Disable` link.
3. Using your favorite FTP software go to the `(your forum root)/ext/mot/dim` folder and delete all files and directories.
4. Locally unzip the file `mot_dim_x.y.z.zip` file (x, y and z are numbers indicating the major version, minor version and patch level).
5. Upload all files from your unzipped `dim` folder to your server into the `(your forum root)/ext/mot/dim`, please make certain that you use the binary mode for uploading.
6. Go back to the ACP and look for `Delete Inactive Members` under the Disabled Extensions list, and click its `Enable` link.

## Uninstall

1. Navigate in the ACP to `Customise -> Extension Management -> Extensions`.
2. Look for `Delete Inactive Members` under the Enabled Extensions list, and click its `Disable` link.
3. To permanently uninstall, click `Delete Data` and then delete the `/ext/mot/dim` folder.

## License
[GNU General Public License v2](http://opensource.org/licenses/GPL-2.0)
