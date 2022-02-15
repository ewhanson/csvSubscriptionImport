<?php

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
	 * Removes zero width space characters that affect the rows in CSV files
	 * From: https://gist.github.com/ahmadazimi/b1f1b8f626d73728f7aa
	 *
	 * @param string $text
	 * @return string|string[]|null
	 */
	public static function removeZeroWidthSpaces(string $text)
	{
		return preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $text);
	}
}
