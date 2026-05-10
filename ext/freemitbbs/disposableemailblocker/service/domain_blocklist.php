<?php

namespace freemitbbs\disposableemailblocker\service;

class domain_blocklist
{
	protected string $bundled_domains_path;
	protected string $runtime_domains_path;
	protected string $allowlist_path;

	public function __construct(string $root_path)
	{
		$root_path = rtrim($root_path, '/\\');
		$extension_path = rtrim($root_path, '/\\') . '/ext/freemitbbs/disposableemailblocker';
		$this->bundled_domains_path = $extension_path . '/data/domains.txt';
		$this->runtime_domains_path = $root_path . '/store/freemitbbs/disposableemailblocker/domains.txt';
		$this->allowlist_path = $extension_path . '/data/allowlist.txt';
	}

	public function is_disposable_email(string $email): bool
	{
		$domain = $this->email_domain($email);
		return $domain !== '' && $this->is_blocked_domain($domain);
	}

	public function is_blocked_domain(string $domain): bool
	{
		$domain = $this->normalize_domain($domain);
		if ($domain === '' || $this->is_allowlisted_domain($domain))
		{
			return false;
		}

		return $this->file_contains_domain($this->domains_path(), $this->domain_candidates($domain));
	}

	public function refresh_from_url(string $url, int $minimum_domains = 1000, int $timeout_seconds = 20): array
	{
		$directory = dirname($this->runtime_domains_path);
		if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory))
		{
			throw new \RuntimeException('Cannot create disposable email blocklist directory.');
		}

		$temp_path = $directory . '/domains.' . getmypid() . '.' . $this->random_suffix() . '.tmp';

		try
		{
			$this->download_to_path($url, $temp_path, $timeout_seconds);
			$domain_count = $this->count_valid_domains($temp_path);
			if ($domain_count < $minimum_domains)
			{
				throw new \RuntimeException('Downloaded disposable email blocklist is too small.');
			}

			if (!@rename($temp_path, $this->runtime_domains_path))
			{
				throw new \RuntimeException('Cannot replace disposable email blocklist.');
			}
			@chmod($this->runtime_domains_path, 0644);

			return [
				'domains' => $domain_count,
				'path' => $this->runtime_domains_path,
			];
		}
		finally
		{
			if (is_file($temp_path))
			{
				@unlink($temp_path);
			}
		}
	}

	protected function is_allowlisted_domain(string $domain): bool
	{
		return $this->file_contains_domain($this->allowlist_path, $this->domain_candidates($domain));
	}

	protected function domains_path(): string
	{
		return is_file($this->runtime_domains_path) && is_readable($this->runtime_domains_path)
			? $this->runtime_domains_path
			: $this->bundled_domains_path;
	}

	protected function email_domain(string $email): string
	{
		$at = strrpos($email, '@');
		if ($at === false)
		{
			return '';
		}

		return $this->normalize_domain(substr($email, $at + 1));
	}

	protected function normalize_domain(string $domain): string
	{
		$domain = strtolower(trim($domain));
		$domain = trim($domain, " \t\n\r\0\x0B.");
		if ($domain === '')
		{
			return '';
		}

		if (function_exists('idn_to_ascii'))
		{
			$ascii = defined('INTL_IDNA_VARIANT_UTS46')
				? idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
				: idn_to_ascii($domain);
			if (is_string($ascii) && $ascii !== '')
			{
				$domain = strtolower($ascii);
			}
		}

		return $domain;
	}

	protected function domain_candidates(string $domain): array
	{
		$parts = array_values(array_filter(explode('.', $domain), static fn($part) => $part !== ''));
		$count = count($parts);
		if ($count < 2)
		{
			return [];
		}

		$candidates = [];
		for ($index = 0; $index < $count - 1; $index++)
		{
			$candidates[] = implode('.', array_slice($parts, $index));
		}

		return $candidates;
	}

	protected function file_contains_domain(string $path, array $candidates): bool
	{
		if (empty($candidates))
		{
			return false;
		}

		if (!is_file($path) || !is_readable($path))
		{
			return false;
		}

		$candidate_map = array_fill_keys($candidates, true);
		$handle = fopen($path, 'rb');
		if ($handle === false)
		{
			return false;
		}

		try
		{
			while (($line = fgets($handle)) !== false)
			{
				$domain = $this->parse_domain_line($line);
				if ($domain !== '' && isset($candidate_map[$domain]))
				{
					return true;
				}
			}
		}
		finally
		{
			fclose($handle);
		}

		return false;
	}

	protected function parse_domain_line(string $line): string
	{
		$domain = strtolower(trim($line));
		if ($domain === '' || str_starts_with($domain, '#') || str_starts_with($domain, '//'))
		{
			return '';
		}

		$domain = ltrim($domain, '@');
		if (str_starts_with($domain, '*.'))
		{
			$domain = substr($domain, 2);
		}

		$domain = trim($domain, " \t\n\r\0\x0B.");
		return preg_match('/^[a-z0-9.-]+\.[a-z0-9-]+$/', $domain) ? $domain : '';
	}

	protected function download_to_path(string $url, string $path, int $timeout_seconds): void
	{
		$handle = @fopen($path, 'wb');
		if ($handle === false)
		{
			throw new \RuntimeException('Cannot open disposable email blocklist download target.');
		}

		try
		{
			if (function_exists('curl_init'))
			{
				$this->download_with_curl($url, $handle, $timeout_seconds);
				return;
			}

			$this->download_with_streams($url, $handle, $timeout_seconds);
		}
		finally
		{
			fclose($handle);
		}
	}

	protected function download_with_curl(string $url, $handle, int $timeout_seconds): void
	{
		$curl = curl_init($url);
		if ($curl === false)
		{
			throw new \RuntimeException('Cannot initialize disposable email blocklist download.');
		}

		curl_setopt($curl, CURLOPT_FILE, $handle);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_FAILONERROR, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(5, $timeout_seconds));
		curl_setopt($curl, CURLOPT_TIMEOUT, $timeout_seconds);
		curl_setopt($curl, CURLOPT_USERAGENT, 'freemitbbs-disposableemailblocker/1.0');

		$result = curl_exec($curl);
		$error = curl_error($curl);
		$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

		if ($result === false)
		{
			throw new \RuntimeException('Cannot download disposable email blocklist: ' . ($error !== '' ? $error : 'HTTP ' . $status));
		}
	}

	protected function download_with_streams(string $url, $handle, int $timeout_seconds): void
	{
		$context = stream_context_create([
			'http' => [
				'header' => "Accept: text/plain\r\nUser-Agent: freemitbbs-disposableemailblocker/1.0\r\n",
				'ignore_errors' => false,
				'timeout' => $timeout_seconds,
			],
		]);

		$source = @fopen($url, 'rb', false, $context);
		if ($source === false)
		{
			throw new \RuntimeException('Cannot download disposable email blocklist.');
		}

		try
		{
			if (@stream_copy_to_stream($source, $handle) === false)
			{
				throw new \RuntimeException('Cannot write disposable email blocklist download.');
			}
		}
		finally
		{
			fclose($source);
		}
	}

	protected function count_valid_domains(string $path): int
	{
		$count = 0;
		$handle = fopen($path, 'rb');
		if ($handle === false)
		{
			return 0;
		}

		try
		{
			while (($line = fgets($handle)) !== false)
			{
				if ($this->parse_domain_line($line) !== '')
				{
					$count++;
				}
			}
		}
		finally
		{
			fclose($handle);
		}

		return $count;
	}

	protected function random_suffix(): string
	{
		try
		{
			return bin2hex(random_bytes(8));
		}
		catch (\Exception $e)
		{
			return str_replace('.', '', uniqid('', true));
		}
	}
}
