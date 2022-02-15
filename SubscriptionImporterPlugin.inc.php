<?php

/**
 * @file plugins/importexport/subscriptionImporter/SubscriptionImporterPlugin.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubscriptionImporterPlugin
 * @ingroup plugins_importexport_subscriptionimporter
 *
 * @brief CSV import plugin for updating and adding user subscriptions
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

class SubscriptionImporterPlugin extends ImportExportPlugin
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
			echo __('plugins.importexport.subscriptionImporter.fileDoesNotExist', array('filename' => $filename)) . "\n";
			exit();
		}

		$data = [];
		CSVHelpers::csvToArray($filename, $data);

		$this->updateUsersSubscriptionStatus($data, $journalPath);

	}

	/**
	 * @inheritDoc
	 */
	function usage($scriptName)
	{
		echo __('plugins.importexport.subscriptionImporter.cliUsage', [
			'scriptName' => $scriptName,
			'pluginName' => $this->getName(),
		]) . PHP_EOL;
	}

	/**
	 * @inheritDoc
	 */
	function getName()
	{
		return 'SubscriptionImporterPlugin';
	}

	/**
	 * @inheritDoc
	 */
	function getDisplayName()
	{
		return __('plugins.importexport.subscriptionImporter.displayName');
	}

	/**
	 * @inheritDoc
	 */
	function getDescription()
	{
		return __('plugins.importexport.native.description');
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
