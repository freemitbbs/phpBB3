<?php

namespace freemitbbs\blog\migrations;

class release_1_0_10 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\blog\migrations\release_1_0_9',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'enable_similar_topics_cache']]],
			['config.update', ['freemitbbs_blog_version', '1.0.10']],
		];
	}

	public function enable_similar_topics_cache(): void
	{
		if ($this->config->offsetExists('similar_topics_cache')
			&& (int) $this->config['similar_topics_cache'] <= 0)
		{
			$this->config->set('similar_topics_cache', '3600');
		}
	}
}
