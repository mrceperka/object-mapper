<?php declare(strict_types = 1);

namespace Tests\Orisai\ObjectMapper\Unit\Meta\Runtime;

use Orisai\ObjectMapper\Args\EmptyArgs;
use Orisai\ObjectMapper\Callbacks\BaseCallbackArgs;
use Orisai\ObjectMapper\Callbacks\BeforeCallback;
use Orisai\ObjectMapper\Docs\DescriptionDoc;
use Orisai\ObjectMapper\Meta\DefaultValueMeta;
use Orisai\ObjectMapper\Meta\DocMeta;
use Orisai\ObjectMapper\Meta\Runtime\CallbackRuntimeMeta;
use Orisai\ObjectMapper\Meta\Runtime\ModifierRuntimeMeta;
use Orisai\ObjectMapper\Meta\Runtime\PropertyRuntimeMeta;
use Orisai\ObjectMapper\Meta\Runtime\RuleRuntimeMeta;
use Orisai\ObjectMapper\Modifiers\FieldNameArgs;
use Orisai\ObjectMapper\Modifiers\FieldNameModifier;
use Orisai\ObjectMapper\Rules\MixedRule;
use PHPUnit\Framework\TestCase;

final class PropertyRuntimeMetaTest extends TestCase
{

	public function test(): void
	{
		$callbacks = [
			new CallbackRuntimeMeta(BeforeCallback::class, new BaseCallbackArgs('method', false, false)),
		];
		$docs = [
			new DocMeta(DescriptionDoc::class, []),
		];
		$modifiers = [
			new ModifierRuntimeMeta(FieldNameModifier::class, new FieldNameArgs('field')),
		];
		$rule = new RuleRuntimeMeta(MixedRule::class, new EmptyArgs());
		$default = DefaultValueMeta::fromNothing();

		$meta = new PropertyRuntimeMeta($callbacks, $docs, $modifiers, $rule, $default);

		self::assertSame(
			$callbacks,
			$meta->getCallbacks(),
		);
		self::assertSame(
			$docs,
			$meta->getDocs(),
		);
		self::assertSame(
			$modifiers,
			$meta->getModifiers(),
		);
		self::assertSame(
			$rule,
			$meta->getRule(),
		);
	}

}
