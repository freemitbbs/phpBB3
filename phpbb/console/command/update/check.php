<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\console\command\update;

use phpbb\config\config;
use phpbb\language\language;
use phpbb\user;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class check extends \phpbb\console\command\command
{
	/** @var config */
	protected $config;

	/** @var \Symfony\Component\DependencyInjection\ContainerBuilder */
	protected $phpbb_container;

	/**
	 * @var language
	 */
	private $language;

	/**
	* Construct method
	*/
	public function __construct(user $user, config $config, ContainerInterface $phpbb_container, language $language)
	{
		$this->config = $config;
		$this->phpbb_container = $phpbb_container;
		$this->language = $language;

		$this->language->add_lang(array('acp/common', 'acp/extensions'));

		parent::__construct($user);
	}

	/**
	* Configures the service.
	*
	* Sets the name and description of the command.
	*
	* @return null
	*/
	protected function configure()
	{
		$this
			->setName('update:check')
			->setDescription($this->language->lang('CLI_DESCRIPTION_UPDATE_CHECK'))
			->addArgument('ext-name', InputArgument::OPTIONAL, $this->language->lang('CLI_DESCRIPTION_UPDATE_CHECK_ARGUMENT_1'))
			->addOption('stability', null, InputOption::VALUE_REQUIRED, $this->language->lang('CLI_DESCRIPTION_UPDATE_CHECK_OPTION_STABILITY'))
			->addOption('cache', 'c', InputOption::VALUE_NONE, $this->language->lang('CLI_DESCRIPTION_UPDATE_CHECK_OPTION_CACHE'))
		;
	}

	/**
	* Executes the command.
	*
	* Checks if an update is available.
	* If at least one is available, a message is printed and if verbose mode is set the list of possible updates is printed.
	* If their is none, nothing is printed unless verbose mode is set.
	*
	* @param InputInterface $input Input stream, used to get the options.
	* @param OutputInterface $output Output stream, used to print messages.
	* @return int 0 if the board is up to date, 1 if it is not and 2 if an error occurred.
	* @throws \RuntimeException
	*/
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);

		$io->success($this->language->lang('VERSIONCHECK_DISABLED'));
		return 0;
	}

	/**
	 * Check if a given extension is up to date
	 *
	 * @param InputInterface	$input		Input stream, used to get the options.
	 * @param SymfonyStyle		$io			IO handler, for formatted and unified IO
	 * @param string			$stability	Force a given stability
	 * @param bool				$recheck	Disallow the use of the cache
	 * @param string			$ext_name	The extension name
	 * @return int
	 */
	protected function check_ext(InputInterface $input, SymfonyStyle $io, $stability, $recheck, $ext_name)
	{
		$io->success($this->language->lang('VERSIONCHECK_DISABLED'));
		return 0;
	}

	/**
	 * Check if the core is up to date
	 *
	 * @param InputInterface	$input		Input stream, used to get the options.
	 * @param SymfonyStyle		$io			IO handler, for formatted and unified IO
	 * @param string			$stability	Force a given stability
	 * @param bool				$recheck	Disallow the use of the cache
	 * @return int
	 */
	protected function check_core(InputInterface $input, SymfonyStyle $io, $stability, $recheck)
	{
		$io->success($this->language->lang('VERSIONCHECK_DISABLED'));
		return 0;
	}

	/**
	* Check if all the available extensions are up to date
	*
	* @param SymfonyStyle	$io			IO handler, for formatted and unified IO
	* @param string			$stability	Stability specifier string
	* @param bool			$recheck	Disallow the use of the cache
	* @return int
	*/
	protected function check_all_ext(SymfonyStyle $io, $stability, $recheck)
	{
		$io->success($this->language->lang('VERSIONCHECK_DISABLED'));
		return 0;
	}

}
