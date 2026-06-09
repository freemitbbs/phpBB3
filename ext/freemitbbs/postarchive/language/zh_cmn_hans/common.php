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
	'UCP_POSTARCHIVE' => '帖子归档',
	'POSTARCHIVE_READY_TITLE' => '归档已生成',
	'POSTARCHIVE_READY_EXPLAIN' => '你当前的归档将在有限时间内可下载。',
	'POSTARCHIVE_CREATE_TITLE' => '创建你的帖子归档',
	'POSTARCHIVE_CREATE_EXPLAIN' => '请求创建一个 ZIP 归档，其中包含你的账号当前可见且已审核通过的帖子。系统会通过 cron 后台生成，按月份拆分为 CSV 和文本文件，并在完成后给你发送私信。',
	'POSTARCHIVE_VISIBLE_POSTS' => '可导出的帖子数',
	'POSTARCHIVE_ARCHIVE_CREATED' => '创建时间',
	'POSTARCHIVE_ARCHIVE_EXPIRES' => '过期时间',
	'POSTARCHIVE_ARCHIVE_POSTS' => '归档内帖子数',
	'POSTARCHIVE_ARCHIVE_SIZE' => '归档大小',
	'POSTARCHIVE_PENDING_TITLE' => '归档请求已排队',
	'POSTARCHIVE_PROCESSING_TITLE' => '归档正在生成',
	'POSTARCHIVE_PENDING_EXPLAIN' => '你的归档请求已进入队列。归档生成后，你会收到包含下载链接的私信。',
	'POSTARCHIVE_JOB_STATUS' => '状态',
	'POSTARCHIVE_JOB_REQUESTED' => '请求时间',
	'POSTARCHIVE_JOB_STARTED' => '开始时间',
	'POSTARCHIVE_STATUS_QUEUED' => '排队中',
	'POSTARCHIVE_STATUS_PROCESSING' => '处理中',
	'POSTARCHIVE_FAILED_TITLE' => '归档请求失败',
	'POSTARCHIVE_FAILED_EXPLAIN' => '上一次归档请求未能完成。你可以重新提交请求。',
	'POSTARCHIVE_FAILED_TIME' => '失败时间',
	'POSTARCHIVE_CREATE_BUTTON' => '请求归档',
	'POSTARCHIVE_RECREATE_BUTTON' => '请求新归档',
	'POSTARCHIVE_QUEUED_BUTTON' => '归档已请求',
	'POSTARCHIVE_DOWNLOAD_BUTTON' => '下载归档',
	'POSTARCHIVE_LOGIN_REQUIRED' => '请先登录，然后再下载你的帖子归档。',
	'POSTARCHIVE_QUEUE_FAILED' => '无法将帖子归档请求加入队列。请稍后再试。',
	'POSTARCHIVE_NOT_AVAILABLE' => '该帖子归档已不可用。请重新创建归档。',
	'POSTARCHIVE_WINDOW_HOURS' => '%d 小时',
	'POSTARCHIVE_WINDOW_SECONDS' => '%d 秒',
	'POSTARCHIVE_PM_SUBJECT' => '你的帖子归档已生成',
	'POSTARCHIVE_PM_BODY' => "你的帖子归档已生成。\n\n请通过以下链接下载：\n%1\$s\n\n该下载链接有效期至 %2\$s（生成后 %3\$s）。超过这个时间窗口后，归档文件会被自动删除。\n\n包含帖子数：%4\$d\n归档大小：%5\$s",
]);
