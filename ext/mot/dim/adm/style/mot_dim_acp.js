/**
*
* @package MoT DIM v0.1.0
* @copyright (c) 2024 Mike-on-Tour
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

(function($) {  // Avoid conflicts with other libraries

'use strict';

$("#mot_dim_cron_interval").on("focus", function() {
	// Set max input to 23 if the unit is hours
	if ($("#mot_dim_cron_unit").val() == 0)	{
		$(this).attr('max', 23);
	}

	// Set max input to the number of days since registration to deletion if unit is days
	if ($("#mot_dim_cron_unit").val() == 1)	{
		$(this).attr('max', $("#mot_dim_days_delete").val());
	}
});

$("#mot_dim_cron_unit").blur(function() {
	// Set max input for interval to 23 if hours are selected
	if ($(this).val() == 0) {
		$("#mot_dim_cron_interval").attr('max', 23);
	}

	// Set max input for interval to the number of days since registration to deletion if days are selected
	if ($(this).val() == 1)	{
		$("#mot_dim_cron_interval").attr('max', $("#mot_dim_days_delete").val());
	}
});

})(jQuery); // Avoid conflicts with other libraries
