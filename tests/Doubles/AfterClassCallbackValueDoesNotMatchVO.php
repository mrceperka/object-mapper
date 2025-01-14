<?php declare(strict_types = 1);

namespace Tests\Orisai\ObjectMapper\Doubles;

use Orisai\ObjectMapper\Attributes\Callbacks\After;
use Orisai\ObjectMapper\Attributes\Expect\StringValue;
use Orisai\ObjectMapper\Exception\ValueDoesNotMatch;
use Orisai\ObjectMapper\MappedObject;
use Orisai\ObjectMapper\Types\Value;

/**
 * @After(method="after")
 */
final class AfterClassCallbackValueDoesNotMatchVO extends MappedObject
{

	/** @StringValue() */
	public string $string;

	/**
	 * @param array<mixed> $data
	 * @throws ValueDoesNotMatch
	 */
	public static function after(array $data): void
	{
		throw ValueDoesNotMatch::createFromString('Error after class', Value::none());
	}

}
