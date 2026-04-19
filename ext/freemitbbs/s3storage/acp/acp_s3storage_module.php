<?php

namespace freemitbbs\s3storage\acp;

class acp_s3storage_module
{
	private const FORM_KEY = 'freemitbbs/s3storage';

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

		$language->add_lang('info_acp_s3storage', 'freemitbbs/s3storage');

		$this->tpl_name = 'acp_s3storage';
		$this->page_title = 'ACP_S3STORAGE';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$endpoint = trim((string) $request->variable('s3storage_endpoint', '', true));
			$region = trim((string) $request->variable('s3storage_region', 'us-east-1', true));
			$bucket = trim((string) $request->variable('s3storage_bucket', '', true));
			$access_key = trim((string) $request->variable('s3storage_access_key', '', true));
			$secret_key = trim((string) $request->variable('s3storage_secret_key', '', true));
			$public_base_url = trim((string) $request->variable('s3storage_public_base_url', '', true));
			$use_path_style = (int) $request->variable('s3storage_use_path_style', 0);
			$clear_secret = (int) $request->variable('s3storage_secret_key_clear', 0);

			$config->set('s3storage_endpoint', $endpoint);
			$config->set('s3storage_region', $region !== '' ? $region : 'us-east-1');
			$config->set('s3storage_bucket', $bucket);
			$config->set('s3storage_access_key', $access_key);
			$config->set('s3storage_public_base_url', rtrim($public_base_url, '/'));
			$config->set('s3storage_use_path_style', (string) ($use_path_style ? 1 : 0));

			if ($clear_secret)
			{
				$config->set('s3storage_secret_key', '');
			}
			elseif ($secret_key !== '')
			{
				$config->set('s3storage_secret_key', $secret_key);
			}

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'S3STORAGE_ENDPOINT' => (string) ($config['s3storage_endpoint'] ?? ''),
			'S3STORAGE_REGION' => (string) ($config['s3storage_region'] ?? 'us-east-1'),
			'S3STORAGE_BUCKET' => (string) ($config['s3storage_bucket'] ?? ''),
			'S3STORAGE_ACCESS_KEY' => (string) ($config['s3storage_access_key'] ?? ''),
			'S3STORAGE_PUBLIC_BASE_URL' => (string) ($config['s3storage_public_base_url'] ?? ''),
			'S3STORAGE_USE_PATH_STYLE' => (int) ($config['s3storage_use_path_style'] ?? 0),
			'S_S3STORAGE_SECRET_CONFIGURED' => trim((string) ($config['s3storage_secret_key'] ?? '')) !== '',
		]);
	}
}
