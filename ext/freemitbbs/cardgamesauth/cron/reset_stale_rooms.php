<?php

namespace freemitbbs\cardgamesauth\cron;

class reset_stale_rooms extends \phpbb\cron\task\base
{
	private const LAST_RUN_CACHE_KEY = '_freemitbbs_cardgamesauth_reset_stale_rooms_last_run';
	private const NO_READY_CACHE_PREFIX = '_freemitbbs_cardgamesauth_no_ready_since_';
	private const CHECK_INTERVAL_SECONDS = 60;
	private const NO_READY_SECONDS = 600;
	private const ACTIVE_SESSION_STATUSES = ['starting', 'playing', 'finished'];

	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected string $sessions_table;

	public function __construct(
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		string $sessions_table
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->sessions_table = $sessions_table;
	}

	public function run()
	{
		$this->cache->put(self::LAST_RUN_CACHE_KEY, time(), self::CHECK_INTERVAL_SECONDS * 2);
		if (!$this->runtime_configured())
		{
			return;
		}

		try
		{
			$status = $this->runtime_request('GET', '/card-games/runtime/status');
		}
		catch (\RuntimeException $e)
		{
			return;
		}

		$rooms = $status['body']['status']['rooms'] ?? [];
		if (!is_array($rooms))
		{
			return;
		}

		$now = time();
		foreach ($rooms as $room)
		{
			if (!is_array($room))
			{
				continue;
			}

			$room_key = (string) ($room['roomKey'] ?? $room['room_key'] ?? '');
			if ($room_key === '')
			{
				continue;
			}

			if ($this->has_ready_seated_player($room) || !$this->should_track_no_ready_room($room))
			{
				$this->clear_no_ready_since($room_key);
				continue;
			}

			$no_ready_since = $this->no_ready_since($room_key);
			if ($no_ready_since <= 0)
			{
				$this->set_no_ready_since($room_key, $now);
				continue;
			}
			if ($no_ready_since > ($now - self::NO_READY_SECONDS))
			{
				continue;
			}

			try
			{
				$request_id = $this->request_id($room_key, (int) ($room['stateVersion'] ?? $room['state_version'] ?? 0), $now);
				$this->reset_no_ready_room($room, $room_key, $request_id);
				$this->clear_no_ready_since($room_key);
			}
			catch (\RuntimeException $e)
			{
				continue;
			}
		}
	}

	public function is_runnable()
	{
		return $this->runtime_configured();
	}

	public function should_run()
	{
		if (!$this->is_runnable())
		{
			return false;
		}

		$last_run = (int) $this->cache->get(self::LAST_RUN_CACHE_KEY);

		return $last_run <= (time() - self::CHECK_INTERVAL_SECONDS);
	}

	protected function runtime_request(string $method, string $path, array $payload = []): array
	{
		$method = strtoupper($method);
		$url = $this->runtime_base_url() . $path;
		$body = $method === 'GET' ? '' : $this->encode_json($payload);
		$response = $this->http_request($method, $url, $body, $this->runtime_headers($method, $url, $body));
		if ($response['status'] < 200 || $response['status'] >= 300)
		{
			throw new \RuntimeException('runtime_status_' . $response['status']);
		}

		$data = json_decode($response['body'], true);
		if (!is_array($data))
		{
			throw new \RuntimeException('runtime_invalid_json');
		}

		return [
			'status' => $response['status'],
			'body' => $data,
		];
	}

