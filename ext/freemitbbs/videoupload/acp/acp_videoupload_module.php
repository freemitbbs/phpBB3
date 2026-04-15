<?php

namespace freemitbbs\videoupload\acp;

class acp_videoupload_module
{
	private const FORM_KEY = 'freemitbbs/videoupload';

	public string $tpl_name;
	public string $page_title;
	public string $u_action;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \phpbb\config\config $config */
		$config = $phpbb_container->get('config');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');

		$language->add_lang('info_acp_videoupload', 'freemitbbs/videoupload');
		$language->add_lang('common', 'freemitbbs/videoupload');

		$this->tpl_name = 'acp_videoupload';
		$this->page_title = 'ACP_VIDEOUPLOAD';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$enabled = (int) $request->variable('videoupload_enabled', 0);
			$max_size_mb = max(1, min(2048, (int) $request->variable('videoupload_max_size_mb', 64)));
			$endpoint = trim((string) $request->variable('videoupload_s3_endpoint', '', true));
			$region = trim((string) $request->variable('videoupload_s3_region', 'us-east-1', true));
			$bucket = trim((string) $request->variable('videoupload_s3_bucket', '', true));
			$access_key = trim((string) $request->variable('videoupload_s3_access_key', '', true));
			$secret_key = trim((string) $request->variable('videoupload_s3_secret_key', '', true));
			$path_prefix = trim((string) $request->variable('videoupload_s3_path_prefix', 'videos', true));
			$public_base_url = trim((string) $request->variable('videoupload_s3_public_base_url', '', true));
			$use_path_style = (int) $request->variable('videoupload_s3_use_path_style', 0);
			$acl = trim((string) $request->variable('videoupload_s3_acl', 'public-read', true));
			$clear_secret = (int) $request->variable('videoupload_s3_secret_key_clear', 0);

			$config->set('videoupload_enabled', (string) $enabled);
			$config->set('videoupload_max_size_mb', (string) $max_size_mb);
			$config->set('videoupload_s3_endpoint', $endpoint);
			$config->set('videoupload_s3_region', $region !== '' ? $region : 'us-east-1');
			$config->set('videoupload_s3_bucket', $bucket);
			$config->set('videoupload_s3_access_key', $access_key);
			$config->set('videoupload_s3_path_prefix', trim($path_prefix, " \t\n\r\0\x0B/"));
			$config->set('videoupload_s3_public_base_url', rtrim($public_base_url, '/'));
			$config->set('videoupload_s3_use_path_style', (string) ($use_path_style ? 1 : 0));
			$config->set('videoupload_s3_acl', $acl !== '' ? $acl : 'public-read');

			if ($clear_secret)
			{
				$config->set('videoupload_s3_secret_key', '');
			}
			elseif ($secret_key !== '')
			{
				$config->set('videoupload_s3_secret_key', $secret_key);
			}

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'VIDEOUPLOAD_ENABLED' => (int) ($config['videoupload_enabled'] ?? 0),
			'VIDEOUPLOAD_MAX_SIZE_MB' => (int) ($config['videoupload_max_size_mb'] ?? 64),
			'VIDEOUPLOAD_S3_ENDPOINT' => (string) ($config['videoupload_s3_endpoint'] ?? ''),
			'VIDEOUPLOAD_S3_REGION' => (string) ($config['videoupload_s3_region'] ?? 'us-east-1'),
			'VIDEOUPLOAD_S3_BUCKET' => (string) ($config['videoupload_s3_bucket'] ?? ''),
			'VIDEOUPLOAD_S3_ACCESS_KEY' => (string) ($config['videoupload_s3_access_key'] ?? ''),
			'VIDEOUPLOAD_S3_PATH_PREFIX' => (string) ($config['videoupload_s3_path_prefix'] ?? 'videos'),
			'VIDEOUPLOAD_S3_PUBLIC_BASE_URL' => (string) ($config['videoupload_s3_public_base_url'] ?? ''),
			'VIDEOUPLOAD_S3_USE_PATH_STYLE' => (int) ($config['videoupload_s3_use_path_style'] ?? 0),
			'VIDEOUPLOAD_S3_ACL' => (string) ($config['videoupload_s3_acl'] ?? 'public-read'),
			'S_VIDEOUPLOAD_SECRET_CONFIGURED' => trim((string) ($config['videoupload_s3_secret_key'] ?? '')) !== '',
		]);
	}
}
