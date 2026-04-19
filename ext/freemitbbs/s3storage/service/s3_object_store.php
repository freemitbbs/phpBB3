<?php

namespace freemitbbs\s3storage\service;

class s3_object_store
{
	private const SERVICE = 's3';

	public function is_configured(array $storage_config): bool
	{
		return trim((string) ($storage_config['endpoint'] ?? '')) !== ''
			&& trim((string) ($storage_config['region'] ?? '')) !== ''
			&& trim((string) ($storage_config['bucket'] ?? '')) !== ''
			&& trim((string) ($storage_config['access_key'] ?? '')) !== ''
			&& trim((string) ($storage_config['secret_key'] ?? '')) !== '';
	}

	public function put_object(
		array $storage_config,
		string $tmp_path,
		int $size,
		string $content_type,
		string $object_key
	): string
	{
		if (!function_exists('curl_init'))
		{
			throw new \RuntimeException('Upload failed: cURL extension is not enabled.');
		}

		if (!is_file($tmp_path) || !is_readable($tmp_path))
		{
			throw new \RuntimeException('Upload failed: cannot read uploaded file.');
		}

		$config = $this->normalize_config($storage_config);
		$request = $this->build_request($config, ltrim($object_key, '/'));
		$payload_hash = hash_file('sha256', $tmp_path);
		$headers = $this->build_signed_headers(
			$config,
			$request['host'],
			$request['canonical_uri'],
			$payload_hash,
			$content_type,
			$config['acl']
		);

		$file_handle = @fopen($tmp_path, 'rb');
		if ($file_handle === false)
		{
			throw new \RuntimeException('Upload failed: cannot read uploaded file.');
		}

		$curl = curl_init($request['url']);
		curl_setopt($curl, CURLOPT_UPLOAD, true);
		curl_setopt($curl, CURLOPT_INFILE, $file_handle);
		curl_setopt($curl, CURLOPT_INFILESIZE, $size);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
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

		return $this->build_public_url($config, ltrim($object_key, '/'));
	}

	public function delete_object(array $storage_config, string $object_key): void
	{
		if (!function_exists('curl_init'))
		{
			throw new \RuntimeException('Delete failed: cURL extension is not enabled.');
		}

		$config = $this->normalize_config($storage_config);
		$request = $this->build_request($config, ltrim($object_key, '/'));
		$payload_hash = hash('sha256', '');
		$headers = $this->build_signed_headers(
			$config,
			$request['host'],
			$request['canonical_uri'],
			$payload_hash,
			'',
			''
		);

		$curl = curl_init($request['url']);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);

		$response = curl_exec($curl);
		if ($response === false)
		{
			$error = curl_error($curl);
			curl_close($curl);
			throw new \RuntimeException('Delete failed: ' . $error);
		}

		$status_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$header_size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$response_body = trim((string) substr((string) $response, $header_size));
		curl_close($curl);

