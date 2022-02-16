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

	/** @var Subscriber[] */
	protected array $subscribers = [];
	protected string $filename;
	protected string $journalPath;

	private Context $context;
	private int $subscriptionTypeId;

	/** @var array Subscriber[] */
	private array $failedSubscribers = [];

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

		$this->filename = array_shift($args);
		$this->journalPath = array_shift($args);
		$this->subscriptionTypeId = array_shift($args);

		if (!$this->filename || !$this->journalPath || !$this->subscriptionTypeId) {
			$this->usage($scriptName);
			exit();
		}

		if (!file_exists($this->filename)) {
			echo __('plugins.importexport.subscriptionImporter.fileDoesNotExist', array('filename' => $this->filename)) . "\n";
			exit();
		}
			$this->initialSetup();

			foreach ($this->subscribers as $subscriber) {
				try {
					$this->processSubscriber($subscriber);
				} catch(Exception $exception) {
					$this->failedSubscribers[] = $subscriber;
					echo $exception->getMessage() . ' -- ' . 'Failed to process subscription for ' . $subscriber->email. PHP_EOL;
					continue;
				}
			}

			// TODO: Handle failed subscribers somehow at end
	}

	private function initialSetup(): void
	{
		try {
			$this->setSubscribers();
			$this->setContext();
		} catch (Exception $exception) {
			echo $exception->getMessage() . PHP_EOL;
			exit();
		}
	}

	private function processSubscriber(Subscriber $subscriber)
	{
		// Check if user exists
		if ($subscriber->hasExistingUser()) {
			// If they do, update their subscription expiry and status
			$this->updateSubscriptionStatus($this->context, $subscriber);
		} else {
			// Otherwise, create a new user
			$subscriber->createUser();
			// Grant subscription access
			$this->addNewSubscription($this->context, $subscriber);
		}
	}

	/**
	 * Update the subscription expiry date and status for an existing user subscription
	 *
	 * @param Context $context
	 * @param Subscriber $subscriber
	 * @return void
	 */
	private function updateSubscriptionStatus(Context $context, Subscriber $subscriber): void
	{

	}

	/**
	 * Add a subscription for a new user without a previous subscription.
	 *
	 * @param Context $context
	 * @param Subscriber $subscriber
	 * @return void
	 */
	private function addNewSubscription(Context $context, Subscriber $subscriber)
	{
		/** @var $individualSubscriptionDao IndividualSubscriptionDAO */
		$individualSubscriptionDao = DAORegistry::getDAO('IndividualSubscriptionDAO');

		$subscription = $individualSubscriptionDao->newDataObject();

		$subscription->setJournalId($context->getId());
		// TODO: Handle case where user does not exist for whatever reason
		$subscription->setUserId($subscriber->getUser()->getId());
		$subscription->setReferenceNumber(null);
		$subscription->setNotes(null);
		$subscription->setStatus(SUBSCRIPTION_STATUS_ACTIVE);
		$subscription->setTypeId($subscriber->subscriptionTypeId);
		$subscription->setMembership(null);
		$subscription->setDateStart(date('Y-m-d', $subscriber->startDate));
		$subscription->setDateEnd(date('Y-m-d', $subscriber->endDate));

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

	private function setSubscribers(): void
	{
		$rows = [];
		CSVHelpers::csvToArray($this->filename, $rows);
		foreach ($rows as $row) {
			$this->subscribers[] = new Subscriber($row, $this->subscriptionTypeId);
		}
	}

	/**
	 * @throws Exception
	 */
	private function setContext()
	{
		/** @var JournalDAO $journalDao */
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$context = $journalDao->getByPath($this->journalPath);

		if (!$context) {
			throw new Exception("Context not found");
		}

		$this->context = $context;
	}
}
