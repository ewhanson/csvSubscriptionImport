<?php

namespace PKP\Plugins\ImportExport\SubscriptionImporter\classes;

use Context;
use DAORegistry;
use DateTime;
use Exception;
use User;
use UserDAO;
use UserGroupDAO;
use Validation;

class Subscriber
{
	public const STATUS_NEW = 'new';
	public const STATUS_UPDATED = 'updated';
	public const STATUS_ERROR = 'error';

	public string $firstName;
	public string $lastName;
	public string $email;
	public ?string $affiliation = null;
	public string $country;
	public ?string $username = null;
	public ?string $tempPassword = null;
	public Roles $roles;
	public DateTime $startDate;
	public DateTime $endDate;
	public int $subscriptionTypeId;
	public string $status;

	protected ?User $user = null;
	protected Context $context;

	public function __construct(array $rowData, int $subscriptionTypeId, Context $context, bool $isTest, string $dateFormat = 'Y-m-d')
	{
		$this->firstName = $rowData['firstname'];
		$this->lastName = $rowData['lastname'];
		$this->email = $isTest ? $rowData['email'] . 'test' : $rowData['email'];
		$this->affiliation = !empty($rowData['affiliation']) ? $rowData['affiliation'] : null;
		$this->country = $rowData['country'];
		$this->username = !empty($rowData['username']) ? $rowData['username'] : null;
		$this->tempPassword = !empty($rowData['tempPassword']) ? $rowData['tempPassword'] : \Validation::generatePassword();

		$this->roles = new Roles($rowData);

		$this->startDate = DateTime::createFromFormat($dateFormat, $rowData['start_date']);
		$this->endDate = DateTime::createFromFormat($dateFormat, $rowData['end_date']);

		$this->subscriptionTypeId = $subscriptionTypeId;
		$this->context = $context;

	}

	/**
	 * Checks if user exists based on username and email
	 *
	 * @return bool
	 */
	public function hasExistingUser(): bool
	{
		/** @var UserDAO $userDao */
		$userDao = DAORegistry::getDAO('UserDAO');
		if ($this->username) {
			$user = $userDao->getByUsername($this->username);
		} else {
			$user = $userDao->getUserByEmail($this->email);
		}
		if ($user) {
			$this->user = $user;
			return true;
		}

		return false;
	}

	/**
	 * Create user for this subscriber and assign 'Reader' role
	 *
	 * @return void
	 * @throws Exception
	 */
	public function createUser(): void
	{
		/** @var UserDAO $userDao */
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->newDataObject();

		$primaryLocale = $this->context->getPrimaryLocale();

		if ($this->username === null) {
			$this->username = $this->generateNewUsername($userDao);
		}
		$user->setUsername($this->username);

		$user->setGivenName($this->firstName, $primaryLocale);
		$user->setFamilyName($this->lastName, $primaryLocale);
		if ($this->affiliation !== null) {
			$user->setAffiliation($this->affiliation, $primaryLocale);
		}
		$user->setCountry($this->country);
		$user->setEmail($this->email);
		$user->setMustChangePassword(true);
		$user->setPassword(Validation::encryptCredentials($this->username, $this->tempPassword));
		$user->setLocales([$primaryLocale]);

		$user->setDateRegistered(\Core::getCurrentDate());

		$userId = $userDao->insertObject($user);

		if (!$userId) {
			throw new Exception('Something went wrong with user creation.');
		}

		// user groups/roles
		$userGroupIds = $this->roles->getUserGroupIds($this->context);
		/** @var UserGroupDAO $userGroupDao */
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		foreach ($userGroupIds as $userGroupId) {
			$userGroupDao->assignUserToGroup($userId, $userGroupId);
		}

		$this->user = $userDao->getById($userId);
	}

	/**
	 * Getter for stored user object
	 *
	 * @return User|null
	 * @throws Exception
	 */
	public function getUser(): ?User
	{
		if ($this->user === null) {
			throw new Exception('No user found. A user must be created or exist at this point');
		}
		return $this->user;
	}

	/**
	 * Return subscriber data as an associative array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'firstname' => $this->firstName,
			'lastname' => $this->lastName,
			'email' => $this->email,
			'username' => $this->username,
			'tempPassword' => $this->tempPassword,
			'country' => $this->country,
			'status' => $this->status,

		];
	}

	public function setStatus(string $status)
	{
		$this->status = $status;
	}

	/**
	 * Get associative array keys for creating CSV header row.
	 *
	 * @return string[]
	 */
	static public function getArrayKeys(): array
	{
		return [
			'firstname',
			'lastname',
			'email',
			'username',
			'tempPassword',
			'country',
			'status'
		];
	}

	/**
	 * Create a username based on first/last name. Avoids conflicts with existing usernames in OJS.
	 *
	 * @param UserDAO $userDao
	 * @param int $iterations
	 * @return string
	 */
	private function generateNewUsername(UserDAO $userDao, int $iterations = 0): string
	{
		$username = str_replace(' ', '', strtolower($this->lastName)) . str_split(strtolower($this->firstName))[0];
		if ($iterations != 0) {
			$username .= ((string) $iterations);
		}
		if ($userDao->getByUsername($username)) {
			$username = $this->generateNewUsername($userDao, $iterations + 1);
		}
		return $username;
	}
}