	protected function http_request(string $method, string $url, string $body, array $headers): array
	{
		$timeout_ms = $this->runtime_timeout_ms();
		$timeout_seconds = (int) ceil($timeout_ms / 1000);
		if (function_exists('curl_init'))
		{
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			if ($method !== 'GET')
			{
				curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
			}
			if (defined('CURLOPT_TIMEOUT_MS'))
			{
				curl_setopt($curl, CURLOPT_TIMEOUT_MS, $timeout_ms);
			}
			else
			{
				curl_setopt($curl, CURLOPT_TIMEOUT, $timeout_seconds);
			}

			$response_body = curl_exec($curl);
			$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
			$error = curl_error($curl);
			curl_close($curl);
			if ($response_body === false)
			{
				throw new \RuntimeException('runtime_unreachable_' . $error);
			}

			return [
				'status' => $status,
				'body' => (string) $response_body,
			];
		}

		$options = [
			'http' => [
				'method' => $method,
				'header' => implode("\r\n", $headers),
				'timeout' => $timeout_seconds,
				'ignore_errors' => true,
			],
		];
		if ($method !== 'GET')
		{
			$options['http']['content'] = $body;
		}

		$response_body = file_get_contents($url, false, stream_context_create($options));
		if ($response_body === false)
		{
			throw new \RuntimeException('runtime_unreachable');
		}

		$status = 0;
		foreach (($http_response_header ?? []) as $header)
		{
			if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $match))
			{
				$status = (int) $match[1];
				break;
			}
		}

		return [
			'status' => $status,
			'body' => (string) $response_body,
		];
	}

	protected function runtime_headers(string $method, string $url, string $body): array
	{
		$timestamp = (string) time();
		$nonce = $this->nonce();
		$body_hash = hash('sha256', $body);
		$path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
		$query = (string) (parse_url($url, PHP_URL_QUERY) ?: '');
		$signature_path = $path . ($query !== '' ? '?' . $query : '');
		$signature = hash_hmac('sha256', implode("\n", [
			$method,
			$signature_path,
			$timestamp,
			$nonce,
			$body_hash,
		]), $this->runtime_service_secret());

		return [
			'accept: application/json',
			'content-type: application/json',
			'x-cardgames-service: ' . $this->runtime_service_id(),
			'x-cardgames-timestamp: ' . $timestamp,
			'x-cardgames-nonce: ' . $nonce,
			'x-cardgames-content-sha256: ' . $body_hash,
			'x-cardgames-signature: sha256=' . $signature,
		];
	}

	protected function runtime_configured(): bool
	{
		return $this->runtime_enabled()
			&& $this->runtime_base_url() !== ''
			&& $this->runtime_service_secret() !== '';
	}

	protected function runtime_enabled(): bool
	{
		return (int) ($this->config['cardgames_node_runtime_enabled'] ?? 0) === 1;
	}

	protected function runtime_base_url(): string
	{
		return rtrim(trim((string) ($this->config['cardgames_node_runtime_base_url'] ?? '')), '/');
	}

	protected function runtime_service_id(): string
	{
		$service_id = trim((string) ($this->config['cardgames_node_runtime_service_id'] ?? 'phpbb-cardgamesauth'));

		return $service_id !== '' ? $service_id : 'phpbb-cardgamesauth';
	}

	protected function runtime_service_secret(): string
	{
		return trim((string) ($this->config['cardgames_node_runtime_service_secret'] ?? ''));
	}

	protected function runtime_timeout_ms(): int
	{
		$timeout_ms = (int) ($this->config['cardgames_node_runtime_timeout_ms'] ?? 10000);

		return max(1000, min(30000, $timeout_ms));
	}

	protected function request_id(string $room_key, int $state_version, int $now): string
	{
		return 'phpbb-cron-reset-' . $now . '-' . substr(hash('sha256', $room_key . ':' . $state_version), 0, 16);
	}

	protected function reset_no_ready_room(array $room, string $room_key, string $request_id): void
	{
		$payload = [
			'requestId' => $request_id,
			'request_id' => $request_id,
			'roomKey' => $room_key,
			'room_key' => $room_key,
			'reason' => 'room_idle_unready',
		];

		if ($this->truthy($room['activeHand'] ?? $room['active_hand'] ?? false))
		{
			$session_id = $this->latest_active_session_id($room_key);
			if ($session_id <= 0)
			{
				throw new \RuntimeException('active_session_not_found');
			}

			$this->runtime_request('POST', '/card-games/runtime/sessions/' . $session_id . '/cancel', $payload + [
				'action' => 'cancel_game',
				'sessionId' => $session_id,
				'session_id' => $session_id,
			]);
			return;
		}

		$this->runtime_request('POST', '/card-games/runtime/rooms/' . rawurlencode($room_key) . '/reset', $payload + [
			'action' => 'reset_room',
		]);
	}

	protected function should_track_no_ready_room(array $room): bool
	{
		if ($this->truthy($room['activeHand'] ?? $room['active_hand'] ?? false))
		{
			return true;
		}

		if ((int) ($room['memberCount'] ?? $room['member_count'] ?? 0) > 0)
		{
			return true;
		}

		$status = (string) ($room['status'] ?? 'waiting');

		return $status !== 'waiting';
	}

	protected function latest_active_session_id(string $room_key): int
	{
		$sql = 'SELECT id
			FROM ' . $this->sessions_table . "
			WHERE room_key = '" . $this->db->sql_escape($room_key) . "'
				AND " . $this->db->sql_in_set('status', self::ACTIVE_SESSION_STATUSES) . '
			ORDER BY updated_at DESC, id DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$session_id = (int) $this->db->sql_fetchfield('id');
		$this->db->sql_freeresult($result);

		return $session_id;
	}

	protected function has_ready_seated_player(array $room): bool
	{
		$seats = $room['seats'] ?? [];
		if (!is_array($seats))
		{
			return false;
		}

		foreach ($seats as $seat)
		{
			if (!is_array($seat) || !$this->has_seat_user($seat['user'] ?? null))
			{
				continue;
			}

			if ($this->truthy($seat['ready'] ?? false))
			{
				return true;
			}
		}

		return false;
	}

	protected function has_seat_user($user): bool
	{
		if (!is_array($user))
		{
			return false;
		}

		return (int) ($user['userId'] ?? $user['user_id'] ?? 0) > 0;
	}

	protected function no_ready_since(string $room_key): int
	{
		return (int) $this->cache->get($this->no_ready_cache_key($room_key));
	}

	protected function set_no_ready_since(string $room_key, int $since): void
	{
		$this->cache->put($this->no_ready_cache_key($room_key), $since, self::NO_READY_SECONDS * 4);
	}

	protected function clear_no_ready_since(string $room_key): void
	{
		$this->cache->destroy($this->no_ready_cache_key($room_key));
	}

	protected function no_ready_cache_key(string $room_key): string
	{
		return self::NO_READY_CACHE_PREFIX . substr(hash('sha256', $room_key), 0, 32);
	}

	protected function nonce(): string
	{
		try
		{
			return bin2hex(random_bytes(16));
		}
		catch (\Exception $e)
		{
			return sha1(uniqid('', true) . mt_rand());
		}
	}

	protected function encode_json($data): string
	{
		$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

		return is_string($json) ? $json : '{}';
	}

	protected function truthy($value): bool
	{
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}
}
