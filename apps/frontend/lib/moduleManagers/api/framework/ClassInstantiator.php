<?php
namespace Api\Framework;

use Psr\Container\ContainerInterface;

class ClassInstantiator
{
	private ContainerInterface $container;

	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}

	/**
	 * Instantiates the given ID into a class. If the ID is a fully qualified class name, the class is instantiated with
	 * each invocation and there are no arguments passed to the constructor. If the ID starts with an at-symbol (@),
	 * the ID is passed directly to the Symphony Dependency Injection.
	 *
	 * @param string $idOrClass
	 * @return mixed
	 */
	public function instantiateFromId(string $idOrClass)
	{
		if ($idOrClass[0] === '@') {
			// Look up the instance within the dependency injection container
			return $this->container->get(substr($idOrClass, 1));
		} else {
			// Assume the ID is a fully qualified class name
			return new $idOrClass();
		}
	}
}
