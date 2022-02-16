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

	public function __construct(array $rowData, int $subscriptionTypeId, string $dateFormat = 'd-M-y')
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
			if ($user) {
				$this->user = $user;
				return true;
			}
		} else {
			$user = $userDao->getUserByEmail($this->email);
			if ($user) {
				$this->user = $user;
				return true;
			}
		}

		return false;
	}

	/**
	 * Create user for this subscriber and assign 'Reader' role
	 *
	 * @return void
	 */
	public function createUser(): void
	{
		// TODO: create user for given subscriber and assign reader role
	}

	/**
	 * Getter for stored user object
	 *
	 * @return User|null
	 */
	public function getUser(): ?User
	{
		return $this->user;
	}
}
