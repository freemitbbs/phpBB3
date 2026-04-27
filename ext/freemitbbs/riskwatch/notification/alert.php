<?php

namespace freemitbbs\riskwatch\notification;

class alert extends \phpbb\notification\type\base
{
	private const MAX_NOTIFICATION_ITEM_ID = 16777215;

	/** @var \phpbb\user_loader */
	protected $user_loader;

	static public $notification_option = [
		'id' => 'freemitbbs_riskwatch_alert',
		'lang' => 'NOTIFICATION_TYPE_RISKWATCH_ALERT',
		'group' => 'NOTIFICATION_GROUP_ADMINISTRATION',
	];

	public function get_type()
	{
		return 'freemitbbs.riskwatch.notification.type.alert';
	}

	public function set_user_loader(\phpbb\user_loader $user_loader): void
	{
		$this->user_loader = $user_loader;
	}

	public function is_available()
	{
		return $this->auth->acl_get('a_board');
	}

	public static function get_item_id($data)
	{
		if (!empty($data['alert_item_id']))
		{
			return self::normalize_item_id((int) $data['alert_item_id']);
		}

		$seed = (string) (($data['risk_user_id'] ?? 0) . '|' . ($data['risk_level'] ?? 0) . '|' . ($data['alert_time'] ?? time()));

		return self::normalize_item_id((int) hexdec(hash('crc32b', $seed)));
	}

	private static function normalize_item_id(int $item_id): int
	{
		$item_id = abs($item_id) % self::MAX_NOTIFICATION_ITEM_ID;

		return $item_id > 0 ? $item_id : self::MAX_NOTIFICATION_ITEM_ID;
	}

	public static function get_item_parent_id($data)
	{
		return (int) ($data['risk_user_id'] ?? 0);
	}

	public function find_users_for_notification($data, $options = [])
	{
		$options = array_merge([
			'ignore_users' => [],
		], $options);

		$admin_ary = $this->auth->acl_get_list(false, 'a_board', false);
		$users = (!empty($admin_ary[0]['a_board'])) ? $admin_ary[0]['a_board'] : [];

		$sql = 'SELECT user_id
			FROM ' . USERS_TABLE . '
			WHERE user_type = ' . USER_FOUNDER;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		if (empty($users))
		{
			return [];
		}

		$users = array_values(array_unique(array_filter(array_map('intval', $users), static function ($user_id) {
			return $user_id > ANONYMOUS;
		})));

		return $this->check_user_notification_options($users, array_merge($options, [
			'item_type' => static::$notification_option['id'],
		]));
	}

	public function get_avatar()
	{
		return $this->user_loader->get_avatar($this->get_data('risk_user_id'), false, true);
	}

	public function get_title()
	{
		$risk_user_id = (int) $this->get_data('risk_user_id');
		$username = $this->user_loader->get_username($risk_user_id, 'no_profile');

		return $this->language->lang(
			'NOTIFICATION_RISKWATCH_ALERT_TITLE',
			$username,
			(string) $this->get_data('risk_level_label'),
			(int) $this->get_data('risk_score')
		);
	}

	public function get_reference()
	{
		return $this->language->lang(
			'NOTIFICATION_RISKWATCH_ALERT_REFERENCE',
			(int) $this->get_data('warning_points'),
			(int) $this->get_data('report_points'),
			(int) $this->get_data('unapproved_points'),
			(int) $this->get_data('login_points'),
			(int) $this->get_data('ban_points'),
			(int) $this->get_data('manual_adjustment')
		);
	}

	public function get_email_template()
	{
		return false;
	}

	public function get_email_template_variables()
	{
		return [];
	}

	public function get_url()
	{
		$risk_user_id = (int) $this->get_data('risk_user_id');

		return append_sid($this->phpbb_root_path . 'memberlist.' . $this->php_ext, 'mode=viewprofile&u=' . $risk_user_id);
	}

	public function users_to_query()
	{
		return [(int) $this->get_data('risk_user_id')];
	}

	public function create_insert_array($data, $pre_create_data = [])
	{
		$this->set_data('risk_user_id', (int) ($data['risk_user_id'] ?? 0));
		$this->set_data('risk_score', (int) ($data['risk_score'] ?? 0));
		$this->set_data('risk_level', (int) ($data['risk_level'] ?? 0));
		$this->set_data('risk_level_label', (string) ($data['risk_level_label'] ?? 'Normal'));
		$this->set_data('warning_points', (int) ($data['warning_points'] ?? 0));
		$this->set_data('report_points', (int) ($data['report_points'] ?? 0));
		$this->set_data('unapproved_points', (int) ($data['unapproved_points'] ?? 0));
		$this->set_data('login_points', (int) ($data['login_points'] ?? 0));
		$this->set_data('ban_points', (int) ($data['ban_points'] ?? 0));
		$this->set_data('manual_adjustment', (int) ($data['manual_adjustment'] ?? 0));

		parent::create_insert_array($data, $pre_create_data);
	}
}
