<?php

namespace PKP\Plugins\ImportExport\SubscriptionImporter\classes;

class CSVHelpers
{
	/**
	 * Loops over a CSV and adds contents to an associative array
	 *
	 * @param string $filePath
	 * @param array $dataOutput
	 * @return bool
	 */
	public static function csvToArray(string $filePath, array &$dataOutput): bool
	{
		if (!file_exists($filePath) || !is_readable($filePath) || is_dir($filePath))
			return false;

		$header = null;

		if (($handle = fopen($filePath, 'r')) !== false) {
			while (($row = fgetcsv($handle, 10000)) !== false) {
				$row = CSVHelpers::removeZeroWidthSpaces($row);
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
	 * Writes associative array out to a CSV. Inserts array keys as header row.
	 *
	 * @param array $data
	 * @param string $filePath
	 * @param array|null $headerData
	 * @return bool
	 */
	public static function arrayToCsv(array $data, string $filePath, ?array $headerData = null): bool
	{
		try {
			if ($headerData !== null) {
				array_unshift($data, $headerData);
			}
			$splFile = new \SplFileObject($filePath, 'w');
			foreach ($data as $row) {
				$splFile->fputcsv($row);
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Removes zero width space characters that affect the rows in CSV files
	 * From: https://gist.github.com/ahmadazimi/b1f1b8f626d73728f7aa
	 *
	 * @param string[] $text
	 * @return string|string[]|null
	 */
	public static function removeZeroWidthSpaces(array $text)
	{
		return preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $text);
	}
}
