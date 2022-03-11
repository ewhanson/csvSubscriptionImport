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

namespace PKP\Plugins\ImportExport\SubscriptionImporter;

use PKP\Plugins\ImportExport\SubscriptionImporter\classes\CSVHelpers;
use PKP\Plugins\ImportExport\SubscriptionImporter\classes\Subscriber;

import('lib.pkp.classes.plugins.ImportExportPlugin');

class SubscriptionImporterPlugin extends \ImportExportPlugin
{

	/** @var Subscriber[] */
	protected array $subscribers = [];
	protected string $filename;
	protected string $journalPath;
	protected bool $isTest = false;

	private \Context $context;
	private int $subscriptionTypeId;

	/** @var array Subscriber[] */
	private array $failedSubscribers = [];

	/** @var array Subscriber[] */
	private array $newSubscribers = [];

	function register($category, $path, $mainContextId = null)
	{
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		$this->useAutoLoader();
		return $success;
	}

	/**
	 * Registers a custom autoloader to handle the plugin namespace
	 */
	private function useAutoLoader()
	{
		spl_autoload_register(function ($className) {
			// Removes the base namespace from the class name
			$path = explode(__NAMESPACE__ . '\\', $className, 2);
			if (!reset($path)) {
				// Breaks the remaining class name by \ to retrieve the folder and class name
				$path = explode('\\', end($path));
				$class = array_pop($path);
				$path = array_map(function ($name) {
					return strtolower($name[0]) . substr($name, 1);
				}, $path);
				$path[] = $class;
				// Uses the internal loader
				$this->import(implode('.', $path));
			}
		});
	}

	/**
	 * @inheritDoc
	 */
	function executeCLI($scriptName, &$args)
	{
		if (count($args) <=4) {
			$this->usage($scriptName);
			exit();
		}

		\AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);

		$this->filename = array_shift($args);
		$this->journalPath = array_shift($args);
		$this->subscriptionTypeId = array_shift($args);
		$outputCsvPath = array_shift($args);
		if (array_shift($args)) {
			$this->isTest = true;
		}

		if (!$this->filename || !$this->journalPath || !$this->subscriptionTypeId) {
			$this->usage($scriptName);
			exit();
		}

		if (!file_exists($this->filename)) {
			echo __('plugins.importexport.subscriptionImporter.fileDoesNotExist', array('filename' => $this->filename)) . "\n";
			exit();
		}
		$this->initialSetup();
		echo "Processing " . count($this->subscribers) . " users." . PHP_EOL . "---------------------------" . PHP_EOL;


		foreach ($this->subscribers as $subscriber) {
			try {
				$this->processSubscriber($subscriber);
			} catch(\Exception $exception) {
				$this->failedSubscribers[] = $subscriber;
				$subscriber->setStatus(Subscriber::STATUS_ERROR);
				echo '[' . $exception->getMessage() . '] '
					. 'Failed to process subscription for ' . $subscriber->email. PHP_EOL;
				continue;
			}
			echo $subscriber->email . " -- " . $subscriber->status . PHP_EOL;
		}

