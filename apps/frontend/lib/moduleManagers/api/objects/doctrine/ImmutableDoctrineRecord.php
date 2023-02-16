<?php
namespace Api\Objects\Doctrine;

use Doctrine_Null;
use Doctrine_Record;
use RuntimeException;
use ReflectionClass;
use ReflectionException;
use piTracker;

/**
 * Wrapper around a Doctrine_Record instance to make it immutable. This allows for the record to be passed into
 * different layers of the application without having to worry about modifications made to the Doctrine_Record.
 *
 * Class ImmutableDoctrineRecord
 * @package Api\Objects\Doctrine
 */
class ImmutableDoctrineRecord
{
	/** @var Doctrine_Record */
	private $doctrineRecord;

	public function __construct(Doctrine_Record $doctrineRecord)
	{
		$this->doctrineRecord = $doctrineRecord;
	}

	/**
	 * Gets the value of a field
	 * @param string $name The name of the field
	 * @return mixed
	 */
	public function get(string $name)
	{
		// Using getData here to avoid calling the getter methods directly.
		$data = $this->doctrineRecord->getData();

		if (!array_key_exists($name, $data)) {
			return null;
		}

		$value = $data[$name];
		if ($value instanceof Doctrine_Null) {
			return null;
		}
		return $value;
	}

	/**
	 * Determines if the reference is available on this record.
	 * @param string $name The name of the reference
	 * @return bool
	 */
	public function hasReference(string $name): bool
	{
		return $this->doctrineRecord->hasReference($name);
	}

	/**
	 * Returns the reference with the given name.
	 * @param string $name The name of the reference
	 * @return ImmutableDoctrineRecord|null
	 */
	public function reference(string $name)
	{
		$reference = $this->doctrineRecord->reference($name);
		if ($reference instanceof Doctrine_Null) {
			return null;
		} elseif ($reference instanceof Doctrine_Record) {
			return ImmutableDoctrineRecord::of($reference);
		}
		throw new RuntimeException("Unexpected value returned from Doctrine_Record->reference");
	}

	/**
	 * Returns an ImmutableDoctrineRecord instance if the value specified is a Doctrine_Record. In the case that
	 * the value is a null, a null value is returned. If the value is already a ImmutableDoctrineRecord, the instance
	 * is returned without modification.
	 * @param Doctrine_Record|ImmutableDoctrineRecord|null $recordOrImmutableOrNull
	 * @return ImmutableDoctrineRecord|null
	 */
	public static function of($recordOrImmutableOrNull): ?ImmutableDoctrineRecord
	{
		if (is_null($recordOrImmutableOrNull)) {
			return null;
		} elseif ($recordOrImmutableOrNull instanceof ImmutableDoctrineRecord) {
			return $recordOrImmutableOrNull;
		} elseif ($recordOrImmutableOrNull instanceof Doctrine_Record) {
			return new ImmutableDoctrineRecord($recordOrImmutableOrNull);
		}
		throw new RuntimeException("Unexpected argument type specified");
	}

	/**
	 * @param $traitClass
	 * @return bool
	 * @throws ReflectionException
	 */
	public function isDoctrineUsingTrait($traitClass): bool
	{
		return in_array(
			$traitClass,
			array_keys((new ReflectionClass(get_class($this->doctrineRecord)))->getTraits())
		);
	}

	/**
	 * @return string
	 */
	public function getDoctrineRecordClass(): string
	{
		return get_class($this->doctrineRecord);
	}

	/**
	 * @param string $domain
	 * @param piTracker|null $tracker
	 * @return string
	 */
	public function getLongUrlForTrackerDomain(string $domain, ?piTracker $tracker): string
	{
		return $this->doctrineRecord->buildLongFormTrackerUrl($domain, $tracker);
	}
}
