<?php

namespace freemitbbs\s3storage\service;

class config_provider
{
	protected \phpbb\config\config $config;

	public function __construct(\phpbb\config\config $config)
	{
		$this->config = $config;
	}

	public function has_shared_storage_config(): bool
	{
		$config = $this->get_shared_storage_config();

		return trim((string) ($config['endpoint'] ?? '')) !== ''
			&& trim((string) ($config['region'] ?? '')) !== ''
			&& trim((string) ($config['bucket'] ?? '')) !== ''
			&& trim((string) ($config['access_key'] ?? '')) !== ''
			&& trim((string) ($config['secret_key'] ?? '')) !== '';
	}

	public function get_shared_storage_config(): array
	{
		return [
			'endpoint' => (string) ($this->config['s3storage_endpoint'] ?? ''),
			'region' => (string) ($this->config['s3storage_region'] ?? 'us-east-1'),
			'bucket' => (string) ($this->config['s3storage_bucket'] ?? ''),
			'access_key' => (string) ($this->config['s3storage_access_key'] ?? ''),
			'secret_key' => (string) ($this->config['s3storage_secret_key'] ?? ''),
			'public_base_url' => (string) ($this->config['s3storage_public_base_url'] ?? ''),
			'use_path_style' => (int) ($this->config['s3storage_use_path_style'] ?? 0),
		];
	}
}
