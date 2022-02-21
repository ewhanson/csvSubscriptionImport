<?php

class Subscriber
{
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

	protected ?User $user = null;
	protected Context $context;

	public function __construct(array $rowData, int $subscriptionTypeId, Context $context, string $dateFormat = 'd-M-y')
	{
		$this->firstName = $rowData['firstname'];
		$this->lastName = $rowData['lastName'];
		$this->email = $rowData['email'];
		$this->affiliation = !empty($rowData['affiliation']) ? $rowData['affiliation'] : null;
		$this->country = $rowData['country'];
		$this->username = !empty($rowData['username']) ? $rowData['username'] : null;
		$this->tempPassword = !empty($rowData['tempPassword']) ? $rowData['tempPassword'] : null;
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
			throw new Exception('Username is required');
		} else {
			$user->setUsername($this->username);
		}
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

		$user->setDateRegistered(Core::getCurrentDate());

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
}
