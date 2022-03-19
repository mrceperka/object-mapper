<?php declare(strict_types = 1);

namespace Orisai\ObjectMapper\Docs;

use Orisai\ObjectMapper\Args\ArgsChecker;
use Orisai\ObjectMapper\Context\ArgsContext;

final class SummaryDoc implements Doc
{

	public const MESSAGE = 'message';

	/**
	 * @param array<mixed> $args
	 * @return array<mixed>
	 */
	public static function resolveArgs(array $args, ArgsContext $context): array
	{
		$checker = new ArgsChecker($args, self::class);
		$checker->checkAllowedArgs([self::MESSAGE]);

		$checker->checkRequiredArg(self::MESSAGE);
		$checker->checkString(self::MESSAGE);

		return $args;
	}

	public static function getUniqueName(): string
	{
		return 'summary';
	}

}
