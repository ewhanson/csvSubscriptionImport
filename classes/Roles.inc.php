<?php

class Roles
{
	/** @var string At least one role is required */
	public string $role1;
	public ?string $role2;
	public ?string $role3;
	public ?string $role4;

	public function __construct(array $rowData)
	{
		$this->role1 = !empty($rowData['role1']) ? $rowData['role1'] : 'Reader';
		$this->role2 = !empty($rowData['role2']) ? $rowData['role2'] : null;
		$this->role3 = !empty($rowData['role3']) ? $rowData['role3'] : null;
		$this->role4 = !empty($rowData['role4']) ? $rowData['role4'] : null;
	}
}
