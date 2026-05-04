<?php

namespace freemitbbs\cardgamesauth\service;

class token_issuer
{
	private const DEFAULT_ISSUER = 'freemitbbs-cardgamesauth';
	private const DEFAULT_AUDIENCE = 'freemitbbs-cardgames-server';

	protected \phpbb\config\config $config;
	protected \phpbb\user $user;

	public function __construct(\phpbb\config\config $config, \phpbb\user $user)
	{
		$this->config = $config;
		$this->user = $user;
	}

	public function issue(array $user_data, array $extra_claims = []): array
	{
		$now = time();
		$ttl = max(30, min(600, (int) ($this->config['cardgamesauth_token_ttl'] ?? 120)));
		$expires_at = $now + $ttl;

		$payload = array_merge([
			'iss' => $this->issuer(),
			'aud' => $this->audience(),
			'iat' => $now,
			'nbf' => $now - 5,
			'exp' => $expires_at,
			'jti' => $this->jti(),
			'sid_hash' => hash('sha256', (string) ($this->user->session_id ?? '')),
			'user_id' => (int) ($user_data['user_id'] ?? 0),
			'username' => (string) ($user_data['username'] ?? ''),
			'username_clean' => (string) ($user_data['username_clean'] ?? ''),
			'display_name' => (string) ($user_data['display_name'] ?? ($user_data['username'] ?? '')),
			'avatar_url' => (string) ($user_data['avatar_url'] ?? ''),
			'groups' => array_values($user_data['groups'] ?? []),
			'permissions' => array_values($user_data['permissions'] ?? []),
		], $extra_claims);

		$token = $this->encode([
			'alg' => 'HS256',
			'typ' => 'JWT',
		], $payload);

		return [
			'token' => $token,
			'expires_at' => gmdate('c', $expires_at),
			'expires_in' => $ttl,
			'jti' => $payload['jti'],
		];
	}

	public function issuer(): string
	{
		$issuer = trim((string) ($this->config['cardgamesauth_token_issuer'] ?? ''));
		return $issuer !== '' ? $issuer : self::DEFAULT_ISSUER;
	}

	public function audience(): string
	{
		$audience = trim((string) ($this->config['cardgamesauth_token_audience'] ?? ''));
		return $audience !== '' ? $audience : self::DEFAULT_AUDIENCE;
	}

	protected function encode(array $header, array $payload): string
	{
		$secret = $this->secret();
		$encoded_header = $this->base64url(json_encode($header, JSON_UNESCAPED_SLASHES));
		$encoded_payload = $this->base64url(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		$signature = hash_hmac('sha256', $encoded_header . '.' . $encoded_payload, $secret, true);

		return $encoded_header . '.' . $encoded_payload . '.' . $this->base64url($signature);
	}

	protected function secret(): string
	{
		$secret = trim((string) ($this->config['cardgamesauth_token_secret'] ?? ''));
		if ($secret === '')
		{
			$secret = $this->random_hex(32);
			$this->config->set('cardgamesauth_token_secret', $secret);
		}

		return $secret;
	}

	protected function jti(): string
	{
		return $this->random_hex(16);
	}

	protected function random_hex(int $bytes): string
	{
		try
		{
			return bin2hex(random_bytes($bytes));
		}
		catch (\Exception $e)
		{
			return sha1(uniqid((string) mt_rand(), true) . microtime(true));
		}
	}

	protected function base64url(string $value): string
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}
