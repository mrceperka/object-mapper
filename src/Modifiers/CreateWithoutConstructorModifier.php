<?php declare(strict_types = 1);

namespace Orisai\ObjectMapper\Modifiers;

use Orisai\ObjectMapper\Args\ArgsChecker;
use Orisai\ObjectMapper\Args\EmptyArgs;
use Orisai\ObjectMapper\Context\ResolverArgsContext;

/**
 * @implements Modifier<EmptyArgs>
 */
final class CreateWithoutConstructorModifier implements Modifier
{

	public static function resolveArgs(array $args, ResolverArgsContext $context): EmptyArgs
	{
		$checker = new ArgsChecker($args, self::class);
		$checker->checkNoArgs();

		return new EmptyArgs();
	}

}
