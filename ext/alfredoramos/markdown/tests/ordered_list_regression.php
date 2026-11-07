<?php

// Run from the board root: php ext/alfredoramos/markdown/tests/ordered_list_regression.php
$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';
require $root . '/phpbb/textformatter/cache_interface.php';
require $root . '/phpbb/textformatter/s9e/factory.php';
require __DIR__ . '/../event/listener.php';

// Use phpBB's actual list definitions and style template, loaded before Litedown.
$factory = new class extends \phpbb\textformatter\s9e\factory
{
	public function __construct()
	{
	}

	public function lists($configurator, $template)
	{
		$templates = $this->extract_templates($template);
		foreach (['list', 'li'] as $name)
		{
			$configurator->BBCodes->addCustom(
				$this->default_definitions[$name],
				str_replace('{LIST_TYPE}', '{HASHMAP}', $templates[$name])
			);
		}
	}
};

function make_configurator($factory, $root)
{
	$configurator = new \s9e\TextFormatter\Configurator();
	$configurator->rendering->engine = 'PHP';
	$configurator->rendering->engine->enableQuickRenderer = true;
	$configurator->rootRules->enableAutoLineBreaks();
	$factory->lists($configurator, file_get_contents($root . '/styles/prosilver/template/bbcode.html'));
	return $configurator;
}

function list_markers($html)
{
	$dom = new \DOMDocument();
	$dom->loadHTML('<meta charset="utf-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
	$markers = [];
	foreach ($dom->getElementsByTagName('ol') as $list)
	{
		$markers[] = $list->hasAttribute('start') ? (int) $list->getAttribute('start') : 1;
	}
	return $markers;
}

$legacy = make_configurator($factory, $root);
$legacy->Litedown;
$legacy_objects = $legacy->finalize();

$reflection = new \ReflectionClass(\alfredoramos\markdown\event\listener::class);
$listener = $reflection->newInstanceWithoutConstructor();
$enabled = $reflection->getProperty('markdown_enabled');
if (PHP_VERSION_ID < 80100)
{
	$enabled->setAccessible(true);
}
$enabled->setValue($listener, true);
$fixed = make_configurator($factory, $root);
$listener->configure_markdown(['configurator' => $fixed]);
$objects = $fixed->finalize();

$headings = [];
foreach (['路线怎么安排', '旅行社怎么找', '司机兼导游', '为什么选十月', '交通和费用', '自选项目', '健康和防蚊准备', '行李和摄影', '酒店和上网', '购物', '入境手续'] as $i => $heading)
{
	$headings[] = ($i + 1) . '. ' . $heading . "\n\n说明文字。";
}

$cases = [
	'eleven separated Chinese headings' => [implode("\n\n", $headings), range(1, 11)],
	'list starts above one' => ["7. Seven\n8. Eight", [7]],
	'zero start' => ["0. Zero\n1. One", [0]],
	'nested ordered lists' => ["3. Outer\n    8. Inner\n    9. Next\n4. Outer next", [3, 8]],
	'loose list' => ["5. Five\n\n6. Six", [5]],
	'ordinary ordered list' => ["1. One\n2. Two\n3. Three", [1]],
	'BBCode decimal list' => ['[list=1][*]One[*]Two[/list]', [1]],
	'number inside a BBCode list item' => ['[list=1][*]9. Literal number[*]Ten[/list]', [1]],
	'BBCode alphabetic list' => ['[list=a][*]One[*]Two[/list]', [1]],
	'BBCode roman list' => ['[list=i][*]One[*]Two[/list]', [1]],
	'unordered list' => ["- One\n- Two", []],
	'BBCode unordered list' => ['[list][*]One[*]Two[/list]', []],
	'escaped number remains literal' => ['2\\. Not a list', []],
];

$checks = 0;
foreach ($cases as $name => [$source, $expected])
{
	foreach (['new post' => $objects['parser'], 'existing post' => $legacy_objects['parser']] as $mode => $parser)
	{
		$xml = $parser->parse($source);
		$event = new \ArrayObject(['xml' => $xml]);
		$listener->restore_list_start($event);
		$html = $objects['renderer']->render($event['xml']);
		$actual = list_markers($html);
		if ($actual !== $expected)
		{
			throw new \RuntimeException($name . ' (' . $mode . '): expected ' . json_encode($expected) . ', got ' . json_encode($actual) . "\n" . $xml . "\n" . $html);
		}
		++$checks;
	}
}

$xml = $objects['parser']->parse('7. Seven');
if (strpos($xml, 'start="7"') === false)
{
	throw new \RuntimeException('Newly parsed lists must retain the start attribute.');
}
++$checks;

// A stored, explicit start must take priority over the original marker.
$event = new \ArrayObject(['xml' => '<r><LIST start="9" type="decimal"><LI><s>2. </s>Two</LI></LIST></r>']);
$listener->restore_list_start($event);
$html = $objects['renderer']->render($event['xml']);
if (list_markers($html) !== [9])
{
	throw new \RuntimeException('Explicit start was overridden by the source marker.');
}
++$checks;

echo 'Passed ' . $checks . " ordered list regression checks.\n";