		if (!in_array($status_code, [200, 202, 204, 404], true))
		{
			$details = $response_body !== '' ? (' - ' . substr($response_body, 0, 300)) : '';
			throw new \RuntimeException('Delete failed with HTTP status ' . $status_code . $details);
		}
	}

	public function build_public_url(array $storage_config, string $object_key): string
	{
		$config = $this->normalize_config($storage_config);
		$key_segment = $this->encode_path(ltrim($object_key, '/'));

		if ($config['public_base_url'] !== '')
		{
			return $config['public_base_url'] . '/' . $key_segment;
		}

		$endpoint_parts = parse_url($config['endpoint']);
		if (!is_array($endpoint_parts))
		{
			throw new \RuntimeException('Invalid S3 endpoint.');
		}

		$scheme = strtolower((string) ($endpoint_parts['scheme'] ?? 'https'));
		$host = (string) ($endpoint_parts['host'] ?? '');
		$port = isset($endpoint_parts['port']) ? (':' . (int) $endpoint_parts['port']) : '';
		$base_path = trim((string) ($endpoint_parts['path'] ?? ''), '/');
		$base_path = ($base_path !== '') ? '/' . $this->encode_path($base_path) : '';
		$bucket_segment = rawurlencode($config['bucket']);

		if ($config['use_path_style'])
		{
			$path = '/' . ltrim($base_path . '/' . $bucket_segment . '/' . $key_segment, '/');
			return $scheme . '://' . $host . $port . $path;
		}

		$path = '/' . ltrim($base_path . '/' . $key_segment, '/');
		return $scheme . '://' . $config['bucket'] . '.' . $host . $port . $path;
	}

	public function create_presigned_get_url(
		array $storage_config,
		string $object_key,
		int $expires_in = 300,
		array $response_headers = []
	): string
	{
		$config = $this->normalize_config($storage_config);
		$request = $this->build_request($config, ltrim($object_key, '/'));
		$expires_in = max(1, min(604800, $expires_in));
		$amz_date = gmdate('Ymd\THis\Z');
		$date_stamp = gmdate('Ymd');
		$credential_scope = $date_stamp . '/' . $config['region'] . '/' . self::SERVICE . '/aws4_request';

		$query_params = [
			'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
			'X-Amz-Credential' => $config['access_key'] . '/' . $credential_scope,
			'X-Amz-Date' => $amz_date,
			'X-Amz-Expires' => (string) $expires_in,
			'X-Amz-SignedHeaders' => 'host',
		];

		foreach ($response_headers as $name => $value)
		{
			$value = trim((string) $value);
			if ($value === '')
			{
				continue;
			}

			$query_params[strtolower((string) $name)] = $value;
		}

		$canonical_query = $this->build_canonical_query_string($query_params);
		$canonical_request = "GET\n"
			. $request['canonical_uri'] . "\n"
			. $canonical_query . "\n"
			. 'host:' . $request['host'] . "\n\n"
			. "host\n"
			. 'UNSIGNED-PAYLOAD';

		$string_to_sign = "AWS4-HMAC-SHA256\n"
			. $amz_date . "\n"
			. $credential_scope . "\n"
			. hash('sha256', $canonical_request);

		$signing_key = $this->derive_signing_key($config['secret_key'], $date_stamp, $config['region'], self::SERVICE);
		$query_params['X-Amz-Signature'] = hash_hmac('sha256', $string_to_sign, $signing_key);

		return $request['url'] . '?' . $this->build_canonical_query_string($query_params);
	}

	private function normalize_config(array $storage_config): array
	{
		$config = [
			'endpoint' => rtrim(trim((string) ($storage_config['endpoint'] ?? '')), '/'),
			'region' => trim((string) ($storage_config['region'] ?? '')),
			'bucket' => trim((string) ($storage_config['bucket'] ?? '')),
			'access_key' => trim((string) ($storage_config['access_key'] ?? '')),
			'secret_key' => trim((string) ($storage_config['secret_key'] ?? '')),
			'public_base_url' => rtrim(trim((string) ($storage_config['public_base_url'] ?? '')), '/'),
			'acl' => trim((string) ($storage_config['acl'] ?? '')),
			'use_path_style' => !empty($storage_config['use_path_style']),
		];
		if (strtolower($config['acl']) === 'private')
		{
			// S3-compatible providers such as Backblaze B2 may reject the
			// explicit "private" canned ACL. Omitting x-amz-acl keeps the
			// provider's default private behavior and works with signed URLs.
			$config['acl'] = '';
		}

		if (
			$config['endpoint'] === ''
			|| $config['region'] === ''
			|| $config['bucket'] === ''
			|| $config['access_key'] === ''
			|| $config['secret_key'] === ''
		)
		{
			throw new \RuntimeException('S3 configuration is incomplete.');
		}

		return $config;
	}

	private function build_request(array $config, string $object_key): array
	{
		$endpoint_parts = parse_url($config['endpoint']);
		if (!is_array($endpoint_parts) || empty($endpoint_parts['scheme']) || empty($endpoint_parts['host']))
		{
			throw new \RuntimeException('Invalid S3 endpoint.');
		}

		$scheme = strtolower((string) $endpoint_parts['scheme']);
		$endpoint_host = (string) $endpoint_parts['host'];
		$endpoint_port = isset($endpoint_parts['port']) ? (':' . (int) $endpoint_parts['port']) : '';
		$endpoint_base_path = trim((string) ($endpoint_parts['path'] ?? ''), '/');
		$endpoint_base_path = ($endpoint_base_path !== '') ? '/' . $this->encode_path($endpoint_base_path) : '';
		$bucket_segment = rawurlencode($config['bucket']);
		$key_segment = $this->encode_path($object_key);

		if ($config['use_path_style'])
		{
			$request_host = $endpoint_host . $endpoint_port;
			$canonical_uri = $endpoint_base_path . '/' . $bucket_segment . '/' . $key_segment;
		}
		else
		{
			$request_host = $config['bucket'] . '.' . $endpoint_host . $endpoint_port;
			$canonical_uri = $endpoint_base_path . '/' . $key_segment;
		}

		$canonical_uri = '/' . ltrim($canonical_uri, '/');

		return [
			'url' => $scheme . '://' . $request_host . $canonical_uri,
			'host' => $request_host,
			'canonical_uri' => $canonical_uri,
		];
	}

	private function build_signed_headers(
		array $config,
		string $request_host,
		string $canonical_uri,
		string $payload_hash,
		string $content_type,
		string $acl
	): array
	{
		$amz_date = gmdate('Ymd\THis\Z');
		$date_stamp = gmdate('Ymd');
		$credential_scope = $date_stamp . '/' . $config['region'] . '/' . self::SERVICE . '/aws4_request';

		$canonical_headers = [
			'host' => $request_host,
			'x-amz-content-sha256' => $payload_hash,
			'x-amz-date' => $amz_date,
		];

		if ($content_type !== '')
		{
			$canonical_headers['content-type'] = $content_type;
		}

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

		$method = ($content_type !== '') ? 'PUT' : 'DELETE';
		$canonical_request = $method . "\n"
			. $canonical_uri . "\n"
			. "\n"
			. $canonical_headers_string . "\n"
			. $signed_headers . "\n"
			. $payload_hash;

		$string_to_sign = "AWS4-HMAC-SHA256\n"
			. $amz_date . "\n"
			. $credential_scope . "\n"
			. hash('sha256', $canonical_request);

		$signing_key = $this->derive_signing_key($config['secret_key'], $date_stamp, $config['region'], self::SERVICE);
		$signature = hash_hmac('sha256', $string_to_sign, $signing_key);
		$authorization = 'AWS4-HMAC-SHA256 '
			. 'Credential=' . $config['access_key'] . '/' . $credential_scope . ', '
			. 'SignedHeaders=' . $signed_headers . ', '
			. 'Signature=' . $signature;

		$request_headers = [
			'Authorization: ' . $authorization,
			'Host: ' . $request_host,
			'X-Amz-Content-Sha256: ' . $payload_hash,
			'X-Amz-Date: ' . $amz_date,
		];

		if ($content_type !== '')
		{
			$request_headers[] = 'Content-Type: ' . $content_type;
		}

		if ($acl !== '')
		{
			$request_headers[] = 'X-Amz-Acl: ' . $acl;
		}

		return $request_headers;
	}

	private function encode_path(string $path): string
	{
		$segments = explode('/', trim($path, '/'));
		$segments = array_map(static function ($segment)
		{
			return rawurlencode($segment);
		}, $segments);

		return implode('/', $segments);
	}

	private function derive_signing_key(string $secret, string $date, string $region, string $service): string
	{
		$date_key = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
		$region_key = hash_hmac('sha256', $region, $date_key, true);
		$service_key = hash_hmac('sha256', $service, $region_key, true);

		return hash_hmac('sha256', 'aws4_request', $service_key, true);
	}

	private function build_canonical_query_string(array $query_params): string
	{
		$encoded = [];
		foreach ($query_params as $name => $value)
		{
			$encoded[] = [
				rawurlencode((string) $name),
				rawurlencode((string) $value),
			];
		}

		usort($encoded, static function (array $left, array $right): int
		{
			if ($left[0] === $right[0])
			{
				return $left[1] <=> $right[1];
			}

			return $left[0] <=> $right[0];
		});

		$pairs = [];
		foreach ($encoded as $pair)
		{
			$pairs[] = $pair[0] . '=' . $pair[1];
		}

		return implode('&', $pairs);
	}
}
