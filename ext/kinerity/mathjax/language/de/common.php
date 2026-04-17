<?php
/**
 * MathJax3
 * An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2025, Thorsten Ahlers
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'KMJ_LATEX'			  => 'Fügt die Formel in eine Textzeile ein: [latex]E = mc^2[/latex] ',
	'KMJ_LATEX_INLINE'	  => 'Fügt die Formel in eine Textzeile ein: [latex_inline]E = mc^2[/latex_inline] ',
	'KMJ_LATEX_PARAGRAPH' => 'Fügt die Formel zentriert in einen separaten Absatz ein: [latex_para]E = mc^2[/latex_para] ',
	'KMJ_POWERED_BY'	  => 'Powered by',
]);
