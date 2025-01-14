<?php declare(strict_types = 1);

namespace Tests\Orisai\ObjectMapper\Unit\Processing;

use DateTimeImmutable;
use DateTimeInterface;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\ObjectMapper\Exception\InvalidData;
use Orisai\ObjectMapper\MappedObject;
use Orisai\ObjectMapper\Printers\ErrorPrinter;
use Orisai\ObjectMapper\Printers\ErrorVisualPrinter;
use Orisai\ObjectMapper\Printers\TypeToStringConverter;
use Orisai\ObjectMapper\Processing\Options;
use Orisai\ObjectMapper\Processing\RequiredFields;
use stdClass;
use Tests\Orisai\ObjectMapper\Doubles\AfterClassCallbackCurrentTypeInvalidDataVO;
use Tests\Orisai\ObjectMapper\Doubles\AfterClassCallbackNewTypeInvalidDataVO;
use Tests\Orisai\ObjectMapper\Doubles\AfterClassCallbackValueDoesNotMatchVO;
use Tests\Orisai\ObjectMapper\Doubles\AttributesVO;
use Tests\Orisai\ObjectMapper\Doubles\BeforeClassCallbackMixedValueVO;
use Tests\Orisai\ObjectMapper\Doubles\BeforeClassCallbackValueDoesNotMatchVO;
use Tests\Orisai\ObjectMapper\Doubles\CallbacksVisibilityVO;
use Tests\Orisai\ObjectMapper\Doubles\CallbacksVO;
use Tests\Orisai\ObjectMapper\Doubles\CallbacksVoContext;
use Tests\Orisai\ObjectMapper\Doubles\CircularAVO;
use Tests\Orisai\ObjectMapper\Doubles\CircularBVO;
use Tests\Orisai\ObjectMapper\Doubles\CircularCVO;
use Tests\Orisai\ObjectMapper\Doubles\ConstructorUsingVO;
use Tests\Orisai\ObjectMapper\Doubles\DefaultsVO;
use Tests\Orisai\ObjectMapper\Doubles\EmptyVO;
use Tests\Orisai\ObjectMapper\Doubles\FieldNamesVO;
use Tests\Orisai\ObjectMapper\Doubles\InitializingVO;
use Tests\Orisai\ObjectMapper\Doubles\NoDefaultsVO;
use Tests\Orisai\ObjectMapper\Doubles\PropertiesInitVO;
use Tests\Orisai\ObjectMapper\Doubles\PropertiesVisibilityVO;
use Tests\Orisai\ObjectMapper\Doubles\PropertyCallbacksFailureVO;
use Tests\Orisai\ObjectMapper\Doubles\SelfReferenceVO;
use Tests\Orisai\ObjectMapper\Doubles\SkippedPropertiesVO;
use Tests\Orisai\ObjectMapper\Doubles\StructuresVO;
use Tests\Orisai\ObjectMapper\Doubles\TransformingVO;
use Tests\Orisai\ObjectMapper\Toolkit\ProcessingTestCase;
use function sprintf;
use const PHP_VERSION_ID;

final class DefaultProcessorTest extends ProcessingTestCase
{

	private ErrorPrinter $formatter;

	protected function setUp(): void
	{
		parent::setUp();
		$this->formatter = new ErrorVisualPrinter(new TypeToStringConverter());
	}

