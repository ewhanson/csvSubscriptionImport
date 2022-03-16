<?php

namespace PKP\Plugins\ImportExport\SubscriptionImporter\classes;

use Context;
use DAORegistry;
use Exception;
use UserGroupDAO;

class Roles
{
	/** @var string At least one role is required */
	public $role1;
	/** @var string|null  */
	public $role2;
	/** @var string|null  */
	public $role3;
	/** @var string|null  */
	public $role4;

	/**
	 * @param array $rowData
	 */
	public function __construct($rowData)
	{
		$this->role1 = !empty($rowData['role1']) ? $rowData['role1'] : 'Reader';
		$this->role2 = !empty($rowData['role2']) ? $rowData['role2'] : null;
		$this->role3 = !empty($rowData['role3']) ? $rowData['role3'] : null;
		$this->role4 = !empty($rowData['role4']) ? $rowData['role4'] : null;
	}

	/**
	 * @param Context $context
	 * @throws Exception
	 */
	public function getUserGroupIds($context)
	{
		$groupIds = [];

		/** @var UserGroupDAO $userGroupDao */
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		$assignedRoles = [$this->role1, $this->role2, $this->role3, $this->role4];
		$allowedRoles = ['Reader', 'Author', 'Reviewer'];
		$nameToRoles = [
			'Reader' => ROLE_ID_READER,
			'Author' => ROLE_ID_AUTHOR,
			'Reviewer' => ROLE_ID_REVIEWER,
		];

		foreach ($assignedRoles as $assignedRole) {
			if (in_array($assignedRole, $allowedRoles) === false && $assignedRole !== null) {
				throw new Exception("Invalid role provided");
			}

			if ($assignedRole !== null) {
				$groupIds[] = $userGroupDao->getDefaultByRoleId($context->getId(), $nameToRoles[$assignedRole])->getId();
			}
		}

		return $groupIds;
	}
}
