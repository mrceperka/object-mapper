<?php declare(strict_types = 1);

namespace Tests\Orisai\ObjectMapper\Doubles;

use Orisai\ObjectMapper\Annotation\Expect\StringValue;
use Orisai\ObjectMapper\Annotation\Modifiers\Skipped;
use Orisai\ObjectMapper\MappedObject;

final class SkippedPropertiesVO extends MappedObject
{

	/** @StringValue() */
	public string $required;

	/** @StringValue() */
	public string $optional = 'optional';

	/**
	 * @StringValue()
	 * @Skipped()
	 */
	public ?string $requiredSkipped;

	/**
	 * @StringValue()
	 * @Skipped()
	 */
	public string $optionalSkipped = 'optionalSkipped';

}
