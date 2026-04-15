<?php

namespace freemitbbs\videoupload\service;

class s3_uploader
{
	private const SERVICE = 's3';
	private const ALLOWED_EXTENSIONS = ['mp4', 'ogg', 'webm', 'weba', 'mp3', 'm4a', 'aac', 'wav', 'oga', 'opus', 'flac'];
	private const CONTENT_TYPES = [
		'mp4' => 'video/mp4',
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

	public function __construct(\phpbb\config\config $config)
	{
		$this->config = $config;
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

		if (!function_exists('curl_init'))
		{
			throw new \RuntimeException('Upload failed: cURL extension is not enabled.');
		}

		$endpoint = rtrim(trim((string) ($this->config['videoupload_s3_endpoint'] ?? '')), '/');
		$region = trim((string) ($this->config['videoupload_s3_region'] ?? ''));
		$bucket = trim((string) ($this->config['videoupload_s3_bucket'] ?? ''));
		$access_key = trim((string) ($this->config['videoupload_s3_access_key'] ?? ''));
		$secret_key = trim((string) ($this->config['videoupload_s3_secret_key'] ?? ''));
		$acl = trim((string) ($this->config['videoupload_s3_acl'] ?? ''));
		$use_path_style = (bool) ((int) ($this->config['videoupload_s3_use_path_style'] ?? 0));

		if ($endpoint === '' || $region === '' || $bucket === '' || $access_key === '' || $secret_key === '')
		{
			throw new \RuntimeException('Upload failed: S3 configuration is incomplete.');
		}

		$endpoint_parts = parse_url($endpoint);
		if (!is_array($endpoint_parts) || empty($endpoint_parts['scheme']) || empty($endpoint_parts['host']))
		{
			throw new \RuntimeException('Upload failed: invalid S3 endpoint.');
		}

		$scheme = strtolower((string) $endpoint_parts['scheme']);
		$endpoint_host = (string) $endpoint_parts['host'];
		$endpoint_port = isset($endpoint_parts['port']) ? (':' . (int) $endpoint_parts['port']) : '';
		$endpoint_base_path = trim((string) ($endpoint_parts['path'] ?? ''), '/');
		$endpoint_base_path = ($endpoint_base_path !== '') ? '/' . $this->encode_path($endpoint_base_path) : '';
		$bucket_segment = rawurlencode($bucket);
		$key_segment = $this->encode_path(ltrim($object_key, '/'));

		if ($use_path_style)
		{
			$request_host = $endpoint_host . $endpoint_port;
			$canonical_uri = $endpoint_base_path . '/' . $bucket_segment . '/' . $key_segment;
		}
		else
		{
			$request_host = $bucket . '.' . $endpoint_host . $endpoint_port;
			$canonical_uri = $endpoint_base_path . '/' . $key_segment;
		}
		$canonical_uri = '/' . ltrim($canonical_uri, '/');
		$request_url = $scheme . '://' . $request_host . $canonical_uri;

		$amz_date = gmdate('Ymd\THis\Z');
		$date_stamp = gmdate('Ymd');
		$content_type = self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';
		$payload_hash = hash_file('sha256', $tmp_path);
		$credential_scope = $date_stamp . '/' . $region . '/' . self::SERVICE . '/aws4_request';

		$canonical_headers = [
			'content-type' => $content_type,
			'host' => $request_host,
			'x-amz-content-sha256' => $payload_hash,
			'x-amz-date' => $amz_date,
		];
		if ($acl !== '')
		{
			$canonical_headers['x-amz-acl'] = $acl;
		}
		ksort($canonical_headers);

		$canonical_headers_string = '';
		$signed_header_names = [];
		foreach ($canonical_headers as $name => $value)
		{
			$canonical_headers_string .= $name . ':' . trim((string) $value) . "\n";
			$signed_header_names[] = $name;
		}
		$signed_headers = implode(';', $signed_header_names);

		$canonical_request = "PUT\n"
			. $canonical_uri . "\n"
			. "\n"
			. $canonical_headers_string . "\n"
			. $signed_headers . "\n"
			. $payload_hash;

		$string_to_sign = "AWS4-HMAC-SHA256\n"
			. $amz_date . "\n"
			. $credential_scope . "\n"
			. hash('sha256', $canonical_request);

		$signing_key = $this->derive_signing_key($secret_key, $date_stamp, $region, self::SERVICE);
		$signature = hash_hmac('sha256', $string_to_sign, $signing_key);
		$authorization = 'AWS4-HMAC-SHA256 '
			. 'Credential=' . $access_key . '/' . $credential_scope . ', '
			. 'SignedHeaders=' . $signed_headers . ', '
			. 'Signature=' . $signature;

		$request_headers = [
			'Authorization: ' . $authorization,
			'Content-Type: ' . $content_type,
			'Host: ' . $request_host,
			'X-Amz-Content-Sha256: ' . $payload_hash,
			'X-Amz-Date: ' . $amz_date,
		];
		if ($acl !== '')
		{
			$request_headers[] = 'X-Amz-Acl: ' . $acl;
		}

		$file_handle = @fopen($tmp_path, 'rb');
		if ($file_handle === false)
		{
			throw new \RuntimeException('Upload failed: cannot read uploaded file.');
		}

		$curl = curl_init($request_url);
		curl_setopt($curl, CURLOPT_UPLOAD, true);
		curl_setopt($curl, CURLOPT_INFILE, $file_handle);
		curl_setopt($curl, CURLOPT_INFILESIZE, $size);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($curl, CURLOPT_TIMEOUT, 120);

		$response = curl_exec($curl);
		if ($response === false)
		{
			$error = curl_error($curl);
			curl_close($curl);
			fclose($file_handle);
			throw new \RuntimeException('Upload failed: ' . $error);
		}

		$status_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$header_size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$response_body = trim((string) substr((string) $response, $header_size));

		curl_close($curl);
		fclose($file_handle);

		if (!in_array($status_code, [200, 201, 204], true))
		{
			$details = $response_body !== '' ? (' - ' . substr($response_body, 0, 300)) : '';
			throw new \RuntimeException('Upload failed with HTTP status ' . $status_code . $details);
		}

		return $this->build_public_url(
			$endpoint_parts,
			$bucket,
			$object_key,
			$use_path_style
		);
	}

	protected function build_public_url(
		array $endpoint_parts,
		string $bucket,
		string $object_key,
		bool $use_path_style
	): string
	{
		$public_base_url = rtrim(trim((string) ($this->config['videoupload_s3_public_base_url'] ?? '')), '/');
		$key_segment = $this->encode_path(ltrim($object_key, '/'));
		if ($public_base_url !== '')
		{
			return $public_base_url . '/' . $key_segment;
		}

		$scheme = strtolower((string) ($endpoint_parts['scheme'] ?? 'https'));
		$host = (string) ($endpoint_parts['host'] ?? '');
		$port = isset($endpoint_parts['port']) ? (':' . (int) $endpoint_parts['port']) : '';
		$base_path = trim((string) ($endpoint_parts['path'] ?? ''), '/');
		$base_path = ($base_path !== '') ? '/' . $this->encode_path($base_path) : '';
		$bucket_segment = rawurlencode($bucket);

		if ($use_path_style)
		{
			$path = '/' . ltrim($base_path . '/' . $bucket_segment . '/' . $key_segment, '/');
			return $scheme . '://' . $host . $port . $path;
		}

		$path = '/' . ltrim($base_path . '/' . $key_segment, '/');
		return $scheme . '://' . $bucket . '.' . $host . $port . $path;
	}

	protected function encode_path(string $path): string
	{
		$segments = explode('/', trim($path, '/'));
		$segments = array_map(static function ($segment)
		{
			return rawurlencode($segment);
		}, $segments);

		return implode('/', $segments);
	}

	protected function derive_signing_key(string $secret, string $date, string $region, string $service): string
	{
		$date_key = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
		$region_key = hash_hmac('sha256', $region, $date_key, true);
		$service_key = hash_hmac('sha256', $service, $region_key, true);

		return hash_hmac('sha256', 'aws4_request', $service_key, true);
	}
}
