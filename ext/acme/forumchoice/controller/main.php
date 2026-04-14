<?php

namespace acme\forumchoice\controller;

use phpbb\db\driver\driver_interface;
use phpbb\db\tools\tools_interface;
use phpbb\request\request_interface;
use phpbb\user;

class main
{
	protected $db;
	protected $db_tools;
	protected $request;
	protected $user;
	protected $table_prefix;
	protected $root_path;
	protected $php_ext;
	protected $favorite_table_available;

	public function __construct(driver_interface $db, tools_interface $db_tools, request_interface $request, user $user, $table_prefix, $root_path, $php_ext)
	{
		$this->db = $db;
		$this->db_tools = $db_tools;
		$this->request = $request;
		$this->user = $user;
		$this->table_prefix = $table_prefix;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->favorite_table_available = null;
	}

	public function toggle($forum_id)
	{
		$forum_id = (int) $forum_id;
		$user_id = (int) $this->user->data['user_id'];
		$action = $this->request->variable('action', '');
		$redirect_url = $this->request->variable('redirect', append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . $forum_id));
		$success = false;

		if ($user_id == ANONYMOUS || !$forum_id)
		{
			throw new \phpbb\exception\http_exception(403, 'NO_AUTH_OPERATION');
		}

		if (!check_link_hash($this->request->variable('hash', ''), 'favorite_forum_' . $forum_id))
		{
			throw new \phpbb\exception\http_exception(403, 'FORM_INVALID');
		}

		if (!$this->favorite_table_exists())
		{
			$is_favorite = false;
		}
		else
		{
			if ($action !== 'add' && $action !== 'remove')
			{
				$action = $this->is_favorite_forum($user_id, $forum_id) ? 'remove' : 'add';
			}

			if ($action === 'remove')
			{
				$is_favorite = $this->remove_favorite_forum($user_id, $forum_id);
				$success = !$is_favorite;
			}
			else
			{
				$is_favorite = $this->add_favorite_forum($user_id, $forum_id);
				$success = $is_favorite;
			}
		}

		if ($this->request->is_ajax())
		{
			$this->user->add_lang_ext('acme/forumchoice', 'common');
			$json_response = new \phpbb\json_response();
			$json_response->send([
				'success'     => $success,
				'is_favorite' => $is_favorite,
				'text'        => $is_favorite ? $this->user->lang['REMOVE_FAVORITE'] : $this->user->lang['ADD_FAVORITE'],
				'REFRESH_DATA' => [
					'time' => 0,
					'url'  => $redirect_url,
				],
			]);
		}

		redirect($redirect_url);
	}

	protected function favorite_table_exists()
	{
		if ($this->favorite_table_available === null)
		{
			$this->favorite_table_available = $this->db_tools->sql_table_exists($this->get_favorite_table());
		}

		return $this->favorite_table_available;
	}

	protected function get_favorite_table()
	{
		return $this->table_prefix . 'user_favorite_forums';
	}

	protected function is_favorite_forum($user_id, $forum_id)
	{
		$sql = 'SELECT forum_id FROM ' . $this->get_favorite_table() . '
			WHERE user_id = ' . (int) $user_id . '
				AND forum_id = ' . (int) $forum_id;
		$result = $this->safe_sql_query($sql);
		if (!$result)
		{
			return false;
		}

		$is_favorite = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $is_favorite;
	}

	protected function add_favorite_forum($user_id, $forum_id)
	{
		$sql = 'INSERT INTO ' . $this->get_favorite_table() . ' ' . $this->db->sql_build_array('INSERT', [
			'user_id'  => (int) $user_id,
			'forum_id' => (int) $forum_id,
		]);

		if ($this->safe_sql_write($sql))
		{
			return true;
		}

		return $this->is_favorite_forum($user_id, $forum_id);
	}

	protected function remove_favorite_forum($user_id, $forum_id)
	{
		$sql = 'DELETE FROM ' . $this->get_favorite_table() . '
			WHERE user_id = ' . (int) $user_id . '
				AND forum_id = ' . (int) $forum_id;

		if ($this->safe_sql_write($sql))
		{
			return false;
		}

		return $this->is_favorite_forum($user_id, $forum_id);
	}

	protected function safe_sql_query($sql)
	{
		$this->db->sql_return_on_error(true);
		$result = $this->db->sql_query($sql);
		$errored = $this->db->get_sql_error_triggered();
		$this->db->sql_return_on_error(false);

		return $errored ? false : $result;
	}

	protected function safe_sql_write($sql)
	{
		$this->db->sql_return_on_error(true);
		$this->db->sql_query($sql);
		$errored = $this->db->get_sql_error_triggered();
		$this->db->sql_return_on_error(false);

		return !$errored;
	}
}
