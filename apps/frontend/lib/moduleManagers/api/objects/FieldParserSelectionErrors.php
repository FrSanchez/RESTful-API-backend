<?php
namespace Api\Objects;

/**
 * Selection errors that occur during parsing with {@see FieldsParser}.
 */
class FieldParserSelectionErrors
{
	/** The selection is not valid. */
	const SELECTION_ERROR_UNKNOWN = 0;

	/** The selection is a known queryable field but queryable fields are not allowed. */
	const SELECTION_ERROR_QUERYABLE_NOT_ALLOWED = 1;

	/** The selection is a known write only field which is not allowed to be selected */
	const SELECTION_ERROR_WRITE_ONLY_NOT_ALLOWED = 2;

	/**
	 * The selection is a scalar/primitive but has a property following. For example, specifying
	 * "firstName.name" but expected "firstName" since it's a string.
	 */
	const SELECTION_ERROR_SCALAR_WITH_INVALID_CHILD = 3;

	/** The selection is a known custom field of an array type (like multi-select), but it is not queryable */
	const SELECTION_ERROR_CUSTOM_FIELD_ARRAY_NOT_ALLOWED = 4;

	/**
	 * The selection is an object or representation but the property is missing. For example, specifying
	 * "prospect.createdBy" when the user is expected to pass in "prospect.createdBy.id".
	 */
	const SELECTION_ERROR_MISSING_FIELD = 10;

	/** The selection is a known collection name but collections are not allowed. */
	const SELECTION_ERROR_COLLECTIONS_NOT_ALLOWED = 30;
	const SELECTION_ERROR_COLLECTIONS_WITHIN_COLLECTIONS_NOT_ALLOWED = 31;

	/**
	 * The selection is a known object or representation collection but the property is missing. For example, specifying
	 * "suppressedLists" when the user is expected to pass "suppressedLists.name".
	 */
	const SELECTION_ERROR_COLLECTION_MISSING_FIELD = 32;

	/**
	 * The selection is a scalar collection but has a property following. For example, specifying
	 * "suppressedListIds.name" but expected "suppressedListIds" since it's an array of integers.
	 */
	const SELECTION_ERROR_SCALAR_COLLECTION_WITH_INVALID_CHILD = 33;

	private array $selectionErrors = [];
	private int $errorCount = 0;

	/**
	 * Gets all selections that have errors excluding those of the specific error code. Note that the error codes
	 * are not returned.
	 * @param int[] $excludedErrorCodes Array of error codes to not return. See the SELECTION_ERROR_* constants in this class.
	 * @return string[] The selections that are invalid or empty array if no errors are present.
	 */
	public function getSelectionErrorsExcludingErrorCodes(array $excludedErrorCodes): array
	{
		// build map of exempt error codes to make lookup faster
		$excludedErrorCodeMap = [];
		foreach ($excludedErrorCodes as $excludedErrorCode) {
			$excludedErrorCodeMap[$excludedErrorCode] = true;
		}

		$invalidSelections = [];
		foreach ($this->selectionErrors as $errorCode => $selections) {
			if (!isset($excludedErrorCodeMap[$errorCode])) {
				$invalidSelections = array_merge($invalidSelections, $selections);
			}
		}
		$this->reorderSelectionErrors($invalidSelections);
		return $this->getSelectionsFromSelectionErrors($invalidSelections);
	}

	/**
	 * Gets all selections that have the specified error code.
	 * @param int $errorCode See the SELECTION_ERROR_* constants in this class.
	 * @return string[] The selections that are invalid with the specified error code or empty array if no errors are present.
	 */
	public function getSelectionErrorsWithErrorCode(int $errorCode): array
	{
		return $this->getSelectionErrorsWithErrorCodes([$errorCode]);
	}

	/**
	 * Gets all selections that have the specified error codes.
	 * @param int[] $errorCodes Array of error codes to return. See the SELECTION_ERROR_* constants in this class.
	 * @return string[] The selections that are invalid with the specified error codes or empty array if no errors are present.
	 */
	public function getSelectionErrorsWithErrorCodes(array $errorCodes): array
	{
		$invalidSelections = [];
		foreach ($errorCodes as $errorCode) {
			if (isset($this->selectionErrors[$errorCode])) {
				$invalidSelections = array_merge($invalidSelections, $this->selectionErrors[$errorCode]);
			}
		}
		$this->reorderSelectionErrors($invalidSelections);
		return $this->getSelectionsFromSelectionErrors($invalidSelections);
	}

	/**
	 * Adds the given error code to the selection. If the selection is null, then it's skipped and not added.
	 * @param string|null $selection The selection made by the user.
	 * @param int $errorCode See the SELECTION_ERROR_* constants in this class.
	 */
	public function addSelectionError(?string $selection, int $errorCode): void
	{
		if (is_null($selection)) {
			return;
		}

		$this->selectionErrors[$errorCode][] = [
			'selection' => $selection,

			// Order of the selection error is kept so that it can be returned to the same order on retrieval. The pattern
			// with FieldsParser is parse selections in order from left-to-right so errors need to be produced in the
			// same left-to-right order.
			'order' => $this->errorCount,
		];
		$this->errorCount = $this->errorCount + 1;
	}

	private function reorderSelectionErrors(array &$selectionErrors): void
	{
		usort($selectionErrors, function (array $selectionError1, array $selectionError2) {
			return $selectionError1['order'] - $selectionError2['order'];
		});
	}

	private function getSelectionsFromSelectionErrors(array $selectionErrors): array
	{
		$invalidSelections = [];
		foreach ($selectionErrors as $selectionError) {
			$invalidSelections[] = $selectionError['selection'];
		}
		return $invalidSelections;
	}
}
