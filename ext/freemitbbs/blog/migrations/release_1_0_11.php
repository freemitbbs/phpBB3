<?php

namespace freemitbbs\blog\migrations;

class release_1_0_11 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\blog\migrations\release_1_0_10',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['freemitbbs_blog_block_direct_sid_anon', '1']],
			['config.add', ['freemitbbs_blog_china_safe_mode', '1']],
			['config.add', ['freemitbbs_blog_china_safe_action', '404']],
			['config.add', ['freemitbbs_blog_china_safe_countries', 'CN']],
			['config.add', ['freemitbbs_blog_china_safe_country_headers', 'CF-IPCountry,CloudFront-Viewer-Country,X-Country-Code']],
			['config.add', ['freemitbbs_blog_china_safe_cidrs', '']],
			['config.add', ['freemitbbs_blog_china_safe_topic_ids', '']],
			['config.add', ['freemitbbs_blog_china_safe_keywords', '六四,反共,反华,中共,中国,美国,日本,印度,习近平,毛泽东,邓小平,法西斯,军国主义,靖国神社,共产党,华为,抗美']],
			['config.update', ['freemitbbs_blog_version', '1.0.11']],
		];
	}
}
