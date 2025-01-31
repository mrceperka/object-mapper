<?php declare(strict_types = 1);

namespace Tests\Orisai\ObjectMapper\Doubles;

use Orisai\ObjectMapper\MappedObject;
use stdClass;

final class DependentVO extends MappedObject
{

	public ?stdClass $class = null;

	public function __construct(stdClass $class)
	{
		$this->class = $class;
	}

}
