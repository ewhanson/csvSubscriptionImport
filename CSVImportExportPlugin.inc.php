<?php

/**
 * @file plugins/importexport/csv/CSVImportExportPlugin.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CSVImportExportPlugin
 * @ingroup plugins_importexport_csv
 *
 * @brief CSV import/export plugin
 */

class CSVImportExportPlugin extends ImportExportPlugin
{

	function register($category, $path, $mainContextId = null)
	{
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * @inheritDoc
	 */
	function executeCLI($scriptName, &$args)
	{
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);

		$filename = array_shift($args);
		$journalPath = array_shift($args);

		if (!$filename || !$journalPath) {
			$this->usage($scriptName);
			exit();
		}

		if (!file_exists($filename)) {
			echo __('plugins.importexport.csv.fileDoesNotExist', array('filename' => $filename)) . "\n";
			exit();
		}

		$data = [];
		$this->csv_to_array($filename, $data);

		$this->updateUsersSubscriptionStatus($data, $journalPath);

	}

	/**
	 * @inheritDoc
	 */
	function usage($scriptName)
	{
		echo __('plugins.importexport.csv.cliUsage', [
			'scriptName' => $scriptName,
			'pluginName' => $this->getName(),
		]) . PHP_EOL;
	}

	/**
	 * @inheritDoc
	 */
	function getName()
	{
		return 'CSVImportExportPlugin';
	}

	/**
	 * @inheritDoc
	 */
	function getDisplayName()
	{
		return __('plugins.importexport.csv.displayName');
	}

	/**
	 * @inheritDoc
	 */
	function getDescription()
	{
		return __('plugins.importexport.native.description');
	}

	public function csv_to_array($filePath, array &$dataOutput): bool
	{
		if (!file_exists($filePath) || !is_readable($filePath) || is_dir($filePath))
			return false;

		$header = null;

		if (($handle = fopen($filePath, 'r')) !== false) {
			while (($row = fgetcsv($handle, 10000)) !== false) {
				$row = $this->_removeZeroWidthSpaces($row);
				$row = array_map('trim', $row);
				if (!$header) {
					$header = $row;
				} else {

					$dataOutput[] = array_combine($header, $row);
				}
			}
			fclose($handle);
		}

		return true;
	}

	/**
	 * Removes zero width space characters that affect the rows in the CSV files
	 * From: https://gist.github.com/ahmadazimi/b1f1b8f626d73728f7aa
	 *
	 * @param $text
	 * @return string|string[]|null
	 */
	private function _removeZeroWidthSpaces($text)
	{
		return preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $text);
	}

	private function updateUsersSubscriptionStatus(array $data, string $journalPath)
	{
		/** @var UserDAO $userDao */
		$userDao = DAORegistry::getDAO('UserDAO');
		/** @var JournalDAO $journalDao */
		$journalDao = DAORegistry::getDAO('JournalDAO');
		/** @var $individualSubscriptionDao IndividualSubscriptionDAO */
		$individualSubscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');

		$journal = $journalDao->getByPath($journalPath);
		$journalId = $journal->getId();

		foreach ($data as $userData) {
			$user = $userDao->getByUsername($userData['username']);

			$subscription = $individualSubscriptionDao->newDataObject();
			$subscription->setJournalId($journalId);
			$subscription->setUserId($user->getId());
			$subscription->setReferenceNumber(null);
			$subscription->setNotes(null);
			$subscription->setStatus(SUBSCRIPTION_STATUS_ACTIVE);
			// TODO: Could be hard-coded
			$subscription->setTypeId($userData['subscription_type_id']);
			$subscription->setMembership(null);

			$dateTimeStart = DateTime::createFromFormat('d-M-y', $userData['start_date']);
			$dateTimeEnd = DateTime::createFromFormat('d-M-y', $userData['end_date']);

			$subscription->setDateStart(date('Y-m-d',$dateTimeStart->getTimestamp()));
			$subscription->setDateEnd(date('Y-m-d', $dateTimeEnd->getTimestamp()));

			$individualSubscriptionDao->insertObject($subscription);
		}
	}
}
