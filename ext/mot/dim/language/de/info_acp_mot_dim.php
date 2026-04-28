<?php
/**
*
* @package MoT DIM v1.2.0
* @copyright (c) 2024 - 2025 Mike-on-Tour
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* DO NOT CHANGE
*/
if ( !defined('IN_PHPBB') )
{
	exit;
}
if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_MOT_DIM'						=> 'Lösche inaktive Mitglieder',
	'ACP_MOT_DIM_SETTINGS'				=> 'Einstellungen',

	'ACP_MOT_DIM_SETTINGS_EXPL'			=> 'Hier kannst du die Einstellungen für diese Erweiterung ändern.',
	'ACP_MOT_DIM_VERSION'				=> '<img src="https://img.shields.io/badge/Version-%1$s-green.svg?style=plastic"><br>&copy; 2024 - %2$d by Mike-on-Tour',

	'ACP_MOT_DIM_GENERAL_SETTINGS'		=> 'Allgemeine Einstellungen',
	'ACP_MOT_DIM_ENABLE'				=> 'Funktion aktivieren',
	'ACP_MOT_DIM_ENABLE_EXPL'			=> 'Mit diesem Schalter kannst du die Erweiterung aktivieren bzw. deaktivieren.<br><span style="color:red">
											Vor dem Aktivieren solltest du sicher sein, dass die mit den nachfolgenden Einstellungen gewählte Konfiguration deinen Absichten
											entspricht und nicht versehentlich Mitglieder gelöscht werden, die erhalten bleiben sollen!</span>',

	'ACP_MOT_DIM_DELETE_SETTINGS'		=> 'Lösch-Einstellungen',
	'ACP_MOT_DIM_DAYS_DELETE'			=> 'Anzahl Tage bis zum Löschen',
	'ACP_MOT_DIM_DAYS_DELETE_EXP'		=> 'Anzahl der Tage nach der Registrierung bis zum automatischen Löschen des Mitgliedes, wenn es in dieser Zeit keine Aktivierung des
											Accounts oder kein Anmelden gab oder das Mitglied keinen Beitrag verfasst hat.',
	'ACP_MOT_DIM_ENABLE_SLEEPER'		=> 'Berücksichtige Schläfer',
	'ACP_MOT_DIM_ENABLE_SLEEPER_EXPL'	=> 'Wenn aktiviert (Standard), werden Schläfer (Mitglieder, die nach Aktivierung des Accounts nie eingeloggt waren) beim Löschen mit einbezogen.',
	'ACP_MOT_DIM_ENABLE_ZEROPOST'		=> 'Berücksichtige Nullposter',
	'ACP_MOT_DIM_ENABLE_ZEROPOST_EXPL'	=> 'Wenn aktiviert (Standard), werden Nullposter (Mitglieder, die nach Aktivierung des Accounts mindestens einmal eingeloggt waren, aber keinen
											Beitrag gepostet haben) beim Löschen mit einbezogen.',
	'ACP_MOT_DIM_PROTECTED_USERS'		=> 'Geschützte Mitglieder',
	'ACP_MOT_DIM_PROTECTED_USERS_EXPL'	=> 'Eingabe der Benutzernamen von Mitgliedern, die von einer Löschung ausgenommen werden sollen.<br>
											Zeilen mit Mitgliedernamen entfernen, um diese Mitglieder aus dieser Liste zu löschen.<br><strong>Nur ein Name pro Zeile!</strong>',
	'ACP_MOT_DIM_PROTECTED_GROUPS'		=> 'Geschützte Gruppen',
	'ACP_MOT_DIM_PROTECTED_GROUPS_EXPL'	=> 'Auswahl von <strong>Hauptgruppe(n)</strong>, deren Mitglieder von einer Löschung ausgenommen werden sollen. Bereits ausgewählte Gruppen
											sind hervorgehoben.<br>Unter Drücken und Halten der ´Strg´-Taste und Mausklick können mehrere Gruppen ausgewählt werden.',
	'ACP_MOT_DIM_CHECK_RESULT'			=> 'Einstellungen testen',
	'ACP_MOT_DIM_CHECK_RESULT_EXPL'		=> 'Nach Anklicken des Buttons rechts öffnet sich ein neues Fenster, in dem die Mitglieder aufgelistet werden, die mit der obigen
											Konfiguration gelöscht würden. So kannst du vor Aktivierung der Erweiterung prüfen, ob die gewählten Einstellungen das gewünschte
											Resultat erzeugen.<br><strong>Beachte bitte, dass die Einstellungen zuvor gespeichert werden müssen!</strong>',

	'ACP_MOT_DIM_CRON_SETTINGS'			=> 'Cron-Einstellungen',
	'ACP_MOT_DIM_CRON_INTERVAL'			=> 'Zeit zwischen zwei Cron-Läufen',
	'ACP_MOT_DIM_CRON_INTERVAL_EXPL'	=> 'Hier kannst du die Zeit zwischen zwei Aufrufen des Cron-Jobs einstellen. Außerdem hast du die Wahl zwischen Tagen und Stunden für die
											Auswahl dieses Zeitraumes.',
	'MOT_DIM_UNIT_HOUR'					=> 'Stunde(n)',
	'MOT_DIM_UNIT_DAY'					=> 'Tag(e)',
	'ACP_MOT_DIM_LAST_CRON_RUN'			=> 'Letzter Cron-Lauf',

	'ACP_MOT_DIM_SETTING_SAVED'			=> 'Die Einstellungen für die Erweiterung wurden erfolgreich gesichert.',

	'ACP_MOT_DIM_SUBMIT_CHANGES'		=> 'Änderungen übermitteln',
]);