		// TODO: Handle failed subscribers somehow at end?
		CSVHelpers::arrayToCsv($this->subscribersToArray(), $outputCsvPath, Subscriber::getArrayKeys());
		echo "----------------------" . PHP_EOL . "Finished. Processed " . count($this->subscribers) . "(" .
			count($this->newSubscribers) . " new, " . count($this->failedSubscribers) . " errors)" .PHP_EOL;
	}

	private function initialSetup(): void
	{
		try {
			$this->setContext();
			$this->setSubscribers();
		} catch (\Exception $exception) {
			echo $exception->getMessage() . PHP_EOL;
			exit();
		}
	}

	/**
	 * @throws \Exception
	 */
	private function processSubscriber(Subscriber $subscriber)
	{
		/** @var \IndividualSubscriptionDAO $individualSubscriptionDao */
		$individualSubscriptionDao = \DAORegistry::getDAO('IndividualSubscriptionDAO');

		// Check if user exists
		if ($subscriber->hasExistingUser()) {
			// If they do, check if they have an existing subscription
			if ($individualSubscriptionDao->subscriptionExistsByUserForJournal($subscriber->getUser()->getId(), $this->context->getId())) {
				// If so, update their subscription expiry and status
				$this->updateSubscriptionStatus($this->context, $subscriber);
			} else {
				// Otherwise, create a new subscription for the user
				$this->addNewSubscription($this->context, $subscriber);
			}
			$subscriber->setStatus(Subscriber::STATUS_UPDATED);
		} else {
			// Otherwise, create a new user
			$subscriber->createUser();
			// Add to new user list
			$this->newSubscribers[] = $subscriber;
			// Grant subscription access
			$this->addNewSubscription($this->context, $subscriber);
			$subscriber->setStatus(Subscriber::STATUS_NEW);
		}
	}

	/**
	 * Update the subscription expiry date and status for an existing user subscription
	 *
	 * @param \Context $context
	 * @param Subscriber $subscriber
	 * @return void
	 * @throws \Exception
	 */
	private function updateSubscriptionStatus(\Context $context, Subscriber $subscriber): void
	{
		/** @var \IndividualSubscriptionDAO $individualSubscriptionDao */
		$individualSubscriptionDao = \DAORegistry::getDAO('IndividualSubscriptionDAO');

		$subscription = $individualSubscriptionDao->getByUserIdForJournal($subscriber->getUser()->getId(), $context->getId());
		$subscription->setDateEnd($subscriber->endDate->format('Y-m-d'));

		$individualSubscriptionDao->updateObject($subscription);
	}

	/**
	 * Add a subscription for a new user without a previous subscription.
	 *
	 * @param \Context $context
	 * @param Subscriber $subscriber
	 * @return void
	 * @throws \Exception
	 */
	private function addNewSubscription(\Context $context, Subscriber $subscriber)
	{
		/** @var $individualSubscriptionDao \IndividualSubscriptionDAO */
		$individualSubscriptionDao = \DAORegistry::getDAO('IndividualSubscriptionDAO');

		$subscription = $individualSubscriptionDao->newDataObject();

		$subscription->setJournalId($context->getId());
		// TODO: Handle case where user does not exist for whatever reason
		$subscription->setUserId($subscriber->getUser()->getId());
		$subscription->setReferenceNumber(null);
		$subscription->setNotes(null);
		$subscription->setStatus(SUBSCRIPTION_STATUS_ACTIVE);
		$subscription->setTypeId($subscriber->subscriptionTypeId);
		$subscription->setMembership(null);
		$subscription->setDateStart($subscriber->startDate->format('Y-m-d'));
		$subscription->setDateEnd($subscriber->endDate->format('Y-m-d'));

		$individualSubscriptionDao->insertObject($subscription);
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

	/**
	 * @throws \Exception
	 */
	private function setSubscribers(): void
	{
		$rows = [];
		$status = CSVHelpers::csvToArray($this->filename, $rows);
		if (!$status) {
			throw new \Exception('CSV parsing was not successful.');
		}
		foreach ($rows as $row) {
			$this->subscribers[] = new Subscriber($row, $this->subscriptionTypeId, $this->context, $this->isTest);
		}
	}

	/**
	 * @throws \Exception
	 */
	private function setContext()
	{
		/** @var \JournalDAO $journalDao */
		$journalDao = \DAORegistry::getDAO('JournalDAO');
		$context = $journalDao->getByPath($this->journalPath);

		if (!$context) {
			throw new \Exception("Context not found");
		}

		$this->context = $context;
	}

	// TODO: Investigate turning into generator so this array and array of Subscribers aren't loaded
	//	 into memory at the same time.
	private function subscribersToArray(): array
	{
		$data = [];
		foreach ($this->subscribers as $subscriber) {
			$data[] = $subscriber->toArray();
		}

		return $data;
	}
}
