<?php

namespace freemitbbs\videoupload\service;

use Symfony\Component\DependencyInjection\ContainerInterface;

class s3_uploader
{
	private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'ogg', 'webm', 'weba', 'mp3', 'm4a', 'aac', 'wav', 'oga', 'opus', 'flac'];
	private const CONTENT_TYPES = [
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png' => 'image/png',
		'gif' => 'image/gif',
		'webp' => 'image/webp',
		'mp4' => 'video/mp4',
		'mov' => 'video/quicktime',
		'ogg' => 'video/ogg',
		'webm' => 'video/webm',
		'weba' => 'audio/webm',
		'mp3' => 'audio/mpeg',
		'm4a' => 'audio/mp4',
		'aac' => 'audio/aac',
		'wav' => 'audio/wav',
		'oga' => 'audio/ogg',
		'opus' => 'audio/opus',
		'flac' => 'audio/flac',
	];

	protected \phpbb\config\config $config;
	protected $object_store;
	protected $shared_config_provider;

	public function __construct(\phpbb\config\config $config, ContainerInterface $container)
	{
		$this->config = $config;
		$this->shared_config_provider = $container->has('freemitbbs.s3storage.config_provider')
			? $container->get('freemitbbs.s3storage.config_provider')
			: null;

		if ($container->has('freemitbbs.s3storage.object_store'))
		{
			$this->object_store = $container->get('freemitbbs.s3storage.object_store');
		}
		else
		{
			$this->object_store = new s3_object_store();
		}
	}

	public function upload(string $tmp_path, int $size, string $extension, string $object_key): string
	{
		$extension = strtolower($extension);
		if (!in_array($extension, self::ALLOWED_EXTENSIONS, true))
		{
			$allowed_extensions = implode(', ', array_map(static function ($ext)
			{
				return '.' . $ext;
			}, self::ALLOWED_EXTENSIONS));
			throw new \RuntimeException('Only ' . $allowed_extensions . ' uploads are supported.');
		}

		$content_type = self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';

		return $this->object_store->put_object(
			$this->get_storage_config(),
			$tmp_path,
			$size,
			$content_type,
			$object_key
		);
	}

	public function has_storage_config(): bool
	{
		return $this->object_store->is_configured($this->get_storage_config());
	}

	protected function get_storage_config(): array
	{
		if ($this->shared_config_provider && $this->shared_config_provider->has_shared_storage_config())
		{
			$shared_config = $this->shared_config_provider->get_shared_storage_config();
			$shared_config['acl'] = (string) ($this->config['videoupload_s3_acl'] ?? 'public-read');

			return $shared_config;
		}

		return [
			'endpoint' => (string) ($this->config['videoupload_s3_endpoint'] ?? ''),
			'region' => (string) ($this->config['videoupload_s3_region'] ?? ''),
			'bucket' => (string) ($this->config['videoupload_s3_bucket'] ?? ''),
			'access_key' => (string) ($this->config['videoupload_s3_access_key'] ?? ''),
			'secret_key' => (string) ($this->config['videoupload_s3_secret_key'] ?? ''),
			'public_base_url' => (string) ($this->config['videoupload_s3_public_base_url'] ?? ''),
			'use_path_style' => (int) ($this->config['videoupload_s3_use_path_style'] ?? 0),
			'acl' => (string) ($this->config['videoupload_s3_acl'] ?? ''),
		];
	}
}
