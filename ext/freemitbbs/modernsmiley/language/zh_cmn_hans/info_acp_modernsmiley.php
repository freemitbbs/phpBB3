<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_MODERNSMILEY' => 'Modern Smiley',
	'MODERNSMILEY_EXPLAIN' => '在这里统一管理 phpBB 旧表情和 Unicode emoji 的渲染。这里新增的表情会自动使用共享占位图片，并在设置了 Unicode 序列后通过可配置的资源 URL 模板渲染。',
	'MODERNSMILEY_ASSET_FIELDSET' => 'Emoji 资源包',
	'MODERNSMILEY_ASSET_EXPLAIN' => '这些 URL 模板同时用于 Unicode emoji 和现代表情替换。请用 {seq} 表示 Unicode 序列应插入的位置。后备模板是可选的，当主资源加载失败时会使用它。',
	'MODERNSMILEY_ASSET_URL_PATTERN' => '主资源 URL 模板',
	'MODERNSMILEY_ASSET_URL_PATTERN_EXPLAIN' => '例如：https://fonts.gstatic.com/s/e/notoemoji/latest/{seq}/512.webp',
	'MODERNSMILEY_ASSET_FALLBACK_URL_PATTERN' => '后备资源 URL 模板',
	'MODERNSMILEY_ASSET_FALLBACK_URL_PATTERN_EXPLAIN' => '可选。例如：https://fonts.gstatic.com/s/e/notoemoji/latest/{seq}/512.gif',
	'MODERNSMILEY_ASSET_SAMPLE' => '主资源示例 URL',
	'MODERNSMILEY_ASSET_FALLBACK_SAMPLE' => '后备资源示例 URL',
	'MODERNSMILEY_HOVER_ASSET_URL_PATTERN' => '悬停资源 URL 模板',
	'MODERNSMILEY_HOVER_ASSET_URL_PATTERN_EXPLAIN' => '可选。如果设置，emoji 将保持主资源，直到鼠标悬停时才切换到这里的资源。',
	'MODERNSMILEY_HOVER_ASSET_FALLBACK_URL_PATTERN' => '悬停后备资源 URL 模板',
	'MODERNSMILEY_HOVER_ASSET_FALLBACK_URL_PATTERN_EXPLAIN' => '可选。悬停资源加载失败时使用。',
	'MODERNSMILEY_HOVER_ASSET_SAMPLE' => '悬停示例 URL',
	'MODERNSMILEY_HOVER_ASSET_FALLBACK_SAMPLE' => '悬停后备示例 URL',
	'MODERNSMILEY_ASSET_URL_INVALID' => '资源 URL 模板必须包含 {seq}。',
	'MODERNSMILEY_ASSET_FALLBACK_URL_INVALID' => '后备资源 URL 模板必须留空或包含 {seq}。',
	'MODERNSMILEY_HOVER_ASSET_URL_INVALID' => '悬停资源 URL 模板必须留空或包含 {seq}。',
	'MODERNSMILEY_HOVER_ASSET_FALLBACK_URL_INVALID' => '悬停后备资源 URL 模板必须留空或包含 {seq}。',
	'MODERNSMILEY_FIELDSET' => '现有表情',
	'MODERNSMILEY_NEW_FIELDSET' => '新增表情',
	'MODERNSMILEY_NEW_EXPLAIN' => '直接在本页创建新的表情条目。扩展会自动使用核心占位图片，因此无需再进入旧版 Smilies ACP。',
	'MODERNSMILEY_LEGACY' => '旧版表情',
	'MODERNSMILEY_CODES' => '代码',
	'MODERNSMILEY_EMOTIONS' => '含义',
	'MODERNSMILEY_POSTING' => '显示在发帖表单',
	'MODERNSMILEY_ORDER' => '顺序',
	'MODERNSMILEY_EMOJI_SEQ' => 'Emoji 序列',
	'MODERNSMILEY_EMOJI_SEQ_EXPLAIN' => '示例：1f603 或 2764-fe0f。留空则继续使用原始 phpBB 表情图片。',
	'MODERNSMILEY_NEW_SEQ_EXPLAIN' => '例如 1f44d 或 1f468-200d-1f4bb。留空则创建一个仍使用占位图片的经典表情。',
	'MODERNSMILEY_NEW_PREVIEW_HINT' => '保存后显示预览',
	'MODERNSMILEY_PREVIEW' => '现代预览',
	'MODERNSMILEY_INVALID_INPUT' => '表情输入无效：%s',
	'MODERNSMILEY_NEW_ROW_INVALID' => '新增表情必须同时填写代码和含义。',
	'MODERNSMILEY_NEW_SEQ_INVALID' => '新增表情的 emoji 序列格式无效：%s',
]);
