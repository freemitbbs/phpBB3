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
	'KMJ_LATEX'			  => 'Insert formula inline: [latex]E = mc^2[/latex]',
	'KMJ_LATEX_INLINE'	  => 'Insert formula inline: [latex_inline]E = mc^2[/latex_inline]',
	'KMJ_LATEX_PARAGRAPH' => 'Insert formula centered in a separate paragraph: [latex_para]E = mc^2[/latex_para]',
	'KMJ_POWERED_BY'	  => 'Powered by',
]);
