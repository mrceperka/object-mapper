<?php declare(strict_types = 1);

namespace Orisai\ObjectMapper\Rules;

use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\ObjectMapper\Args\Args;
use Orisai\ObjectMapper\Args\ArgsChecker;
use Orisai\ObjectMapper\Context\RuleArgsContext;
use Orisai\ObjectMapper\Context\TypeContext;
use Orisai\ObjectMapper\Meta\RuleMeta;
use Orisai\ObjectMapper\Types\CompoundType;
use function count;
use function sprintf;

/**
 * @phpstan-implements Rule<CompoundRuleArgs>
 */
abstract class CompoundRule implements Rule
{

	public const RULES = 'rules';

	/**
	 * {@inheritDoc}
	 */
	public function resolveArgs(array $args, RuleArgsContext $context): CompoundRuleArgs
	{
		$checker = new ArgsChecker($args, static::class);
		$checker->checkAllowedArgs([self::RULES]);

		$checker->checkRequiredArg(self::RULES);
		$rules = $checker->checkArray(self::RULES);

		if (count($rules) < 2) {
			throw InvalidArgument::create()
				->withMessage(sprintf(
					'Argument %s given to rule %s expect at least 2 rules',
					self::RULES,
					static::class,
				));
		}

		$resolver = $context->getMetaResolver();

		foreach ($rules as $key => $rule) {
			if (!$rule instanceof RuleMeta) {
				throw InvalidArgument::create();
			}

			$rules[$key] = $resolver->resolveRuleMeta($rule, $context);
		}

		return new CompoundRuleArgs($rules);
	}

	public function getArgsType(): string
	{
		return CompoundRuleArgs::class;
	}

	/**
	 * @param CompoundRuleArgs $args
	 */
	public function createType(Args $args, TypeContext $context): CompoundType
	{
		$type = new CompoundType($this->getOperator());

		foreach ($args->rules as $key => $nestedRuleMeta) {
			$nestedRule = $context->getRule($nestedRuleMeta->getType());
			$nestedRuleArgs = $nestedRuleMeta->getArgs();
			$type->addSubtype($key, $nestedRule->createType($nestedRuleArgs, $context));
		}

		return $type;
	}

	abstract protected function getOperator(): string;

}