	public function testMissingRequiredValues(): void
	{
		$vo = null;
		$exception = null;
		$data = [];

		try {
			$vo = $this->processor->process($data, NoDefaultsVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::markTestSkipped('TODO - all structure values are invalid and complete type must be rendered');
		self::assertSame(
			'string: string
nullableString: string||null
untypedString: string
arrayOfMixed: array<mixed>
manyStructures: array<int(unsigned), shape{
	string: string
	nullableString: string||null
	untypedNullableString: string||null
	untypedNull: null
	arrayOfMixed: array<mixed>
}>',
			$this->formatter->printError($exception),
		);
	}

	public function testInvalidData(): void
	{
		$vo = null;
		$exception = null;
		$data = 'wtf';

		try {
			$vo = $this->processor->process($data, NoDefaultsVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			'string: string
nullableString: string||null
untypedString: string
arrayOfMixed: array<mixed>
structure: shape{
	string: string
	nullableString: string||null
	untypedNullableString: string||null
	untypedNull: null
	arrayOfMixed: array<mixed>
}
manyStructures: array<int, shape{
	string: string
	nullableString: string||null
	untypedNullableString: string||null
	untypedNull: null
	arrayOfMixed: array<mixed>
}>',
			$this->formatter->printError($exception),
		);
	}

	public function testInvalidArrayItems(): void
	{
		$vo = null;
		$exception = null;
		$data = [
			'string' => 'foo',
			'nullableString' => null,
			'untypedString' => 'foo',
			'arrayOfMixed' => [],
			'manyStructures' => [
				['test' => 'foo'],
				'badKey' => [],
				'anotherBadKey' => ['string' => null],
			],
		];

		try {
			$vo = $this->processor->process($data, NoDefaultsVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::markTestSkipped(
			'TODO - badKey and anotherBadKey are completely invalid, complete type (including parameters) must be rendered',
		);
		self::assertSame(
			'manyStructures: array[
	0: shape{
		test: Field is unknown.
	}
	badKey: int(unsigned) => value
	anotherBadKey: int(unsigned) => shape{
		string: string
	}
}',
			$this->formatter->printError($exception),
		);
	}

	public function testRequiredValues(): void
	{
		$data = [
			'string' => 'foo',
			'nullableString' => null,
			'untypedString' => 'untyped',
			'arrayOfMixed' => [],
			'manyStructures' => [
				[],
				[],
				[],
			],
		];
		$vo = $this->processor->process($data, NoDefaultsVO::class);

		self::assertInstanceOf(NoDefaultsVO::class, $vo);
		self::assertSame('foo', $vo->string);
		self::assertNull($vo->nullableString);
		self::assertSame('untyped', $vo->untypedString);
		self::assertSame([], $vo->arrayOfMixed);
		self::assertCount(3, $vo->manyStructures);

		foreach ($vo->manyStructures as $structure) {
			self::assertInstanceOf(DefaultsVO::class, $structure);
		}
	}

	public function testStructures(): void
	{
		$data = [
			'structureOrArray' => ['valueWhichIsNotInDefaultsVO' => null],
			'anotherStructureOrArray' => ['string' => 'value of property which is in DefaultsVO'],
			'manyStructures' => [
				[ // Not all properties are defined by NoDefaultsVO, should match DefaultsVO
					'string' => 'example',
					'untypedNull' => null,
				],
				[], // Empty should match DefaultsVO
				[ // Not all properties are defined by DefaultsVO, should match NoDefaultsVO
					'string' => 'example',
					'nullableString' => 'example',
					'untypedString' => 'example',
					'arrayOfMixed' => [],
					'manyStructures' => [],
				],
			],
		];
		$vo = $this->processor->process($data, StructuresVO::class);

		self::assertInstanceOf(StructuresVO::class, $vo);
		self::assertInstanceOf(DefaultsVO::class, $vo->structure);
		self::assertIsArray($vo->structureOrArray);
		self::assertSame(['valueWhichIsNotInDefaultsVO' => null], $vo->structureOrArray);
		self::assertInstanceOf(DefaultsVO::class, $vo->anotherStructureOrArray);
		self::assertSame('value of property which is in DefaultsVO', $vo->anotherStructureOrArray->string);
		self::assertCount(3, $vo->manyStructures);
		self::assertInstanceOf(DefaultsVO::class, $vo->manyStructures[0]);
		self::assertInstanceOf(DefaultsVO::class, $vo->manyStructures[1]);
		self::assertInstanceOf(NoDefaultsVO::class, $vo->manyStructures[2]);
	}

	public function testUnknownValues(): void
	{
		$vo = null;
		$exception = null;
		$data = [
			'unknown' => 'Example',
			123 => 'Numeric example',
			'stringg' => 'foo',
		];

		try {
			$vo = $this->processor->process($data, DefaultsVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			'unknown: Field is unknown.
123: Field is unknown.
stringg: Field is unknown, did you mean `string`?',
			$this->formatter->printError($exception),
		);
	}

	public function testUnknownValuesAllowed(): void
	{
		$options = new Options();
		$options->setAllowUnknownFields();

		$exception = null;
		try {
			$this->processor->process(['unknown' => true], EmptyVO::class, $options);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($exception);
	}

	public function testDefaultValues(): void
	{
		$data = [];
		$vo = $this->processor->process($data, DefaultsVO::class);

		self::assertInstanceOf(DefaultsVO::class, $vo);
		self::assertSame('foo', $vo->string);
		self::assertNull($vo->nullableString);
		self::assertNull($vo->untypedNullableString);
		self::assertNull($vo->untypedNull);
		self::assertSame(
			[
				0 => 'foo',
				'bar' => 'baz',
			],
			$vo->arrayOfMixed,
		);

		// Defaults are not pre-filled by default
		$processed = $this->processor->processWithoutMapping($data, DefaultsVO::class);
		self::assertSame([], $processed);

		// Pre-fill defaults
		$options = new Options();
		$options->setPrefillDefaultValues();
		$processed = $this->processor->processWithoutMapping($data, DefaultsVO::class, $options);
		self::assertSame(
			[
				'string' => 'foo',
				'nullableString' => null,
				'untypedNullableString' => null,
				'untypedNull' => null,
				'arrayOfMixed' => [
					0 => 'foo',
					'bar' => 'baz',
				],
			],
			$processed,
		);
	}

	public function testStdClass(): void
	{
		$data = new stdClass();
		$data->string = 'custom';

		$vo = $this->processor->process($data, DefaultsVO::class);

		self::assertInstanceOf(DefaultsVO::class, $vo);
		self::assertSame('custom', $vo->string);
	}

	public function testPropertiesVisibility(): void
	{
		$data = [
			'public' => 'public',
			'protected' => 'protected',
			'private' => 'private',
		];

		$vo = $this->processor->process($data, PropertiesVisibilityVO::class);

		self::assertInstanceOf(PropertiesVisibilityVO::class, $vo);
		self::assertSame('public', $vo->public);
		self::assertSame('protected', $vo->getProtected());
		self::assertSame('private', $vo->getPrivate());
	}

	public function testNoInitialization(): void
	{
		$options = new Options();
		$options->setPrefillDefaultValues();

		$instance = new stdClass();
		$instance->foo = 'bar';

		$data = [
			'datetime' => '1990-12-31T12:34:56+00:00',
			'instance' => $instance,
			'structure' => [],
		];
		$processedData = $this->processor->processWithoutMapping($data, InitializingVO::class, $options);

		self::assertSame(
			[
				// Returns raw value intentionally instead of value which would be returned by instance serialization
				'datetime' => '1990-12-31T12:34:56+00:00',
				'instance' => $instance,
				'structure' => [
					'string' => 'foo',
					'nullableString' => null,
					'untypedNullableString' => null,
					'untypedNull' => null,
					'arrayOfMixed' => [
						0 => 'foo',
						'bar' => 'baz',
					],
				],
			],
			$processedData,
		);
	}

	public function testInitialization(): void
	{
		$instance = new stdClass();
		$instance->foo = 'bar';

		$data = [
			'datetime' => '1990-12-31T12:34:56+00:00',
			'instance' => $instance,
			'structure' => [],
		];
		$vo = $this->processor->process($data, InitializingVO::class);

		self::assertInstanceOf(InitializingVO::class, $vo);
		self::assertInstanceOf(DateTimeImmutable::class, $vo->datetime);
		self::assertSame('1990-12-31T12:34:56+00:00', $vo->datetime->format(DateTimeInterface::ATOM));
		self::assertInstanceOf(stdClass::class, $vo->instance);
	}

	public function testTransformation(): void
	{
		$options = new Options();
		$options->setFillRawValues();

		$data = [
			'bool' => 'true',
			'int' => '123',
			'float' => '123,456',
			'stdClassOrNull' => '',
		];
		$vo = $this->processor->process($data, TransformingVO::class, $options);

		self::assertInstanceOf(TransformingVO::class, $vo);
		self::assertTrue($vo->bool);
		self::assertSame(123, $vo->int);
		self::assertSame(123.456, $vo->float);
		self::assertNull($vo->stdClassOrNull);

		self::assertSame($data, $vo->getRawValues());
	}

	public function testDontUseConstructor(): void
	{
		$vo = new ConstructorUsingVO('string');
		self::assertSame('string', $vo->string);

		$vo = $this->processor->process(['string' => 'string'], ConstructorUsingVO::class);
		self::assertSame('string', $vo->string);
	}

	public function testSelfReference(): void
	{
		$data = [
			'selfOrNull' => [
				'selfOrNull' => null,
				'another' => 'string',
			],
			'another' => 'string',
		];

		$vo = $this->processor->process($data, SelfReferenceVO::class);

		self::assertInstanceOf(SelfReferenceVO::class, $vo->selfOrNull);
		self::assertNull($vo->selfOrNull->selfOrNull);
	}

	public function testSelfReferenceFail(): void
	{
		$vo = null;
		$exception = null;
		$data = [
			'selfOrNull' => 'string',
			'another' => 'string',
		];

		try {
			$vo = $this->processor->process($data, SelfReferenceVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			<<<'MSG'
selfOrNull: shape{
	selfOrNull: shape{}||null
	another: string
}||null
MSG,
			$this->formatter->printError($exception),
		);
	}

	public function testCircularReference(): void
	{
		$data = [
			'b' => [
				'c' => [
					'as' => [
						[
							'b' => ['c' => null],
						],
						[
							'b' => [
								'c' => ['as' => []],
							],
						],
					],
				],
			],
		];

		$a = $this->processor->process($data, CircularAVO::class);

		$b = $a->b;
		self::assertInstanceOf(CircularBVO::class, $b);

		$c = $b->c;
		self::assertInstanceOf(CircularCVO::class, $c);

		$as = $c->as;
		self::assertCount(2, $as);

		$a1 = $as[0];
		self::assertNull($a1->b->c);

		$a2 = $as[1];
		self::assertSame([], $a2->b->c->as);
	}

	public function testCircularReferenceFail(): void
	{
		$vo = null;
		$exception = null;
		$data = null;

		try {
			$vo = $this->processor->process($data, CircularAVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			<<<'MSG'
b: shape{
	c: shape{
		as: list<int(continuous), shape{}>
	}||null
}
stringOrNull: string||null
MSG,
			$this->formatter->printError($exception),
		);
	}

	public function testCallbacks(): void
	{
		$options = new Options();
		$options->setPrefillDefaultValues();
		$options->addDynamicContext(new CallbacksVoContext(DefaultsVO::class));

		$data = [
			'array' => [
				'foo' => ['bar'],
			],
			'callbackSetValue' => 'givenByUser',
		];

		$processedData = $this->processor->processWithoutMapping($data, CallbacksVO::class, $options);
		$vo = $this->processor->process($data, CallbacksVO::class, $options);

		self::assertSame(
			[
				'array' => [
					'foo' => ['bar'],
					'beforeClassCallback' => [false],
					'afterArrayProcessingCallback' => [false],
					'afterClassCallback' => [false],
				],
				'callbackSetValue' => 'givenByConstructor',
				'structure' => [
					'string' => 'foo',
					'nullableString' => null,
					'untypedNullableString' => null,
					'untypedNull' => null,
					'arrayOfMixed' => [
						0 => 'foo',
						'bar' => 'baz',
					],
				],
				'overriddenDefaultValue' => 'overriddenValue',
				'requiredValue' => 'overriddenValue',
				'immutableDefaultValue' => 'defaultValue_immutable',
			],
			$processedData,
		);

		self::assertSame(
			[
				'foo' => ['bar'],
				'beforeClassCallback' => [true],
				'afterArrayInitializationCallback' => [true],
				'afterClassCallback' => [true],
			],
			$vo->array,
		);
		self::assertInstanceOf(DefaultsVO::class, $vo->structure);
		self::assertSame('overriddenValue', $vo->overriddenDefaultValue);
		self::assertSame('defaultValue_immutable', $vo->immutableDefaultValue);
		self::assertSame('overriddenValue', $vo->requiredValue);
		self::assertSame('givenByConstructor', $vo->callbackSetValue);
	}

	public function testCallbacksVisibility(): void
	{
		$data = [
			'public' => 'a',
			'protected' => 'b',
			'private' => 'c',
			'publicStatic' => 'd',
			'protectedStatic' => 'e',
			'privateStatic' => 'f',
		];

		$vo = $this->processor->process($data, CallbacksVisibilityVO::class);

		self::assertInstanceOf(CallbacksVisibilityVO::class, $vo);
		self::assertSame('a-public', $vo->public);
		self::assertSame('b-protected', $vo->protected);
		self::assertSame('c-private', $vo->private);
		self::assertSame('d-public-static', $vo->publicStatic);
		self::assertSame('e-protected-static', $vo->protectedStatic);
		self::assertSame('f-private-static', $vo->privateStatic);
	}

	public function testPropertyCallbacksFailure(): void
	{
		$vo = null;
		$exception = null;
		$data = [];

		try {
			$vo = $this->processor->process($data, PropertyCallbacksFailureVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			'neverValidated: Check before validation failed, field was never validated
validationFailed: string',
			$this->formatter->printError($exception),
		);
	}

	public function testBeforeClassCallbackMixedValue(): void
	{
		$options = new Options();
		$options->setFillRawValues();

		$vo = null;
		$exception = null;

		try {
			$vo = $this->processor->process(false, BeforeClassCallbackMixedValueVO::class, $options);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			'',
			$this->formatter->printError($exception),
		);
		$vo = $this->processor->process(true, BeforeClassCallbackMixedValueVO::class, $options);
		self::assertTrue($vo->getRawValues());
	}

	public function testBeforeClassCallbackRuleException(): void
	{
		$vo = null;
		$exception = null;
		$data = [];

		try {
			$vo = $this->processor->process($data, BeforeClassCallbackValueDoesNotMatchVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			'Error before class',
			$this->formatter->printError($exception),
		);
	}

	public function testAfterClassCallbackRuleException(): void
	{
		$vo = null;
		$exception = null;
		$data = [
			'string' => 'string',
		];

		try {
			$vo = $this->processor->process($data, AfterClassCallbackValueDoesNotMatchVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			'Error after class',
			$this->formatter->printError($exception),
		);
	}

	public function testAfterClassCallbackCurrentTypeValidationException(): void
	{
		$vo = null;
		$exception = null;
		$data = [
			'string' => 'string',
		];

		try {
			$vo = $this->processor->process($data, AfterClassCallbackCurrentTypeInvalidDataVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			'string: string',
			$this->formatter->printError($exception),
		);
	}

	public function testAfterClassCallbackNewTypeValidationException(): void
	{
		$vo = null;
		$exception = null;
		$data = [
			'string' => 'string',
		];

		try {
			$vo = $this->processor->process($data, AfterClassCallbackNewTypeInvalidDataVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			'shape{
	test
}',
			$this->formatter->printError($exception),
		);
	}

	public function testRequiredNonDefaultFields(): void
	{
		$options = new Options();
		$options->setRequiredFields(RequiredFields::nonDefault());

		$vo = $this->processor->process([
			'required' => null,
		], PropertiesInitVO::class, $options);

		self::assertTrue($vo->isInitialized('required'));
		self::assertTrue($vo->isInitialized('optional'));
		self::assertTrue($vo->isInitialized('structure'));

		self::assertNull($vo->required);
		self::assertNull($vo->optional);
		self::assertInstanceOf(EmptyVO::class, $vo->structure);
	}

	public function testRequireAllFields(): void
	{
		$options = new Options();
		$options->setRequiredFields(RequiredFields::all());

		$vo = $this->processor->process([
			'required' => null,
			'optional' => null,
			'structure' => [],
		], PropertiesInitVO::class, $options);

		self::assertTrue($vo->isInitialized('required'));
		self::assertTrue($vo->isInitialized('optional'));
		self::assertTrue($vo->isInitialized('structure'));

		self::assertNull($vo->required);
		self::assertNull($vo->optional);
		self::assertInstanceOf(EmptyVO::class, $vo->structure);
	}

	public function testRequireAllFieldsError(): void
	{
		$options = new Options();
		$options->setRequiredFields(RequiredFields::all());

		$vo = null;
		$exception = null;
		$data = [];

		try {
			$vo = $this->processor->process($data, DefaultsVO::class, $options);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::markTestSkipped('TODO - all structure values are invalid and complete type must be rendered');
		self::assertSame(
			'string: string
nullableString: string||null
untypedNullableString: string||null
untypedNull: null
arrayOfMixed: array<mixed>',
			$this->formatter->printError($exception),
		);
	}

	public function testRequireNoneFields(): void
	{
		$options = new Options();
		$options->setRequiredFields(RequiredFields::none());

		$vo = $this->processor->process([], PropertiesInitVO::class, $options);

		self::assertFalse($vo->isInitialized('required'));
		self::assertFalse($vo->isInitialized('optional'));
		self::assertFalse($vo->isInitialized('structure'));

		$vo = $this->processor->process([
			'required' => null,
			'optional' => null,
			'structure' => [],
		], PropertiesInitVO::class, $options);

		self::assertTrue($vo->isInitialized('required'));
		self::assertTrue($vo->isInitialized('optional'));
		self::assertTrue($vo->isInitialized('structure'));

		self::assertNull($vo->required);
		self::assertNull($vo->optional);
		self::assertInstanceOf(EmptyVO::class, $vo->structure);
	}

	public function testMappedFieldNames(): void
	{
		$vo = $this->processor->process([
			'original' => 'original',
			'field' => 'property',
			123 => 'integer',
			'swap1' => 'swap2',
			'swap2' => 'swap1',
		], FieldNamesVO::class);

		self::assertSame('original', $vo->original);
		self::assertSame('property', $vo->property);
		self::assertSame('integer', $vo->integer);
		self::assertSame('swap1', $vo->swap1);
		self::assertSame('swap2', $vo->swap2);
	}

	public function testSkipped(): void
	{
		$vo = $this->processor->process([
			'required' => 'required',
			'requiredSkipped' => 'requiredSkipped',
		], SkippedPropertiesVO::class);

		self::assertSame('required', $vo->required);
		self::assertSame('optional', $vo->optional);
		self::assertFalse($vo->isInitialized('requiredSkipped'));
		self::assertFalse($vo->isInitialized('optionalSkipped'));

		$this->processor->processSkippedProperties([
			'requiredSkipped',
			'optionalSkipped',
		], $vo);

		self::assertSame('requiredSkipped', $vo->requiredSkipped);
		self::assertSame('optionalSkipped', $vo->optionalSkipped);
	}

	public function testSkippedNotSent(): void
	{
		$vo = null;
		$exception = null;

		try {
			$vo = $this->processor->process([
				'required' => 'required',
			], SkippedPropertiesVO::class);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertNull($vo);
		self::assertInstanceOf(InvalidData::class, $exception);

		self::assertSame(
			'requiredSkipped: string',
			$this->formatter->printError($exception),
		);
	}

	public function testSkippedInvalidField(): void
	{
		$vo = $this->processor->process([
			'required' => 'required',
			'requiredSkipped' => null,
		], SkippedPropertiesVO::class);
		$exception = null;

		try {
			$this->processor->processSkippedProperties([
				'requiredSkipped',
			], $vo);
		} catch (InvalidData $exception) {
			// Checked bellow
		}

		self::assertInstanceOf(InvalidData::class, $exception);
		self::assertSame(
			'requiredSkipped: string',
			$this->formatter->printError($exception),
		);
	}

	public function testSkippedObjectAlreadyFullyInitialized(): void
	{
		$vo = $this->processor->process([
			'required' => 'required',
			'requiredSkipped' => 'requiredSkipped',
		], SkippedPropertiesVO::class);

		$this->processor->processSkippedProperties([
			'requiredSkipped',
			'optionalSkipped',
		], $vo);

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(
			'Cannot initialize properties "whatever" of "Tests\Orisai\ObjectMapper\Doubles\SkippedPropertiesVO" instance ' .
			'because it has no skipped properties.',
		);

		$this->processor->processSkippedProperties(['whatever'], $vo);
	}

	public function testSkippedPropertyAlreadyInitialized(): void
	{
		$vo = $this->processor->process([
			'required' => 'required',
			'requiredSkipped' => 'requiredSkipped',
		], SkippedPropertiesVO::class);

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(
			'Cannot initialize property "whatever" of "Tests\Orisai\ObjectMapper\Doubles\SkippedPropertiesVO" instance ' .
			'because it is already initialized or does not exist.',
		);

		$this->processor->processSkippedProperties(['whatever'], $vo);
	}

	public function testNotAClass(): void
	{
		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage("Class 'foo' does not exist");

		$this->processor->process([], 'foo');
	}

	public function testNotAValueObject(): void
	{
		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(sprintf(
			"Class '%s' should be subclass of '%s'",
			stdClass::class,
			MappedObject::class,
		));

		$this->processor->process([], stdClass::class);
	}

	public function testAttributes(): void
	{
		if (PHP_VERSION_ID < 8_00_00) {
			self::markTestSkipped('Attributes are supported on PHP 8.0+');
		}

		$data = [
			'string' => 'foo',
		];

		$vo = $this->processor->process($data, AttributesVO::class);

		self::assertInstanceOf(AttributesVO::class, $vo);
		self::assertSame('foo', $vo->string);
	}

}
