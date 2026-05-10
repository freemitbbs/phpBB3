<?php

namespace freemitbbs\disposableemailblocker\service;

class domain_blocklist
{
	protected string $domains_path;
	protected string $allowlist_path;

	public function __construct(string $root_path)
	{
		$extension_path = rtrim($root_path, '/\\') . '/ext/freemitbbs/disposableemailblocker';
		$this->domains_path = $extension_path . '/data/domains.txt';
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

		return $this->file_contains_domain($this->domains_path, $this->domain_candidates($domain));
	}

	protected function is_allowlisted_domain(string $domain): bool
	{
		return $this->file_contains_domain($this->allowlist_path, $this->domain_candidates($domain));
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
}
