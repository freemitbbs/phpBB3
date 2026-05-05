<?php

namespace freemitbbs\cardgamesauth\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class server
{
	private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
	private const DEFAULT_CLOCK_TOLERANCE_SECONDS = 10;
	private const DEFAULT_PROXY_CLOCK_SKEW_SECONDS = 300;
	private const DEFAULT_PROXY_NONCE_TTL_SECONDS = 300;
	private const DEFAULT_PROXY_MAX_BODY_BYTES = 262144;
	private const DEFAULT_EVENT_EXPORT_LIMIT = 1000;
	private const MAX_EVENT_EXPORT_LIMIT = 5000;
	private const NICKNAME_PROFILE_FIELD_IDENT = 'nick_name';

	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\request\request_interface $request;
	protected \freemitbbs\cardgamesauth\service\token_issuer $token_issuer;
	protected string $room_configs_table;
	protected string $sessions_table;
	protected string $room_members_table;
	protected string $events_table;
	protected string $snapshots_table;
	protected string $finished_summaries_table;
	protected string $player_stats_table;
	protected string $player_settings_table;
	protected string $moderation_audit_table;
	protected string $proxy_nonces_table;
	protected ?bool $nickname_profile_field_exists = null;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request_interface $request,
		\freemitbbs\cardgamesauth\service\token_issuer $token_issuer,
		string $room_configs_table,
		string $sessions_table,
		string $room_members_table,
		string $events_table,
		string $snapshots_table,
		string $finished_summaries_table,
		string $player_stats_table,
		string $player_settings_table,
		string $moderation_audit_table,
		string $proxy_nonces_table
	)
	{
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->token_issuer = $token_issuer;
		$this->room_configs_table = $room_configs_table;
		$this->sessions_table = $sessions_table;
		$this->room_members_table = $room_members_table;
		$this->events_table = $events_table;
		$this->snapshots_table = $snapshots_table;
		$this->finished_summaries_table = $finished_summaries_table;
		$this->player_stats_table = $player_stats_table;
		$this->player_settings_table = $player_settings_table;
		$this->moderation_audit_table = $moderation_audit_table;
		$this->proxy_nonces_table = $proxy_nonces_table;
	}

	public function ready(): JsonResponse
	{
		if (($error = $this->require_server_auth('')) !== null)
		{
			return $error;
		}

		return $this->json([
			'ok' => true,
			'success' => true,
			'schemaVersion' => 1,
			'schema_version' => 1,
		]);
	}

	public function verify_auth(): JsonResponse
	{
		$body = $this->raw_body();
		if (($error = $this->require_server_auth($body)) !== null)
		{
			return $error;
		}

		$data = $this->decode_body($body);
		if (!is_array($data))
		{
			return $this->json_error('invalid_json', 'Request body must be JSON.', 400);
		}

		$token = trim((string) ($data['token'] ?? ''));
		if ($token === '')
		{
			return $this->json_error('missing_token', 'Request body must include token.', 400);
		}

		try
		{
			$claims = $this->token_issuer->verify($token, $this->get_int_config('cardgamesauth_token_clock_tolerance', self::DEFAULT_CLOCK_TOLERANCE_SECONDS, 0, 300));
		}
		catch (\InvalidArgumentException $e)
		{
			return $this->json_error((string) $e->getMessage(), 'Game auth token could not be verified.', 401);
		}

		$token_permissions = $this->string_list($claims['permissions'] ?? []);
		if (!in_array('u_cardgames_play', $token_permissions, true))
		{
			return $this->json_error('permission_denied', 'Game auth token does not grant card-game access.', 403);
		}

		$user = $this->load_user((int) $claims['user_id']);
		if (empty($user) || (int) $user['user_id'] <= ANONYMOUS)
		{
			return $this->json_error('user_not_found', 'phpBB user could not be loaded.', 404);
		}

		$user_type = (int) ($user['user_type'] ?? USER_IGNORE);
		if ($user_type === USER_IGNORE || $user_type === USER_INACTIVE)
		{
			return $this->json_error('user_not_active', 'phpBB user is not active.', 403);
		}

		$is_banned = $this->is_user_banned((int) $user['user_id']);
		if ($is_banned)
		{
			return $this->json_error('user_banned', 'phpBB user is banned.', 403);
		}

		$permissions = $this->current_user_permissions($user);
		if (!in_array('u_cardgames_play', $permissions, true))
		{
			return $this->json_error('permission_denied', 'phpBB user no longer has card-game access.', 403);
		}

		return $this->json([
			'success' => true,
			'user' => $this->user_payload($user, $claims, $permissions, $is_banned),
			'token' => [
				'jti' => (string) ($claims['jti'] ?? ''),
				'expiresAt' => $this->iso_time((int) ($claims['exp'] ?? 0)),
			],
		]);
	}

	public function room_configs(): JsonResponse
	{
		if (($error = $this->require_server_auth('')) !== null)
		{
			return $error;
		}

		$sql = 'SELECT room_key, game_type, display_name, sort_order, enabled, default_settings_json, created_at, updated_at
			FROM ' . $this->room_configs_table . '
			ORDER BY sort_order ASC, room_key ASC';
		$result = $this->db->sql_query($sql);

		$rooms = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rooms[] = [
				'roomKey' => (string) $row['room_key'],
				'gameType' => (string) $row['game_type'],
				'displayName' => (string) $row['display_name'],
				'sortOrder' => (int) $row['sort_order'],
				'enabled' => (bool) ((int) $row['enabled']),
				'defaultSettings' => $this->decode_json_field((string) $row['default_settings_json'], new \stdClass()),
				'createdAt' => $this->iso_time((int) $row['created_at']),
				'updatedAt' => $this->iso_time((int) $row['updated_at']),
			];
		}
		$this->db->sql_freeresult($result);

		return $this->json([
			'success' => true,
			'rooms' => $rooms,
		]);
	}

	public function active_recoveries(): JsonResponse
	{
		if (($error = $this->require_server_auth('')) !== null)
		{
			return $error;
		}

		$sql = 'SELECT s.id AS session_id, s.room_key, s.game_type, s.status, s.owner_user_id,
					s.settings_json, s.state_version, s.updated_at, s.finished_at,
					rc.display_name, rc.sort_order, rc.enabled, rc.default_settings_json,
					gs.seq AS snapshot_seq, gs.state_schema_version, gs.state_json,
					gs.created_at AS snapshot_created_at,
					ge.event_seq
				FROM ' . $this->sessions_table . ' s
			INNER JOIN (
				SELECT session_id, MAX(seq) AS seq
				FROM ' . $this->snapshots_table . '
				GROUP BY session_id
			) latest_snapshot
				ON latest_snapshot.session_id = s.id
			INNER JOIN ' . $this->snapshots_table . ' gs
				ON gs.session_id = latest_snapshot.session_id
				AND gs.seq = latest_snapshot.seq
			LEFT JOIN (
				SELECT session_id, MAX(seq) AS event_seq
				FROM ' . $this->events_table . '
				GROUP BY session_id
			) ge
				ON ge.session_id = s.id
			LEFT JOIN ' . $this->room_configs_table . ' rc
				ON rc.room_key = s.room_key
			WHERE ' . $this->db->sql_in_set('s.status', ['starting', 'playing']) . '
				AND s.finished_at = 0
			ORDER BY s.updated_at ASC, s.id ASC';
		$result = $this->db->sql_query($sql);

		$rows = [];
		$session_ids = [];
		$user_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$snapshot = $this->decode_json_field((string) ($row['state_json'] ?? ''), []);
			if (!$this->is_recovery_snapshot($snapshot))
			{
				continue;
			}

			$rows[] = [
				'session' => $row,
				'snapshot' => $snapshot,
			];
			$session_id = (int) $row['session_id'];
			$session_ids[] = $session_id;
			$owner_user_id = (int) ($row['owner_user_id'] ?? 0);
			if ($owner_user_id > 0)
			{
				$user_ids[] = $owner_user_id;
			}

			foreach (($snapshot['players'] ?? []) as $player)
			{
				if (is_array($player))
				{
					$user_id = (int) ($player['userId'] ?? $player['user_id'] ?? 0);
					if ($user_id > 0)
					{
						$user_ids[] = $user_id;
					}
				}
			}
		}
		$this->db->sql_freeresult($result);

		$observers_by_session = $this->load_recovery_observers($session_ids);
		foreach ($observers_by_session as $observers)
		{
			foreach ($observers as $observer)
			{
				$user_id = (int) ($observer['user_id'] ?? 0);
				if ($user_id > 0)
				{
					$user_ids[] = $user_id;
				}
			}
		}

		$users = $this->load_users($user_ids);
		$recoveries = [];
		foreach ($rows as $recovery)
		{
			$session = $recovery['session'];
			$snapshot = $recovery['snapshot'];
			$session_id = (int) $session['session_id'];
			$recoveries[] = [
				'sessionId' => $session_id,
				'session_id' => $session_id,
				'seq' => max(
					(int) ($session['snapshot_seq'] ?? 0),
					(int) ($session['state_version'] ?? 0),
					(int) ($session['event_seq'] ?? 0)
				),
				'room' => $this->recovery_room_payload($session, $snapshot, $observers_by_session[$session_id] ?? [], $users),
				'snapshot' => $snapshot,
				'state' => $snapshot,
				'stateJson' => $snapshot,
				'state_json' => $snapshot,
			];
		}

		return $this->json([
			'success' => true,
			'recoveries' => $recoveries,
		]);
	}

	public function create_session(): JsonResponse
	{
		$body = $this->raw_body();
		if (($error = $this->require_server_auth($body)) !== null)
		{
			return $error;
		}

		$data = $this->require_json_object($body);
		if (!is_array($data))
		{
			return $this->json_error('invalid_json', 'Request body must be a JSON object.', 400);
		}

		try
		{
			$now = time();
			$row = [
				'room_key' => $this->required_string($data, 'roomKey', 'room_key', 64),
				'game_type' => $this->required_string($data, 'gameType', 'game_type', 32),
				'status' => $this->string_value($data, 'status', 'status', 32, 'waiting'),
				'owner_user_id' => $this->nullable_int_value($data, 'ownerUserId', 'owner_user_id'),
				'settings_json' => $this->json_value($data, 'settings', 'settings_json', new \stdClass()),
				'state_schema_version' => $this->int_value($data, 'stateSchemaVersion', 'state_schema_version', 1),
				'state_version' => $this->int_value($data, 'stateVersion', 'state_version', 0),
				'random_audit_json' => $this->nullable_json_value($data, 'randomAudit', 'random_audit_json'),
				'created_at' => $this->time_value($data, 'createdAt', 'created_at', $now),
				'started_at' => $this->nullable_time_value($data, 'startedAt', 'started_at'),
				'updated_at' => $this->time_value($data, 'updatedAt', 'updated_at', $now),
				'finished_at' => $this->nullable_time_value($data, 'finishedAt', 'finished_at'),
			];

			$id = $this->insert_row($this->sessions_table, $row);
		}
		catch (\InvalidArgumentException $e)
		{
			return $this->json_error((string) $e->getMessage(), 'Session payload is invalid.', 400);
		}
		catch (\RuntimeException $e)
		{
			return $this->json_error('session_store_failed', 'Session could not be stored.', 500);
		}

		return $this->json([
			'success' => true,
			'sessionId' => $id,
		], 201);
	}

	public function update_session(int $id): JsonResponse
	{
		$body = $this->raw_body();
		if (($error = $this->require_server_auth($body)) !== null)
		{
			return $error;
		}

		$id = max(0, $id);
		if ($id <= 0)
		{
			return $this->json_error('invalid_session_id', 'Session id must be positive.', 400);
		}

		$data = $this->require_json_object($body);
		if (!is_array($data))
		{
			return $this->json_error('invalid_json', 'Request body must be a JSON object.', 400);
		}

		$updates = [];
		$this->copy_string_update($updates, $data, 'status', 'status', 32);
		$this->copy_nullable_int_update($updates, $data, 'ownerUserId', 'owner_user_id');
		$this->copy_json_update($updates, $data, 'settings', 'settings_json');
		$this->copy_int_update($updates, $data, 'stateSchemaVersion', 'state_schema_version');
		$this->copy_int_update($updates, $data, 'stateVersion', 'state_version');
		$this->copy_nullable_json_update($updates, $data, 'randomAudit', 'random_audit_json');
		$this->copy_nullable_time_update($updates, $data, 'startedAt', 'started_at');
		$this->copy_time_update($updates, $data, 'updatedAt', 'updated_at');
		$this->copy_nullable_time_update($updates, $data, 'finishedAt', 'finished_at');

		if (empty($updates))
		{
			return $this->json_error('empty_update', 'No supported session fields were provided.', 400);
		}

		$sql = 'UPDATE ' . $this->sessions_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $updates) . '
			WHERE id = ' . $id;
		$this->db->sql_query($sql);

		return $this->json([
			'success' => true,
			'sessionId' => $id,
			'updated' => (int) $this->db->sql_affectedrows(),
		]);
	}

	public function events(): JsonResponse
	{
		$body = $this->raw_body();
		if (($error = $this->require_server_auth($body)) !== null)
		{
			return $error;
		}

		$data = $this->require_json_object($body);
		if (!is_array($data))
		{
			return $this->json_error('invalid_json', 'Request body must be a JSON object.', 400);
		}

		try
		{
			$events = $this->batch_rows($data, 'events', 'event');
			$inserted = 0;
			$skipped = 0;
			$recorded = [];
			foreach ($events as $event)
			{
				if (isset($event['roomKey']) || isset($event['room_key']))
				{
					$result = $this->record_lobby_event($event);
					$inserted += (int) $result['inserted'];
					$skipped += (int) $result['skipped'];
					$recorded[] = [
						'sessionId' => (int) $result['sessionId'],
						'seq' => (int) $result['seq'],
					];
					continue;
				}

				$session_id = $this->required_int($event, 'sessionId', 'session_id');
				$seq = $this->required_int($event, 'seq', 'seq');
				$insert = $this->try_insert_row($this->events_table, [
					'session_id' => $session_id,
					'seq' => $seq,
					'game_type' => $this->required_string($event, 'gameType', 'game_type', 32),
					'actor_user_id' => $this->nullable_int_value($event, 'actorUserId', 'actor_user_id'),
					'request_id' => $this->nullable_string_value($event, 'requestId', 'request_id', 64),
					'event_type' => $this->required_string($event, 'eventType', 'event_type', 64),
					'payload_schema_version' => $this->int_value($event, 'payloadSchemaVersion', 'payload_schema_version', 1),
					'payload_json' => $this->json_value($event, 'payload', 'payload_json', new \stdClass()),
					'created_at' => $this->time_value($event, 'createdAt', 'created_at', time()),
				]);
				if ($insert['inserted'])
				{
					$inserted++;
				}
				else if ($this->is_duplicate_insert($insert))
				{
					$skipped++;
				}
				else
				{
					return $this->json_error('event_insert_failed', 'Event could not be stored.', 500);
				}
			}
		}
		catch (\InvalidArgumentException $e)
		{
			return $this->json_error((string) $e->getMessage(), 'Event payload is invalid.', 400);
		}
		catch (\RuntimeException $e)
		{
			return $this->json_error('event_store_failed', 'Event could not be stored.', 500);
		}

		return $this->json([
			'ok' => true,
			'success' => true,
			'inserted' => $inserted,
			'skipped' => $skipped,
			'events' => $recorded,
		]);
	}

	public function events_read(): JsonResponse
	{
		return $this->event_export_response(false);
	}

	public function replay_export(): JsonResponse
	{
		return $this->event_export_response(true);
	}

	public function snapshots(): JsonResponse
	{
		$body = $this->raw_body();
		if (($error = $this->require_server_auth($body)) !== null)
		{
			return $error;
		}

		$data = $this->require_json_object($body);
		if (!is_array($data))
		{
			return $this->json_error('invalid_json', 'Request body must be a JSON object.', 400);
		}

		try
		{
			$snapshots = $this->batch_rows($data, 'snapshots', 'snapshot');
			$inserted = 0;
			$updated = 0;
			foreach ($snapshots as $snapshot)
			{
				$session_id = $this->required_int($snapshot, 'sessionId', 'session_id');
				$seq = $this->required_int($snapshot, 'seq', 'seq');
				$row = [
					'session_id' => $session_id,
					'seq' => $seq,
					'game_type' => $this->required_string($snapshot, 'gameType', 'game_type', 32),
					'state_schema_version' => $this->int_value($snapshot, 'stateSchemaVersion', 'state_schema_version', 1),
					'state_json' => $this->json_value($snapshot, 'state', 'state_json', new \stdClass()),
					'created_at' => $this->time_value($snapshot, 'createdAt', 'created_at', time()),
				];

				$insert = $this->try_insert_row($this->snapshots_table, $row);
				if ($insert['inserted'])
				{
					$inserted++;
				}
				else if ($this->is_duplicate_insert($insert))
				{
					$this->update_snapshot($session_id, $seq, $row);
					$updated++;
				}
				else
				{
					return $this->json_error('snapshot_store_failed', 'Snapshot could not be stored.', 500);
				}
			}
		}
		catch (\InvalidArgumentException $e)
		{
			return $this->json_error((string) $e->getMessage(), 'Snapshot payload is invalid.', 400);
		}

		return $this->json([
			'success' => true,
			'inserted' => $inserted,
			'updated' => $updated,
		]);
	}

	public function finished_summary(): JsonResponse
	{
		$body = $this->raw_body();
		if (($error = $this->require_server_auth($body)) !== null)
		{
			return $error;
		}

		$data = $this->require_json_object($body);
		if (!is_array($data))
		{
			return $this->json_error('invalid_json', 'Request body must be a JSON object.', 400);
		}

		try
		{
			$session_id = $this->required_int($data, 'sessionId', 'session_id');
			$row = [
				'session_id' => $session_id,
				'game_type' => $this->required_string($data, 'gameType', 'game_type', 32),
				'room_key' => $this->required_string($data, 'roomKey', 'room_key', 64),
				'winner_json' => $this->json_value($data, 'winner', 'winner_json', new \stdClass()),
				'score_json' => $this->json_value($data, 'score', 'score_json', new \stdClass()),
				'summary_json' => $this->json_value($data, 'summary', 'summary_json', new \stdClass()),
				'finished_at' => $this->time_value($data, 'finishedAt', 'finished_at', time()),
			];
			$updated = $this->upsert_row($this->finished_summaries_table, ['session_id' => $session_id], $row);
		}
		catch (\InvalidArgumentException $e)
		{
			return $this->json_error((string) $e->getMessage(), 'Finished summary payload is invalid.', 400);
		}
		catch (\RuntimeException $e)
		{
			return $this->json_error('finished_summary_store_failed', 'Finished summary could not be stored.', 500);
		}

		return $this->json([
			'success' => true,
			'updated' => $updated,
		]);
	}

	public function player_stats(): JsonResponse
	{
		$body = $this->raw_body();
		if (($error = $this->require_server_auth($body)) !== null)
		{
			return $error;
		}

		$data = $this->require_json_object($body);
		if (!is_array($data))
		{
			return $this->json_error('invalid_json', 'Request body must be a JSON object.', 400);
		}

		try
		{
			$stats_rows = $this->batch_rows($data, 'stats', 'stat');
			$updated = 0;
			foreach ($stats_rows as $stat)
			{
				$game_type = $this->required_string($stat, 'gameType', 'game_type', 32);
				$user_id = $this->required_int($stat, 'userId', 'user_id');
				$row = [
					'game_type' => $game_type,
					'user_id' => $user_id,
					'games_played' => $this->int_value($stat, 'gamesPlayed', 'games_played', 0),
					'games_won' => $this->int_value($stat, 'gamesWon', 'games_won', 0),
					'stats_json' => $this->json_value($stat, 'stats', 'stats_json', new \stdClass()),
					'updated_at' => $this->time_value($stat, 'updatedAt', 'updated_at', time()),
				];
				$updated += $this->upsert_row($this->player_stats_table, ['game_type' => $game_type, 'user_id' => $user_id], $row);
			}
		}
		catch (\InvalidArgumentException $e)
		{
			return $this->json_error((string) $e->getMessage(), 'Player stats payload is invalid.', 400);
		}
		catch (\RuntimeException $e)
		{
			return $this->json_error('player_stats_store_failed', 'Player stats could not be stored.', 500);
		}

		return $this->json([
			'success' => true,
			'updated' => $updated,
		]);
	}

	public function cleanup(): JsonResponse
	{
		$body = $this->raw_body();
		if (($error = $this->require_server_auth($body)) !== null)
		{
			return $error;
		}

		$data = $this->decode_body($body);
		$max_age_days = is_array($data) ? max(1, min(365, (int) ($data['maxAgeDays'] ?? 30))) : 30;
		$cutoff = time() - ($max_age_days * 86400);

		$this->db->sql_query('DELETE FROM ' . $this->proxy_nonces_table . '
			WHERE expires_at < ' . time());
		$nonces = (int) $this->db->sql_affectedrows();

		$this->db->sql_query('DELETE FROM ' . $this->snapshots_table . '
			WHERE created_at < ' . $cutoff);
		$snapshots = (int) $this->db->sql_affectedrows();

		return $this->json([
			'success' => true,
			'deleted' => [
				'proxyNonces' => $nonces,
				'snapshots' => $snapshots,
			],
		]);
	}

	protected function require_server_auth(string $body): ?JsonResponse
	{
		if (!(bool) ((int) ($this->config['cardgamesauth_proxy_enabled'] ?? 1)))
		{
			return $this->json_error('proxy_disabled', 'Card games server proxy is disabled.', 403);
		}

		if (strlen($body) > $this->get_int_config('cardgamesauth_proxy_max_body_bytes', self::DEFAULT_PROXY_MAX_BODY_BYTES, 1024, 1048576))
		{
			return $this->json_error('request_too_large', 'Request body is too large.', 413);
		}

		$timestamp = (int) $this->request->server('HTTP_X_CARDGAMES_TIMESTAMP', 0);
		$nonce = trim((string) $this->request->server('HTTP_X_CARDGAMES_NONCE', ''));
		$signature = trim((string) $this->request->server('HTTP_X_CARDGAMES_SIGNATURE', ''));
		$now = time();
		$clock_skew = $this->get_int_config('cardgamesauth_proxy_clock_skew', self::DEFAULT_PROXY_CLOCK_SKEW_SECONDS, 30, 3600);

		if ($timestamp <= 0 || abs($now - $timestamp) > $clock_skew)
		{
			return $this->json_error('invalid_timestamp', 'Server proxy timestamp is missing or outside the accepted window.', 401);
		}
		if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $nonce))
		{
			return $this->json_error('invalid_nonce', 'Server proxy nonce is missing or invalid.', 401);
		}
		if (str_starts_with($signature, 'sha256='))
		{
			$signature = substr($signature, 7);
		}
		if (!preg_match('/^[a-f0-9]{64}$/i', $signature))
		{
			return $this->json_error('invalid_signature', 'Server proxy signature is missing or invalid.', 401);
		}

		$secret = trim((string) ($this->config['cardgamesauth_proxy_secret'] ?? ''));
		if ($secret === '')
		{
			return $this->json_error('proxy_secret_missing', 'Server proxy secret is not configured.', 500);
		}

		$method = strtoupper((string) $this->request->server('REQUEST_METHOD', 'GET'));
		$body_hash = hash('sha256', $body);
		$header_body_hash = strtolower(trim((string) $this->request->server('HTTP_X_CARDGAMES_CONTENT_SHA256', '')));
		if ($header_body_hash !== '' && !hash_equals($body_hash, $header_body_hash))
		{
			return $this->json_error('invalid_body_hash', 'Server proxy request body hash is invalid.', 401);
		}

		$valid_signature = false;
		foreach ($this->proxy_request_paths() as $path)
		{
			$canonical = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $body_hash;
			$expected = hash_hmac('sha256', $canonical, $secret);
			if (hash_equals($expected, strtolower($signature)))
			{
				$valid_signature = true;
				break;
			}
		}
		if (!$valid_signature)
		{
			return $this->json_error('invalid_signature', 'Server proxy signature is invalid.', 401);
		}

		$nonce_hash = hash('sha256', $nonce);
		$this->db->sql_query('DELETE FROM ' . $this->proxy_nonces_table . '
			WHERE expires_at < ' . $now);
		$nonce_insert = $this->try_insert_row($this->proxy_nonces_table, [
			'nonce_hash' => $nonce_hash,
			'created_at' => $now,
			'expires_at' => $now + $this->get_int_config('cardgamesauth_proxy_nonce_ttl', self::DEFAULT_PROXY_NONCE_TTL_SECONDS, 30, 3600),
		]);
		if (!$nonce_insert['inserted'])
		{
			return $this->is_duplicate_insert($nonce_insert) ?
				$this->json_error('replayed_nonce', 'Server proxy nonce has already been used.', 409) :
				$this->json_error('nonce_store_failed', 'Server proxy nonce could not be stored.', 500);
		}

		return null;
	}

	protected function load_user(int $user_id): array
	{
		if ($user_id <= 0)
		{
			return [];
		}

		$sql = 'SELECT user_id, username, username_clean, user_type, user_permissions, user_colour,
				user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return is_array($row) ? $row : [];
	}

	protected function user_payload(array $user, array $claims, array $permissions, bool $is_banned): array
	{
		$user_id = (int) $user['user_id'];
		$nickname = $this->profile_nickname($user_id);
		$display_name = $nickname !== '' ? (string) $user['username'] . '（' . $nickname . '）' : (string) ($claims['display_name'] ?? $user['username']);

		return [
			'userId' => $user_id,
			'username' => (string) $user['username'],
			'usernameClean' => (string) $user['username_clean'],
			'displayName' => $display_name,
			'nickname' => $nickname,
			'avatarUrl' => $this->avatar_url($user),
			'groups' => $this->user_groups($user_id),
			'permissions' => $permissions,
			'isBanned' => $is_banned,
		];
	}

	protected function load_users(array $user_ids): array
	{
		$user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids), static function ($user_id) {
			return $user_id > 0;
		})));
		if (empty($user_ids))
		{
			return [];
		}

		$sql = 'SELECT user_id, username, username_clean, user_type, user_permissions, user_colour,
				user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
			FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('user_id', $user_ids);
		$result = $this->db->sql_query($sql);
		$users = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[(int) $row['user_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $users;
	}

	protected function is_recovery_snapshot($snapshot): bool
	{
		if (!is_array($snapshot))
		{
			return false;
		}

		return (int) ($snapshot['schemaVersion'] ?? 0) === 1
			&& (string) ($snapshot['gameType'] ?? '') === 'tractor'
			&& (string) ($snapshot['roomKey'] ?? '') !== ''
			&& (string) ($snapshot['handId'] ?? '') !== '';
	}

	protected function load_recovery_observers(array $session_ids): array
	{
		$session_ids = array_values(array_unique(array_filter(array_map('intval', $session_ids), static function ($session_id) {
			return $session_id > 0;
		})));
		if (empty($session_ids))
		{
			return [];
		}

		$sql = 'SELECT session_id, user_id, watched_seat_index, connected
			FROM ' . $this->room_members_table . "
			WHERE role = 'observer'
				AND left_at = 0
				AND " . $this->db->sql_in_set('session_id', $session_ids) . '
			ORDER BY session_id ASC, joined_at ASC, id ASC';
		$result = $this->db->sql_query($sql);
		$observers = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$observers[(int) $row['session_id']][] = $row;
		}
		$this->db->sql_freeresult($result);

		return $observers;
	}

	protected function recovery_room_payload(array $session, array $snapshot, array $observer_rows, array $users): array
	{
		$settings = $this->decode_json_field((string) ($session['settings_json'] ?? ''), []);
		if (!is_array($settings) || $this->is_list_array($settings))
		{
			$settings = [];
		}
		if (empty($settings))
		{
			$default_settings = $this->decode_json_field((string) ($session['default_settings_json'] ?? ''), []);
			$settings = (is_array($default_settings) && !$this->is_list_array($default_settings)) ? $default_settings : [];
		}

		$players = $this->snapshot_players($snapshot);
		$max_seat_index = -1;
		foreach ($players as $player)
		{
			$max_seat_index = max($max_seat_index, (int) ($player['seatIndex'] ?? 0));
		}
		$seat_count = max($this->seat_count_from_settings($settings), $max_seat_index + 1, 1);
		$seats = [];
		for ($seat_index = 0; $seat_index < $seat_count; $seat_index++)
		{
			$seats[$seat_index] = [
				'seatIndex' => $seat_index,
				'user' => null,
				'ready' => false,
				'connected' => false,
			];
		}

		foreach ($players as $player)
		{
			$seat_index = (int) ($player['seatIndex'] ?? 0);
			$user_id = (int) ($player['userId'] ?? 0);
			if ($seat_index < 0 || $seat_index >= $seat_count || empty($users[$user_id]))
			{
				continue;
			}

			$seats[$seat_index]['user'] = $this->room_user_payload($users[$user_id]);
		}

		$observers = [];
		foreach ($observer_rows as $observer_row)
		{
			$user_id = (int) ($observer_row['user_id'] ?? 0);
			if (empty($users[$user_id]))
			{
				continue;
			}

			$observer = [
				'user' => $this->room_user_payload($users[$user_id]),
				'connected' => (bool) ((int) ($observer_row['connected'] ?? 0)),
			];
			$watched_seat_index = (int) ($observer_row['watched_seat_index'] ?? -1);
			if ($watched_seat_index >= 0)
			{
				$observer['watchedSeatIndex'] = $watched_seat_index;
			}
			$observers[] = $observer;
		}

		$owner_user_id = (int) ($session['owner_user_id'] ?? 0);
		$member_count = count(array_filter($seats, static function ($seat) {
			return !empty($seat['user']);
		})) + count($observers);
		$updated_at = max((int) ($session['updated_at'] ?? 0), (int) ($session['snapshot_created_at'] ?? 0));
		$updated_at_text = $updated_at > 0 ? $this->iso_time($updated_at) : (string) ($snapshot['updatedAt'] ?? '');
		$status = (string) ($session['status'] ?? 'playing');
		if (!in_array($status, ['starting', 'playing'], true))
		{
			$status = 'playing';
		}

		$room = [
			'roomKey' => (string) ($session['room_key'] ?: ($snapshot['roomKey'] ?? '')),
			'gameType' => (string) ($session['game_type'] ?: ($snapshot['gameType'] ?? '')),
			'displayName' => (string) ($session['display_name'] ?: ($session['room_key'] ?: ($snapshot['roomKey'] ?? ''))),
			'sortOrder' => (int) ($session['sort_order'] ?? 0),
			'enabled' => !isset($session['enabled']) || (bool) ((int) $session['enabled']),
			'status' => $status,
			'stateVersion' => max(
				(int) ($session['state_version'] ?? 0),
				(int) ($session['snapshot_seq'] ?? 0),
				(int) ($session['event_seq'] ?? 0)
			),
			'seatCount' => $seat_count,
			'memberCount' => $member_count,
			'seats' => array_values($seats),
			'observers' => $observers,
			'settings' => $this->json_object_payload($settings),
			'updatedAt' => $updated_at_text,
		];

		if ($owner_user_id > 0 && !empty($users[$owner_user_id]))
		{
			$room['owner'] = $this->room_user_payload($users[$owner_user_id]);
		}

		return $room;
	}

	protected function snapshot_players(array $snapshot): array
	{
		$players = $snapshot['players'] ?? [];
		if (!is_array($players))
		{
			return [];
		}

		$result = [];
		foreach ($players as $player)
		{
			if (!is_array($player))
			{
				continue;
			}

			$seat_index = (int) ($player['seatIndex'] ?? $player['seat_index'] ?? -1);
			$user_id = (int) ($player['userId'] ?? $player['user_id'] ?? 0);
			if ($seat_index < 0 || $user_id <= 0)
			{
				continue;
			}

			$result[] = [
				'seatIndex' => $seat_index,
				'userId' => $user_id,
			];
		}

		return $result;
	}

	protected function seat_count_from_settings(array $settings): int
	{
		$seat_count = (int) ($settings['seatCount'] ?? $settings['seat_count'] ?? 4);
		return max(1, $seat_count);
	}

	protected function room_user_payload(array $user): array
	{
		$user_id = (int) ($user['user_id'] ?? 0);
		$username = (string) ($user['username'] ?? '');
		$nickname = $user_id > 0 ? $this->profile_nickname($user_id) : '';
		$display_name = $nickname !== '' ? $username . '（' . $nickname . '）' : $username;

		return [
			'userId' => $user_id,
			'username' => $username,
			'usernameClean' => (string) ($user['username_clean'] ?? $username),
			'displayName' => $display_name,
			'nickname' => $nickname,
			'avatarUrl' => $this->avatar_url($user),
		];
	}

	protected function json_object_payload(array $value)
	{
		if (empty($value) || $this->is_list_array($value))
		{
			return new \stdClass();
		}

		return $value;
	}

	protected function is_list_array(array $value): bool
	{
		if (empty($value))
		{
			return false;
		}

		return array_keys($value) === range(0, count($value) - 1);
	}

	protected function user_groups(int $user_id): array
	{
		$sql = 'SELECT group_id
			FROM ' . USER_GROUP_TABLE . '
			WHERE user_id = ' . $user_id . '
				AND user_pending = 0
			ORDER BY group_id ASC';
		$result = $this->db->sql_query($sql);
		$groups = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$groups[] = (string) ((int) $row['group_id']);
		}
		$this->db->sql_freeresult($result);

		return $groups;
	}

	protected function is_user_banned(int $user_id): bool
	{
		$sql = 'SELECT ban_id
			FROM ' . BANLIST_TABLE . '
			WHERE ban_userid = ' . $user_id . '
				AND ban_exclude = 0
				AND (ban_end = 0 OR ban_end > ' . time() . ')';
		$result = $this->db->sql_query_limit($sql, 1);
		$banned = (bool) $this->db->sql_fetchfield('ban_id');
		$this->db->sql_freeresult($result);

		return $banned;
	}

	protected function current_user_permissions(array $user): array
	{
		$acl_user = $user;
		$acl_user['user_permissions'] = (string) ($acl_user['user_permissions'] ?? '');

		$user_auth = new \phpbb\auth\auth();
		$user_auth->acl($acl_user);

		$permissions = [];
		if ($user_auth->acl_get('u_cardgames_play'))
		{
			$permissions[] = 'u_cardgames_play';
		}
		if ($user_auth->acl_get('m_'))
		{
			$permissions[] = 'moderator';
		}
		if ($user_auth->acl_get('a_'))
		{
			$permissions[] = 'admin';
		}

		return $permissions;
	}

	protected function profile_nickname(int $user_id): string
	{
		if (!$this->nickname_profile_field_exists())
		{
			return '';
		}

		$sql = 'SELECT pf_nick_name
			FROM ' . PROFILE_FIELDS_DATA_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$nickname = (string) $this->db->sql_fetchfield('pf_nick_name');
		$this->db->sql_freeresult($result);

		return trim(html_entity_decode(strip_tags($nickname), ENT_QUOTES, 'UTF-8'));
	}

	protected function nickname_profile_field_exists(): bool
	{
		if ($this->nickname_profile_field_exists !== null)
		{
			return $this->nickname_profile_field_exists;
		}

		$sql = 'SELECT field_id
			FROM ' . PROFILE_FIELDS_TABLE . "
			WHERE field_ident = '" . self::NICKNAME_PROFILE_FIELD_IDENT . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$this->nickname_profile_field_exists = (bool) $this->db->sql_fetchfield('field_id');
		$this->db->sql_freeresult($result);

		return $this->nickname_profile_field_exists;
	}

	protected function avatar_url(array $user): string
	{
		if (!function_exists('phpbb_get_user_avatar'))
		{
			return '';
		}

		$avatar_html = phpbb_get_user_avatar($user, 'USER_AVATAR', false, true);
		if ($avatar_html === '')
		{
			return '';
		}

		if (preg_match('#src="([^"]+)"#', $avatar_html, $match))
		{
			$url = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
			if (preg_match('#^https?://#i', $url))
			{
				return $url;
			}

			return rtrim(generate_board_url(), '/') . '/' . ltrim($url, '/');
		}

		return '';
	}

	protected function record_lobby_event(array $event): array
	{
		$room_key = $this->required_string($event, 'roomKey', 'room_key', 64);
		$game_type = $this->required_string($event, 'gameType', 'game_type', 32);
		$event_type = $this->required_string($event, 'eventType', 'event_type', 64);
		$request_id = $this->nullable_string_value($event, 'requestId', 'request_id', 64);
		$now = time();

		$this->db->sql_transaction('begin');
		try
		{
			$session = $this->ensure_lobby_session($room_key, $game_type, $now);
			$session_id = (int) $session['id'];
			$existing_seq = $this->find_event_seq_by_request_id($session_id, $request_id);
			if ($existing_seq > 0)
			{
				$this->db->sql_transaction('commit');
				return [
					'inserted' => 0,
					'skipped' => 1,
					'sessionId' => $session_id,
					'seq' => $existing_seq,
				];
			}

			$room_state_version = max(0, $this->int_value($event, 'roomStateVersion', 'room_state_version', 0));
			$seq = max(((int) $session['state_version']) + 1, $room_state_version);
			$owner_user_id = $this->nullable_int_value($event, 'roomOwnerUserId', 'room_owner_user_id');
			$this->apply_lobby_membership($session_id, $room_key, $event['membership'] ?? null, $now);

			$this->db->sql_query('UPDATE ' . $this->sessions_table . '
				SET ' . $this->db->sql_build_array('UPDATE', [
					'state_version' => $seq,
					'owner_user_id' => $owner_user_id,
					'updated_at' => $now,
				]) . '
				WHERE id = ' . $session_id);

			$insert = $this->try_insert_row($this->events_table, [
				'session_id' => $session_id,
				'seq' => $seq,
				'game_type' => $game_type,
				'actor_user_id' => $this->nullable_int_value($event, 'actorUserId', 'actor_user_id'),
				'request_id' => $request_id,
				'event_type' => $event_type,
				'payload_schema_version' => $this->int_value($event, 'payloadSchemaVersion', 'payload_schema_version', 1),
				'payload_json' => $this->json_value($event, 'payload', 'payload_json', new \stdClass()),
				'created_at' => $this->time_value($event, 'createdAt', 'created_at', $now),
			]);
			if (!$insert['inserted'] && !$this->is_duplicate_insert($insert))
			{
				throw new \RuntimeException('event_insert_failed');
			}

			$this->db->sql_transaction('commit');
			return [
				'inserted' => $insert['inserted'] ? 1 : 0,
				'skipped' => $insert['inserted'] ? 0 : 1,
				'sessionId' => $session_id,
				'seq' => $seq,
			];
		}
		catch (\Throwable $e)
		{
			$this->db->sql_transaction('rollback');
			throw $e;
		}
	}

	protected function event_export_response(bool $replay_export): JsonResponse
	{
		if (($error = $this->require_server_auth('')) !== null)
		{
			return $error;
		}

		$session_id = max(0, $this->query_int('sessionId', 'session_id', 0));
		$room_key = trim((string) $this->query_string('roomKey', 'room_key', ''));
		$limit = $this->query_int('limit', 'limit', self::DEFAULT_EVENT_EXPORT_LIMIT);
		$limit = max(1, min(self::MAX_EVENT_EXPORT_LIMIT, $limit));
		$after_seq = $this->query_optional_int('afterSeq', 'after_seq');
		$from_seq = $this->query_optional_int('fromSeq', 'from_seq');
		$to_seq = $this->query_optional_int('toSeq', 'to_seq');
		$include_snapshots = $this->query_bool('includeSnapshots', 'include_snapshots', $replay_export);
		$include_finished_summary = $this->query_bool('includeFinishedSummary', 'include_finished_summary', $replay_export);

		if ($session_id <= 0 && $room_key === '')
		{
			return $this->json_error('missing_event_selector', 'Event export requires sessionId or roomKey.', 400);
		}

		$event_rows = $this->load_event_export_rows($session_id, $room_key, $after_seq, $from_seq, $to_seq, $limit + 1);
		$has_more = count($event_rows) > $limit;
		if ($has_more)
		{
			array_pop($event_rows);
		}

		$events = [];
		$sessions = [];
		$session_ids = [];
		foreach ($event_rows as $row)
		{
			$row_session_id = (int) $row['session_id'];
			$session_ids[$row_session_id] = $row_session_id;
			if (!isset($sessions[$row_session_id]))
			{
				$sessions[$row_session_id] = $this->event_export_session_payload($row);
			}
			$events[] = $this->event_export_event_payload($row);
		}

		if (empty($sessions) && $session_id > 0)
		{
			$session = $this->load_event_export_session($session_id);
			if (!empty($session))
			{
				$sessions[$session_id] = $this->event_export_session_payload($session);
				$session_ids[$session_id] = $session_id;
			}
		}

		$response = [
			'ok' => true,
			'success' => true,
			'events' => $events,
			'sessions' => array_values($sessions),
			'pagination' => [
				'limit' => $limit,
				'count' => count($events),
				'hasMore' => $has_more,
				'has_more' => $has_more,
				'nextAfterSeq' => !empty($events) ? (int) $events[count($events) - 1]['seq'] : ($after_seq ?? 0),
				'next_after_seq' => !empty($events) ? (int) $events[count($events) - 1]['seq'] : ($after_seq ?? 0),
			],
			'exportedAt' => $this->iso_time(time()),
			'exported_at' => $this->iso_time(time()),
		];

		if (count($sessions) === 1)
		{
			$response['session'] = array_values($sessions)[0];
		}

		if ($include_snapshots)
		{
			$response['snapshots'] = $this->load_event_export_snapshots(array_values($session_ids), $after_seq, $from_seq, $to_seq);
		}

		if ($include_finished_summary)
		{
			$response['finishedSummaries'] = $this->load_event_export_finished_summaries(array_values($session_ids));
			$response['finished_summaries'] = $response['finishedSummaries'];
		}

		return $this->json($response);
	}

	protected function load_event_export_rows(int $session_id, string $room_key, ?int $after_seq, ?int $from_seq, ?int $to_seq, int $limit): array
	{
		$where = $this->event_export_where($session_id, $room_key, $after_seq, $from_seq, $to_seq, 'e', 's');
		$sql = 'SELECT e.id, e.session_id, e.seq, e.game_type, e.actor_user_id, e.request_id,
					e.event_type, e.payload_schema_version, e.payload_json, e.created_at,
					s.room_key, s.status, s.owner_user_id, s.settings_json, s.state_schema_version,
					s.state_version, s.random_audit_json, s.started_at, s.updated_at, s.finished_at
				FROM ' . $this->events_table . ' e
			INNER JOIN ' . $this->sessions_table . ' s
				ON s.id = e.session_id
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY e.session_id ASC, e.seq ASC';
		$result = $this->db->sql_query_limit($sql, $limit);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function event_export_where(int $session_id, string $room_key, ?int $after_seq, ?int $from_seq, ?int $to_seq, string $event_alias, string $session_alias): array
	{
		$where = [];
		if ($session_id > 0)
		{
			$where[] = $event_alias . '.session_id = ' . $session_id;
		}
		if ($room_key !== '')
		{
			$where[] = $session_alias . ".room_key = '" . $this->db->sql_escape(substr($room_key, 0, 64)) . "'";
		}
		if ($after_seq !== null)
		{
			$where[] = $event_alias . '.seq > ' . max(0, $after_seq);
		}
		else if ($from_seq !== null)
		{
			$where[] = $event_alias . '.seq >= ' . max(0, $from_seq);
		}
		if ($to_seq !== null)
		{
			$where[] = $event_alias . '.seq <= ' . max(0, $to_seq);
		}

		return $where;
	}

	protected function load_event_export_session(int $session_id): array
	{
		$sql = 'SELECT id AS session_id, room_key, game_type, status, owner_user_id, settings_json,
				state_schema_version, state_version, random_audit_json, started_at, updated_at, finished_at
			FROM ' . $this->sessions_table . '
			WHERE id = ' . $session_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return is_array($row) ? $row : [];
	}

	protected function event_export_session_payload(array $row): array
	{
		$session_id = (int) ($row['session_id'] ?? $row['id'] ?? 0);
		$settings = $this->json_field_payload((string) ($row['settings_json'] ?? ''));
		$random_audit = $this->json_field_payload((string) ($row['random_audit_json'] ?? ''));

		return [
			'sessionId' => $session_id,
			'session_id' => $session_id,
			'roomKey' => (string) ($row['room_key'] ?? ''),
			'room_key' => (string) ($row['room_key'] ?? ''),
			'gameType' => (string) ($row['game_type'] ?? ''),
			'game_type' => (string) ($row['game_type'] ?? ''),
			'status' => (string) ($row['status'] ?? ''),
			'ownerUserId' => (int) ($row['owner_user_id'] ?? 0),
			'owner_user_id' => (int) ($row['owner_user_id'] ?? 0),
			'settings' => $settings,
			'settings_json' => $settings,
			'stateSchemaVersion' => (int) ($row['state_schema_version'] ?? 1),
			'state_schema_version' => (int) ($row['state_schema_version'] ?? 1),
			'stateVersion' => (int) ($row['state_version'] ?? 0),
			'state_version' => (int) ($row['state_version'] ?? 0),
			'randomAudit' => $random_audit,
			'random_audit_json' => $random_audit,
			'startedAt' => $this->iso_time((int) ($row['started_at'] ?? 0)),
			'started_at' => $this->iso_time((int) ($row['started_at'] ?? 0)),
			'updatedAt' => $this->iso_time((int) ($row['updated_at'] ?? 0)),
			'updated_at' => $this->iso_time((int) ($row['updated_at'] ?? 0)),
			'finishedAt' => $this->iso_time((int) ($row['finished_at'] ?? 0)),
			'finished_at' => $this->iso_time((int) ($row['finished_at'] ?? 0)),
		];
	}

	protected function event_export_event_payload(array $row): array
	{
		$actor_user_id = (int) ($row['actor_user_id'] ?? 0);
		$request_id = (string) ($row['request_id'] ?? '');
		$payload = $this->json_field_payload((string) ($row['payload_json'] ?? ''));

		return [
			'id' => (int) ($row['id'] ?? 0),
			'sessionId' => (int) ($row['session_id'] ?? 0),
			'session_id' => (int) ($row['session_id'] ?? 0),
			'seq' => (int) ($row['seq'] ?? 0),
			'gameType' => (string) ($row['game_type'] ?? ''),
			'game_type' => (string) ($row['game_type'] ?? ''),
			'actorUserId' => $actor_user_id > 0 ? $actor_user_id : null,
			'actor_user_id' => $actor_user_id > 0 ? $actor_user_id : null,
			'requestId' => $request_id !== '' ? $request_id : null,
			'request_id' => $request_id !== '' ? $request_id : null,
			'eventType' => (string) ($row['event_type'] ?? ''),
			'event_type' => (string) ($row['event_type'] ?? ''),
			'payloadSchemaVersion' => (int) ($row['payload_schema_version'] ?? 1),
			'payload_schema_version' => (int) ($row['payload_schema_version'] ?? 1),
			'payload' => $payload,
			'payloadJson' => $payload,
			'payload_json' => $payload,
			'createdAt' => $this->iso_time((int) ($row['created_at'] ?? 0)),
			'created_at' => $this->iso_time((int) ($row['created_at'] ?? 0)),
		];
	}

	protected function load_event_export_snapshots(array $session_ids, ?int $after_seq, ?int $from_seq, ?int $to_seq): array
	{
		$session_ids = array_values(array_unique(array_filter(array_map('intval', $session_ids), static function ($session_id) {
			return $session_id > 0;
		})));
		if (empty($session_ids))
		{
			return [];
		}

		$where = [$this->db->sql_in_set('session_id', $session_ids)];
		if ($after_seq !== null)
		{
			$where[] = 'seq > ' . max(0, $after_seq);
		}
		else if ($from_seq !== null)
		{
			$where[] = 'seq >= ' . max(0, $from_seq);
		}
		if ($to_seq !== null)
		{
			$where[] = 'seq <= ' . max(0, $to_seq);
		}

		$sql = 'SELECT id, session_id, seq, game_type, state_schema_version, state_json, created_at
			FROM ' . $this->snapshots_table . '
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY session_id ASC, seq ASC';
		$result = $this->db->sql_query($sql);
		$snapshots = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$state = $this->json_field_payload((string) ($row['state_json'] ?? ''));
			$snapshots[] = [
				'id' => (int) ($row['id'] ?? 0),
				'sessionId' => (int) ($row['session_id'] ?? 0),
				'session_id' => (int) ($row['session_id'] ?? 0),
				'seq' => (int) ($row['seq'] ?? 0),
				'gameType' => (string) ($row['game_type'] ?? ''),
				'game_type' => (string) ($row['game_type'] ?? ''),
				'stateSchemaVersion' => (int) ($row['state_schema_version'] ?? 1),
				'state_schema_version' => (int) ($row['state_schema_version'] ?? 1),
				'state' => $state,
				'stateJson' => $state,
				'state_json' => $state,
				'createdAt' => $this->iso_time((int) ($row['created_at'] ?? 0)),
				'created_at' => $this->iso_time((int) ($row['created_at'] ?? 0)),
			];
		}
		$this->db->sql_freeresult($result);

		return $snapshots;
	}

	protected function load_event_export_finished_summaries(array $session_ids): array
	{
		$session_ids = array_values(array_unique(array_filter(array_map('intval', $session_ids), static function ($session_id) {
			return $session_id > 0;
		})));
		if (empty($session_ids))
		{
			return [];
		}

		$sql = 'SELECT id, session_id, game_type, room_key, winner_json, score_json, summary_json, finished_at
			FROM ' . $this->finished_summaries_table . '
			WHERE ' . $this->db->sql_in_set('session_id', $session_ids) . '
			ORDER BY session_id ASC';
		$result = $this->db->sql_query($sql);
		$summaries = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$summaries[] = [
				'id' => (int) ($row['id'] ?? 0),
				'sessionId' => (int) ($row['session_id'] ?? 0),
				'session_id' => (int) ($row['session_id'] ?? 0),
				'gameType' => (string) ($row['game_type'] ?? ''),
				'game_type' => (string) ($row['game_type'] ?? ''),
				'roomKey' => (string) ($row['room_key'] ?? ''),
				'room_key' => (string) ($row['room_key'] ?? ''),
				'winner' => $this->json_field_payload((string) ($row['winner_json'] ?? '')),
				'winner_json' => $this->json_field_payload((string) ($row['winner_json'] ?? '')),
				'score' => $this->json_field_payload((string) ($row['score_json'] ?? '')),
				'score_json' => $this->json_field_payload((string) ($row['score_json'] ?? '')),
				'summary' => $this->json_field_payload((string) ($row['summary_json'] ?? '')),
				'summary_json' => $this->json_field_payload((string) ($row['summary_json'] ?? '')),
				'finishedAt' => $this->iso_time((int) ($row['finished_at'] ?? 0)),
				'finished_at' => $this->iso_time((int) ($row['finished_at'] ?? 0)),
			];
		}
		$this->db->sql_freeresult($result);

		return $summaries;
	}

	protected function ensure_lobby_session(string $room_key, string $game_type, int $now): array
	{
		$sql = 'SELECT id, state_version
			FROM ' . $this->sessions_table . "
			WHERE room_key = '" . $this->db->sql_escape($room_key) . "'
				AND status = 'waiting'
			ORDER BY id DESC
			LIMIT 1
			FOR UPDATE";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (is_array($row))
		{
			return [
				'id' => (int) $row['id'],
				'state_version' => (int) $row['state_version'],
			];
		}

		$id = $this->insert_row($this->sessions_table, [
			'room_key' => $room_key,
			'game_type' => $game_type,
			'status' => 'waiting',
			'owner_user_id' => 0,
			'settings_json' => $this->room_default_settings_json($room_key),
			'state_schema_version' => 1,
			'state_version' => 0,
			'random_audit_json' => '',
			'created_at' => $now,
			'started_at' => 0,
			'updated_at' => $now,
			'finished_at' => 0,
		]);

		return [
			'id' => $id,
			'state_version' => 0,
		];
	}

	protected function room_default_settings_json(string $room_key): string
	{
		$sql = 'SELECT default_settings_json
			FROM ' . $this->room_configs_table . "
			WHERE room_key = '" . $this->db->sql_escape($room_key) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$settings_json = (string) $this->db->sql_fetchfield('default_settings_json');
		$this->db->sql_freeresult($result);

		return $settings_json !== '' ? $settings_json : '{}';
	}

	protected function find_event_seq_by_request_id(int $session_id, string $request_id): int
	{
		if ($request_id === '')
		{
			return 0;
		}

		$sql = 'SELECT seq
			FROM ' . $this->events_table . "
			WHERE session_id = " . $session_id . "
				AND request_id = '" . $this->db->sql_escape($request_id) . "'
			ORDER BY id DESC
			LIMIT 1
			FOR UPDATE";
		$result = $this->db->sql_query($sql);
		$seq = (int) $this->db->sql_fetchfield('seq');
		$this->db->sql_freeresult($result);

		return $seq;
	}

	protected function apply_lobby_membership(int $session_id, string $room_key, $membership, int $now): void
	{
		if (!is_array($membership))
		{
			return;
		}

		$action = $this->string_value($membership, 'action', 'action', 32, '');
		if ($action === '')
		{
			return;
		}

		$user_id = $this->required_int($membership, 'userId', 'user_id');
		$member_id = $this->find_current_member_id($session_id, $room_key, $user_id);

		switch ($action)
		{
			case 'upsert':
				$row = [
					'role' => $this->string_value($membership, 'role', 'role', 32, 'observer'),
					'seat_index' => $this->nullable_int_value($membership, 'seatIndex', 'seat_index'),
					'watched_seat_index' => $this->nullable_int_value($membership, 'watchedSeatIndex', 'watched_seat_index'),
					'connected' => (int) ((bool) ($membership['connected'] ?? 1)),
					'last_seen_at' => $now,
				];
				if ($member_id > 0)
				{
					$this->db->sql_query('UPDATE ' . $this->room_members_table . '
						SET ' . $this->db->sql_build_array('UPDATE', $row) . '
						WHERE id = ' . $member_id);
				}
				else
				{
					$row = array_merge([
						'session_id' => $session_id,
						'room_key' => $room_key,
						'user_id' => $user_id,
						'joined_at' => $now,
						'left_at' => 0,
					], $row);
					$this->insert_row($this->room_members_table, $row);
				}
			break;

			case 'close':
				if ($member_id > 0)
				{
					$this->db->sql_query('UPDATE ' . $this->room_members_table . '
						SET ' . $this->db->sql_build_array('UPDATE', [
							'connected' => 0,
							'last_seen_at' => $now,
							'left_at' => $now,
						]) . '
						WHERE id = ' . $member_id);
				}
			break;

			case 'touch':
				if ($member_id > 0)
				{
					$this->db->sql_query('UPDATE ' . $this->room_members_table . '
						SET last_seen_at = ' . $now . '
						WHERE id = ' . $member_id);
				}
			break;

			case 'watch':
				if ($member_id > 0)
				{
					$this->db->sql_query('UPDATE ' . $this->room_members_table . '
						SET ' . $this->db->sql_build_array('UPDATE', [
							'watched_seat_index' => $this->nullable_int_value($membership, 'watchedSeatIndex', 'watched_seat_index'),
							'last_seen_at' => $now,
						]) . '
						WHERE id = ' . $member_id);
				}
			break;

			case 'disconnect':
				if ($member_id > 0)
				{
					$this->db->sql_query('UPDATE ' . $this->room_members_table . '
						SET ' . $this->db->sql_build_array('UPDATE', [
							'connected' => 0,
							'last_seen_at' => $now,
						]) . '
						WHERE id = ' . $member_id);
				}
			break;

			default:
				throw new \InvalidArgumentException('invalid_membership_action');
		}
	}

	protected function find_current_member_id(int $session_id, string $room_key, int $user_id): int
	{
		$sql = 'SELECT id
			FROM ' . $this->room_members_table . "
			WHERE session_id = " . $session_id . "
				AND room_key = '" . $this->db->sql_escape($room_key) . "'
				AND user_id = " . $user_id . '
				AND left_at = 0
			ORDER BY id DESC
			LIMIT 1
			FOR UPDATE';
		$result = $this->db->sql_query($sql);
		$id = (int) $this->db->sql_fetchfield('id');
		$this->db->sql_freeresult($result);

		return $id;
	}

	protected function update_snapshot(int $session_id, int $seq, array $row): void
	{
		$sql = 'UPDATE ' . $this->snapshots_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $row) . '
			WHERE session_id = ' . $session_id . '
				AND seq = ' . $seq;
		$this->db->sql_query($sql);
	}

	protected function insert_row(string $table, array $row): int
	{
		$insert = $this->try_insert_row($table, $row);
		if (!$insert['inserted'])
		{
			throw new \RuntimeException('insert_failed');
		}

		return (int) $insert['id'];
	}

	protected function try_insert_row(string $table, array $row): array
	{
		$sql = 'INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', $row);
		$this->db->sql_return_on_error(true);
		$result = $this->db->sql_query($sql);
		$error_triggered = $this->db->get_sql_error_triggered();
		$error = $error_triggered ? $this->db->get_sql_error_returned() : [];
		$id = (!$error_triggered && $result !== false) ? (int) $this->db->sql_nextid() : 0;
		$this->db->sql_return_on_error(false);

		return [
			'inserted' => !$error_triggered && $result !== false,
			'id' => $id,
			'error' => is_array($error) ? $error : [],
		];
	}

	protected function is_duplicate_insert(array $insert): bool
	{
		$error = is_array($insert['error'] ?? null) ? $insert['error'] : [];
		$code = (string) ($error['code'] ?? '');
		$message = strtolower((string) ($error['message'] ?? ''));

		return in_array($code, ['1062', '19', '23505'], true)
			|| str_contains($message, 'duplicate')
			|| str_contains($message, 'unique constraint');
	}

	protected function upsert_row(string $table, array $unique, array $row): int
	{
		$where = [];
		foreach ($unique as $column => $value)
		{
			$where[] = $column . ' = ' . (is_int($value) ? $value : "'" . $this->db->sql_escape((string) $value) . "'");
		}

		$sql = 'SELECT id
			FROM ' . $table . '
			WHERE ' . implode(' AND ', $where);
		$result = $this->db->sql_query_limit($sql, 1);
		$id = (int) $this->db->sql_fetchfield('id');
		$this->db->sql_freeresult($result);

		if ($id > 0)
		{
			$this->db->sql_query('UPDATE ' . $table . '
				SET ' . $this->db->sql_build_array('UPDATE', $row) . '
				WHERE id = ' . $id);
			return 1;
		}

		$insert = $this->try_insert_row($table, $row);
		if (!$insert['inserted'])
		{
			if (!$this->is_duplicate_insert($insert))
			{
				throw new \RuntimeException('upsert_failed');
			}

			$this->db->sql_query('UPDATE ' . $table . '
				SET ' . $this->db->sql_build_array('UPDATE', $row) . '
				WHERE ' . implode(' AND ', $where));
		}

		return 1;
	}

	protected function raw_body(): string
	{
		$body = file_get_contents('php://input');
		return is_string($body) ? $body : '';
	}

	protected function proxy_request_paths(): array
	{
		$request_uri = (string) $this->request->server('REQUEST_URI', '/');
		$path = (string) (parse_url($request_uri, PHP_URL_PATH) ?: '/');
		$query = parse_url($request_uri, PHP_URL_QUERY);
		$query_suffix = is_string($query) && $query !== '' ? '?' . $query : '';
		$paths = [$path . $query_suffix];

		$script_name = (string) $this->request->server('SCRIPT_NAME', '');
		if ($script_name !== '' && str_starts_with($path, $script_name))
		{
			$paths[] = $this->normalize_proxy_path(substr($path, strlen($script_name)) ?: '/') . $query_suffix;
		}

		$paths[] = $this->normalize_proxy_path(preg_replace('#^/app\.php(?=/|$)#', '', $path) ?? $path) . $query_suffix;

		return array_values(array_unique($paths));
	}

	protected function normalize_proxy_path(string $path): string
	{
		$path = $path === '' ? '/' : $path;
		return str_starts_with($path, '/') ? $path : '/' . $path;
	}

	protected function require_json_object(string $body): ?array
	{
		$data = $this->decode_body($body);
		if (!is_array($data))
		{
			return null;
		}

		return empty($data) || array_keys($data) !== range(0, count($data) - 1) ? $data : null;
	}

	protected function decode_body(string $body)
	{
		if (trim($body) === '')
		{
			return [];
		}

		return json_decode($body, true);
	}

	protected function batch_rows(array $data, string $plural_key, string $single_key): array
	{
		$rows = $data[$plural_key] ?? null;
		if ($rows === null && isset($data[$single_key]))
		{
			$rows = [$data[$single_key]];
		}
		if ($rows === null)
		{
			$rows = [$data];
		}

		return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
	}

	protected function required_string(array $data, string $camel_key, string $snake_key, int $max_length): string
	{
		$value = trim((string) ($data[$camel_key] ?? $data[$snake_key] ?? ''));
		if ($value === '')
		{
			throw new \InvalidArgumentException('missing_' . $snake_key);
		}

		return substr($value, 0, $max_length);
	}

	protected function string_value(array $data, string $camel_key, string $snake_key, int $max_length, string $default): string
	{
		$value = trim((string) ($data[$camel_key] ?? $data[$snake_key] ?? $default));
		return substr($value, 0, $max_length);
	}

	protected function nullable_string_value(array $data, string $camel_key, string $snake_key, int $max_length): string
	{
		$value = $data[$camel_key] ?? $data[$snake_key] ?? null;
		if ($value === null || trim((string) $value) === '')
		{
			return '';
		}

		return substr(trim((string) $value), 0, $max_length);
	}

	protected function required_int(array $data, string $camel_key, string $snake_key): int
	{
		$value = (int) ($data[$camel_key] ?? $data[$snake_key] ?? 0);
		if ($value <= 0)
		{
			throw new \InvalidArgumentException('missing_' . $snake_key);
		}

		return $value;
	}

	protected function int_value(array $data, string $camel_key, string $snake_key, int $default): int
	{
		return (int) ($data[$camel_key] ?? $data[$snake_key] ?? $default);
	}

	protected function nullable_int_value(array $data, string $camel_key, string $snake_key): int
	{
		$value = $data[$camel_key] ?? $data[$snake_key] ?? null;
		return $value === null ? 0 : (int) $value;
	}

	protected function json_value(array $data, string $camel_key, string $snake_key, $default): string
	{
		$value = $data[$camel_key] ?? $data[$snake_key] ?? $default;
		if (is_string($value) && $this->is_valid_json($value))
		{
			return $value;
		}

		return $this->encode_json_value($value);
	}

	protected function nullable_json_value(array $data, string $camel_key, string $snake_key): string
	{
		if (!array_key_exists($camel_key, $data) && !array_key_exists($snake_key, $data))
		{
			return '';
		}

		$value = $data[$camel_key] ?? $data[$snake_key] ?? null;
		if ($value === null)
		{
			return '';
		}

		return $this->json_value([$camel_key => $value], $camel_key, $snake_key, null);
	}

	protected function time_value(array $data, string $camel_key, string $snake_key, int $default): int
	{
		if (!array_key_exists($camel_key, $data) && !array_key_exists($snake_key, $data))
		{
			return $default;
		}

		return $this->parse_time($data[$camel_key] ?? $data[$snake_key]);
	}

	protected function nullable_time_value(array $data, string $camel_key, string $snake_key): int
	{
		if (!array_key_exists($camel_key, $data) && !array_key_exists($snake_key, $data))
		{
			return 0;
		}

		$value = $data[$camel_key] ?? $data[$snake_key];
		return $value === null || $value === '' ? 0 : $this->parse_time($value);
	}

	protected function parse_time($value): int
	{
		if (is_numeric($value))
		{
			return (int) $value;
		}

		$time = strtotime((string) $value);
		return $time === false ? time() : $time;
	}

	protected function copy_string_update(array &$updates, array $data, string $camel_key, string $snake_key, int $max_length): void
	{
		if (array_key_exists($camel_key, $data) || array_key_exists($snake_key, $data))
		{
			$updates[$snake_key] = $this->string_value($data, $camel_key, $snake_key, $max_length, '');
		}
	}

	protected function copy_int_update(array &$updates, array $data, string $camel_key, string $snake_key): void
	{
		if (array_key_exists($camel_key, $data) || array_key_exists($snake_key, $data))
		{
			$updates[$snake_key] = $this->int_value($data, $camel_key, $snake_key, 0);
		}
	}

	protected function copy_nullable_int_update(array &$updates, array $data, string $camel_key, string $snake_key): void
	{
		if (array_key_exists($camel_key, $data) || array_key_exists($snake_key, $data))
		{
			$updates[$snake_key] = $this->nullable_int_value($data, $camel_key, $snake_key);
		}
	}

	protected function copy_json_update(array &$updates, array $data, string $camel_key, string $snake_key): void
	{
		if (array_key_exists($camel_key, $data) || array_key_exists($snake_key, $data))
		{
			$updates[$snake_key] = $this->json_value($data, $camel_key, $snake_key, new \stdClass());
		}
	}

	protected function copy_nullable_json_update(array &$updates, array $data, string $camel_key, string $snake_key): void
	{
		if (array_key_exists($camel_key, $data) || array_key_exists($snake_key, $data))
		{
			$updates[$snake_key] = $this->nullable_json_value($data, $camel_key, $snake_key);
		}
	}

	protected function copy_time_update(array &$updates, array $data, string $camel_key, string $snake_key): void
	{
		if (array_key_exists($camel_key, $data) || array_key_exists($snake_key, $data))
		{
			$updates[$snake_key] = $this->time_value($data, $camel_key, $snake_key, time());
		}
	}

	protected function copy_nullable_time_update(array &$updates, array $data, string $camel_key, string $snake_key): void
	{
		if (array_key_exists($camel_key, $data) || array_key_exists($snake_key, $data))
		{
			$updates[$snake_key] = $this->nullable_time_value($data, $camel_key, $snake_key);
		}
	}

	protected function encode_json_value($value): string
	{
		$json = json_encode($value, self::JSON_FLAGS);
		return $json === false ? '{}' : $json;
	}

	protected function decode_json_field(string $value, $default)
	{
		$decoded = json_decode($value, true);
		return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
	}

	protected function json_field_payload(string $value)
	{
		if (trim($value) === '')
		{
			return new \stdClass();
		}

		$decoded = json_decode($value, true);
		if (json_last_error() !== JSON_ERROR_NONE)
		{
			return new \stdClass();
		}

		return (is_array($decoded) && empty($decoded)) ? new \stdClass() : $decoded;
	}

	protected function is_valid_json(string $value): bool
	{
		json_decode($value, true);
		return json_last_error() === JSON_ERROR_NONE;
	}

	protected function query_int(string $camel_key, string $snake_key, int $default): int
	{
		return (int) $this->request->variable($camel_key, $this->request->variable($snake_key, $default));
	}

	protected function query_optional_int(string $camel_key, string $snake_key): ?int
	{
		$raw = $this->request->variable($camel_key, $this->request->variable($snake_key, ''));
		if ((string) $raw === '')
		{
			return null;
		}

		return (int) $raw;
	}

	protected function query_string(string $camel_key, string $snake_key, string $default): string
	{
		return (string) $this->request->variable($camel_key, $this->request->variable($snake_key, $default));
	}

	protected function query_bool(string $camel_key, string $snake_key, bool $default): bool
	{
		$raw = $this->request->variable($camel_key, $this->request->variable($snake_key, $default ? '1' : '0'));
		if (is_bool($raw))
		{
			return $raw;
		}

		return in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true);
	}

	protected function string_list($value): array
	{
		if (!is_array($value))
		{
			return [];
		}

		return array_values(array_filter(array_map('strval', $value), static function ($item) {
			return $item !== '';
		}));
	}

	protected function get_int_config(string $name, int $default, int $min, int $max): int
	{
		return max($min, min($max, (int) ($this->config[$name] ?? $default)));
	}

	protected function iso_time(int $time): string
	{
		return $time > 0 ? gmdate('c', $time) : '';
	}

	protected function json(array $data, int $status = 200): JsonResponse
	{
		return new JsonResponse($data, $status, [
			'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
			'Pragma' => 'no-cache',
		]);
	}

	protected function json_error(string $code, string $message, int $status = 400): JsonResponse
	{
		return $this->json([
			'ok' => false,
			'success' => false,
			'code' => $code,
			'message' => $message,
			'retryable' => $status >= 500,
			'error' => [
				'code' => $code,
				'message' => $message,
				'retryable' => $status >= 500,
			],
		], $status);
	}
}
