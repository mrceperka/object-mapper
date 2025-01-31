<?php declare(strict_types = 1);

namespace Orisai\ObjectMapper\Processing;

use Nette\Utils\Helpers;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\ObjectMapper\Args\Args;
use Orisai\ObjectMapper\Callbacks\AfterCallback;
use Orisai\ObjectMapper\Callbacks\BeforeCallback;
use Orisai\ObjectMapper\Callbacks\Callback;
use Orisai\ObjectMapper\Context\BaseFieldContext;
use Orisai\ObjectMapper\Context\FieldContext;
use Orisai\ObjectMapper\Context\MappedObjectContext;
use Orisai\ObjectMapper\Context\ProcessorCallContext;
use Orisai\ObjectMapper\Context\SkippedPropertiesContext;
use Orisai\ObjectMapper\Context\SkippedPropertyContext;
use Orisai\ObjectMapper\Context\TypeContext;
use Orisai\ObjectMapper\Exception\InvalidData;
use Orisai\ObjectMapper\Exception\ValueDoesNotMatch;
use Orisai\ObjectMapper\MappedObject;
use Orisai\ObjectMapper\Meta\MetaLoader;
use Orisai\ObjectMapper\Meta\Runtime\ClassRuntimeMeta;
use Orisai\ObjectMapper\Meta\Runtime\NodeRuntimeMeta;
use Orisai\ObjectMapper\Meta\Runtime\PropertyRuntimeMeta;
use Orisai\ObjectMapper\Meta\Runtime\RuntimeMeta;
use Orisai\ObjectMapper\Modifiers\FieldNameModifier;
use Orisai\ObjectMapper\Modifiers\SkippedModifier;
use Orisai\ObjectMapper\Rules\MappedObjectArgs;
use Orisai\ObjectMapper\Rules\MappedObjectRule;
use Orisai\ObjectMapper\Rules\RuleManager;
use Orisai\ObjectMapper\Types\MappedObjectType;
use Orisai\ObjectMapper\Types\MessageType;
use Orisai\ObjectMapper\Types\Value;
use stdClass;
use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_map;
use function assert;
use function get_class;
use function implode;
use function in_array;
use function is_a;
use function is_array;
use function sprintf;

final class DefaultProcessor implements Processor
{

	private MetaLoader $metaLoader;

	private RuleManager $ruleManager;

	private ObjectCreator $objectCreator;

	public function __construct(MetaLoader $metaLoader, RuleManager $ruleManager, ObjectCreator $objectCreator)
	{
		$this->metaLoader = $metaLoader;
		$this->ruleManager = $ruleManager;
		$this->objectCreator = $objectCreator;
	}

	/**
	 * @param mixed $data
	 * @throws InvalidData
	 */
	public function process($data, string $class, ?Options $options = null): MappedObject
	{
		$options ??= new Options();
		$type = $this->createMappedObjectType($class, $options);
		$meta = $this->metaLoader->load($class);
		$holder = $this->createHolder($class, $meta->getClass());

		$mappedObjectContext = $this->createMappedObjectContext($options, $type, true);
		$callContext = $this->createProcessorRunContext($class, $meta, $holder);

		$processedData = $this->processData($data, $mappedObjectContext, $callContext);

		$object = $holder->getInstance();
		$this->fillObject($object, $processedData, $data, $mappedObjectContext, $callContext);

		return $object;
	}

	/**
	 * @param mixed                      $data
	 * @param class-string<MappedObject> $class
	 * @return array<int|string, mixed>
	 * @throws InvalidData
	 */
	public function processWithoutMapping($data, string $class, ?Options $options = null): array
	{
		$options ??= new Options();
		$type = $this->createMappedObjectType($class, $options);
		$meta = $this->metaLoader->load($class);
		$holder = $this->createHolder($class, $meta->getClass());

		$mappedObjectContext = $this->createMappedObjectContext($options, $type, false);
		$callContext = $this->createProcessorRunContext($class, $meta, $holder);

		return $this->processData($data, $mappedObjectContext, $callContext);
	}

	private function createTypeContext(Options $options): TypeContext
	{
		$context = new TypeContext($this->metaLoader, $this->ruleManager);

		foreach ($options->getProcessedClasses() as $class) {
			$context = $context->withProcessedClass($class);
		}

		return $context;
	}

	// /////////////// //
	// Base processing //
	// /////////////// //

	/**
	 * @param mixed                              $data
	 * @param ProcessorCallContext<MappedObject> $callContext
	 * @return array<int|string, mixed>
	 * @throws InvalidData
	 */
	private function processData(
		$data,
		MappedObjectContext $mappedObjectContext,
		ProcessorCallContext $callContext
	): array
	{
		$meta = $callContext->getMeta();
		$classMeta = $meta->getClass();

		if ($data instanceof stdClass) {
			$data = (array) $data;
		}

		$data = $this->handleClassCallbacks(
			$data,
			$mappedObjectContext,
			$callContext,
			$classMeta,
			BeforeCallback::class,
		);
		$data = $this->ensureDataProcessable($data, $mappedObjectContext);
		$data = $this->handleFields($data, $mappedObjectContext, $callContext);
		$data = $this->handleClassCallbacks(
			$data,
			$mappedObjectContext,
			$callContext,
			$classMeta,
			AfterCallback::class,
		);
		assert(is_array($data)); // After class callbacks are forced to return array

		return $data;
	}

	/**
	 * @param class-string<MappedObject> $class
	 */
	private function createMappedObjectType(string $class, Options $options): MappedObjectType
	{
		return $this->ruleManager->getRule(MappedObjectRule::class)->createType(
			new MappedObjectArgs($class),
			$this->createTypeContext($options),
		);
	}

	private function createMappedObjectContext(
		Options $options,
		MappedObjectType $type,
		bool $initializeObjects
	): MappedObjectContext
	{
		return new MappedObjectContext(
			$this->metaLoader,
			$this->ruleManager,
			$this,
			$options,
			$type,
			$initializeObjects,
		);
	}

	/**
	 * @template RC of MappedObject
	 * @param class-string<RC> $class
	 * @param ObjectHolder<RC> $holder
	 * @return ProcessorCallContext<RC>
	 */
	private function createProcessorRunContext(
		string $class,
		RuntimeMeta $meta,
		ObjectHolder $holder
	): ProcessorCallContext
	{
		return new ProcessorCallContext($class, $holder, $meta);
	}

	/**
	 * @param mixed $data
	 * @return array<mixed>
	 * @throws InvalidData
	 */
	private function ensureDataProcessable($data, MappedObjectContext $context): array
	{
		if (!is_array($data)) {
			$type = $context->getType();
			$type->markInvalid();

			throw InvalidData::create($type, Value::of($data));
		}

		return $data;
	}

	/**
	 * @param int|string $fieldName
	 */
	private function fieldNameToPropertyName($fieldName, RuntimeMeta $meta): string
	{
		$map = $meta->getFieldsPropertiesMap();

		return $map[$fieldName] ?? (string) $fieldName;
	}

	/**
	 * @return int|string
	 */
	private function propertyNameToFieldName(string $propertyName, PropertyRuntimeMeta $meta)
	{
		$fieldNameMeta = $meta->getModifier(FieldNameModifier::class);
		if ($fieldNameMeta !== null) {
			return $fieldNameMeta->getArgs()->name;
		}

		return $propertyName;
	}

	// /////////////////// //
	// Properties / Fields //
	// /////////////////// //

	/**
	 * @param array<int|string, mixed>           $data
	 * @param ProcessorCallContext<MappedObject> $callContext
	 * @return array<int|string, mixed>
	 * @throws InvalidData
	 */
	private function handleFields(
		array $data,
		MappedObjectContext $mappedObjectContext,
		ProcessorCallContext $callContext
	): array
	{
		$data = $this->handleSentFields($data, $mappedObjectContext, $callContext);
		$data = $this->handleMissingFields($data, $mappedObjectContext, $callContext);

		$type = $mappedObjectContext->getType();

		if ($type->hasInvalidFields()) {
			throw InvalidData::create($type, Value::none());
		}

		return $data;
	}

	/**
	 * @param array<int|string, mixed>           $data
	 * @param ProcessorCallContext<MappedObject> $callContext
	 * @return array<int|string, mixed>
	 */
	private function handleSentFields(
		array $data,
		MappedObjectContext $mappedObjectContext,
		ProcessorCallContext $callContext
	): array
	{
		$type = $mappedObjectContext->getType();
		$options = $mappedObjectContext->getOptions();

		$meta = $callContext->getMeta();
		$propertiesMeta = $meta->getProperties();
		$propertyNames = array_keys($propertiesMeta);

		foreach ($data as $fieldName => $value) {
			// Skip invalid field
			if ($type->isFieldInvalid($fieldName)) {
				continue;
			}

			$propertyName = $this->fieldNameToPropertyName($fieldName, $meta);

			// Unknown field
			if (!isset($propertiesMeta[$propertyName])) {
				// Remove field from data
				unset($data[$fieldName]);

				if ($options->isAllowUnknownFields()) {
					continue;
				}

				// Add error to type
				$hintedPropertyName = Helpers::getSuggestion($propertyNames, $propertyName);
				$hintedFieldName = $hintedPropertyName !== null ?
					$this->propertyNameToFieldName(
						$hintedPropertyName,
						$propertiesMeta[$hintedPropertyName],
					)
					: null;
				$hint = ($hintedFieldName !== null ? sprintf(', did you mean `%s`?', $hintedFieldName) : '.');
				$type->overwriteInvalidField(
					$fieldName,
					ValueDoesNotMatch::create(
						new MessageType(sprintf('Field is unknown%s', $hint)),
						Value::of($value),
					),
				);

				continue;
			}

			$propertyMeta = $propertiesMeta[$propertyName];
			$fieldContext = $this->createFieldContext($mappedObjectContext, $propertyMeta, $fieldName, $propertyName);

			// Skip skipped property
			if (
				$mappedObjectContext->shouldMapDataToObjects()
				&& $propertyMeta->getModifier(SkippedModifier::class) !== null
			) {
				$callContext->addSkippedProperty(
					$propertyName,
					new SkippedPropertyContext($fieldName, $value, false),
				);
				unset($data[$fieldName]);

				continue;
			}

			// Process field value with property rules
			try {
				$data[$fieldName] = $this->processProperty(
					$value,
					$fieldContext,
					$callContext,
					$propertyMeta,
				);
			} catch (ValueDoesNotMatch | InvalidData $exception) {
				$type->overwriteInvalidField($fieldName, $exception);
			}
		}

		return $data;
	}

	/**
	 * @param array<int|string, mixed>           $data
	 * @param ProcessorCallContext<MappedObject> $callContext
	 * @return array<string>
	 */
	private function findMissingProperties(array $data, ProcessorCallContext $callContext): array
	{
		$meta = $callContext->getMeta();

		return array_diff(
			array_keys($meta->getProperties()),
			array_map(
				fn ($fieldName): string => $this->fieldNameToPropertyName($fieldName, $meta),
				array_keys($data),
			),
		);
	}

	/**
	 * @param ProcessorCallContext<MappedObject> $callContext
	 * @return array<string>
	 */
	private function getSkippedProperties(ProcessorCallContext $callContext): array
	{
		return array_keys($callContext->getSkippedProperties());
	}

	/**
	 * @param array<int|string, mixed>           $data
	 * @param ProcessorCallContext<MappedObject> $callContext
	 * @return array<int|string, mixed>
	 */
	private function handleMissingFields(
		array $data,
		MappedObjectContext $mappedObjectContext,
		ProcessorCallContext $callContext
	): array
	{
		$type = $mappedObjectContext->getType();
		$options = $mappedObjectContext->getOptions();
		$initializeObjects = $mappedObjectContext->shouldMapDataToObjects();

		$propertiesMeta = $callContext->getMeta()->getProperties();

		$missingProperties = $this->findMissingProperties($data, $callContext);
		$requiredFields = $options->getRequiredFields();
		$fillDefaultValues = $initializeObjects || $options->isPrefillDefaultValues();

		$skippedProperties = $this->getSkippedProperties($callContext);

		foreach ($missingProperties as $missingProperty) {
			// Skipped properties are not considered missing, they are just processed later
			if (in_array($missingProperty, $skippedProperties, true)) {
				continue;
			}

			$propertyMeta = $propertiesMeta[$missingProperty];
			$defaultMeta = $propertyMeta->getDefault();
			$missingField = $this->propertyNameToFieldName($missingProperty, $propertyMeta);

			if ($requiredFields === RequiredFields::nonDefault() && $defaultMeta->hasValue()) {
				// Add default value if defaults are not required and should be used
				// If VOs are initialized then values are always prefilled - user can work with them in after callback,
				//   and they are defined by VO anyway
				if ($fillDefaultValues) {
					$data[$missingField] = $defaultMeta->getValue();
				}
			} elseif (
				$requiredFields === RequiredFields::nonDefault()
				&& is_a($propertyMeta->getRule()->getType(), MappedObjectRule::class, true)
			) {
				// Try to initialize object from empty array when no data given
				// Mapped object in compound type is not supported (allOf, anyOf)
				// Used only in default mode - if all or none values are required then we need differentiate whether user sent value or not
				$mappedObjectArgs = $propertyMeta->getRule()->getArgs();
				assert($mappedObjectArgs instanceof MappedObjectArgs);
				try {
					$data[$missingField] = $initializeObjects
						? $this->process([], $mappedObjectArgs->type, $options->createClone())
						: $this->processWithoutMapping([], $mappedObjectArgs->type, $options->createClone());
				} catch (InvalidData $exception) {
					$type->overwriteInvalidField(
						$missingField,
						InvalidData::create($exception->getType(), Value::none()),
					);
				}
			} elseif ($requiredFields !== RequiredFields::none() && !$type->isFieldInvalid($missingField)) {
				// Field is missing and have no default value, mark as invalid
				$propertyRuleMeta = $propertyMeta->getRule();
				$propertyRule = $this->ruleManager->getRule($propertyRuleMeta->getType());
				$type->overwriteInvalidField(
					$missingField,
					ValueDoesNotMatch::create(
						$propertyRule->createType(
							$propertyRuleMeta->getArgs(),
							$this->createTypeContext($options),
						),
						Value::none(),
					),
				);
			}

			// Return skipped property separately
			if (
				array_key_exists($missingField, $data)
				&& $mappedObjectContext->shouldMapDataToObjects()
				&& $propertyMeta->getModifier(SkippedModifier::class) !== null
			) {
				$callContext->addSkippedProperty(
					$missingProperty,
					new SkippedPropertyContext($missingField, $data[$missingField], true),
				);
				unset($data[$missingField]);

				continue;
			}
		}

		return $data;
	}

	// //////////////// //
	// Property / Field //
	// //////////////// //

	/**
	 * @param int|string $fieldName
	 */
	private function createFieldContext(
		MappedObjectContext $mappedObjectContext,
		PropertyRuntimeMeta $meta,
		$fieldName,
		string $propertyName
	): FieldContext
	{
		$parentType = $mappedObjectContext->getType();

		return new FieldContext(
			$this->metaLoader,
			$this->ruleManager,
			$this,
			$mappedObjectContext->getOptions()->createClone(),
			$parentType->getFields()[$fieldName],
			$meta->getDefault(),
			$mappedObjectContext->shouldMapDataToObjects(),
			$fieldName,
			$propertyName,
		);
	}

	/**
	 * @param mixed                              $value
	 * @param ProcessorCallContext<MappedObject> $callContext
	 * @return mixed
	 * @throws ValueDoesNotMatch
	 * @throws InvalidData
	 */
	private function processProperty(
		$value,
		FieldContext $fieldContext,
		ProcessorCallContext $callContext,
		PropertyRuntimeMeta $meta
	)
	{
		$value = $this->applyCallbacks($value, $fieldContext, $callContext, $meta, BeforeCallback::class);
		$value = $this->processPropertyRules($value, $fieldContext, $meta);
		$value = $this->applyCallbacks($value, $fieldContext, $callContext, $meta, AfterCallback::class);

		return $value;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 * @throws ValueDoesNotMatch
	 * @throws InvalidData
	 */
	private function processPropertyRules($value, FieldContext $fieldContext, PropertyRuntimeMeta $meta)
	{
		$ruleMeta = $meta->getRule();
		$rule = $this->ruleManager->getRule($ruleMeta->getType());

		return $rule->processValue(
			$value,
			$ruleMeta->getArgs(),
			$fieldContext,
		);
	}

	// ///////// //
	// Callbacks //
	// ///////// //

	/**
	 * @param mixed                              $data
	 * @param ProcessorCallContext<MappedObject> $callContext
	 * @param class-string<Callback<Args>>       $callbackType
	 * @return mixed
	 * @throws InvalidData
	 */
	private function handleClassCallbacks(
		$data,
		MappedObjectContext $mappedObjectContext,
		ProcessorCallContext $callContext,
		ClassRuntimeMeta $meta,
		string $callbackType
	)
	{
		$type = $mappedObjectContext->getType();

		try {
			$data = $this->applyCallbacks($data, $mappedObjectContext, $callContext, $meta, $callbackType);
		} catch (ValueDoesNotMatch | InvalidData $exception) {
			$caughtType = $exception->getType();

			// User thrown type is not the actual type from MappedObjectContext
			if ($caughtType !== $type) {
				$type->addError($exception);

				throw InvalidData::create($type, Value::none());
			}

			throw InvalidData::create($type, $exception->getValue());
		}

		return $data;
	}

	/**
	 * @param mixed                                $data
	 * @param FieldContext|MappedObjectContext     $baseFieldContext
	 * @param ProcessorCallContext<MappedObject>   $callContext
	 * @param ClassRuntimeMeta|PropertyRuntimeMeta $meta
	 * @param class-string<Callback<Args>>         $callbackType
	 * @return mixed
	 * @throws ValueDoesNotMatch
	 * @throws InvalidData
	 */
	private function applyCallbacks(
		$data,
		BaseFieldContext $baseFieldContext,
		ProcessorCallContext $callContext,
		NodeRuntimeMeta $meta,
		string $callbackType
	)
	{
		$holder = $callContext->getObjectHolder();

		foreach ($meta->getCallbacks() as $callback) {
			if ($callback->getType() === $callbackType) {
				$data = $callbackType::invoke(
					$data,
					$callback->getArgs(),
					$holder,
					$baseFieldContext,
				);
			}
		}

		return $data;
	}


	// ///////////// //
	// Mapped Object //
	// ///////////// //

	/**
	 * @param array<int|string, mixed>           $data
	 * @param mixed                              $rawData
	 * @param ProcessorCallContext<MappedObject> $callContext
	 */
	private function fillObject(
		MappedObject $object,
		array $data,
		$rawData,
		MappedObjectContext $mappedObjectContext,
		ProcessorCallContext $callContext
	): void
	{
		$type = $mappedObjectContext->getType();
		$options = $mappedObjectContext->getOptions();
		$meta = $callContext->getMeta();

		// Set raw data
		if ($options->isFillRawValues()) {
			$object->setRawValues($rawData);
		}

		// Reset mapped properties state
		$propertiesMeta = $callContext->getMeta()->getProperties();
		foreach ($propertiesMeta as $propertyName => $propertyMeta) {
			unset($object->$propertyName);
		}

		// Set processed data
		foreach ($data as $fieldName => $value) {
			$propertyName = $this->fieldNameToPropertyName($fieldName, $meta);
			$object->$propertyName = $value;
		}

		// Set skipped properties
		$skippedProperties = $callContext->getSkippedProperties();
		if ($skippedProperties !== []) {
			$partial = new SkippedPropertiesContext($type, $options);
			$object->setSkippedPropertiesContext($partial);

			foreach ($skippedProperties as $propertyName => $skippedPropertyContext) {
				$partial->addSkippedProperty($propertyName, $skippedPropertyContext);
			}
		}
	}

	/**
	 * @template H of MappedObject
	 * @param class-string<H> $class
	 * @param H|null          $object
	 * @return ObjectHolder<H>
	 */
	private function createHolder(string $class, ClassRuntimeMeta $meta, ?MappedObject $object = null): ObjectHolder
	{
		return new ObjectHolder($this->objectCreator, $meta, $class, $object);
	}

	// ////////////// //
	// Late processing //
	// ////////////// //

	/**
	 * @param array<string> $properties
	 * @throws InvalidData
	 */
	public function processSkippedProperties(
		array $properties,
		MappedObject $object,
		?Options $options = null
	): void
	{
		$class = get_class($object);

		// Object has no skipped properties
		if (!$object->hasSkippedPropertiesContext()) {
			throw InvalidState::create()
				->withMessage(sprintf(
					'Cannot initialize properties "%s" of "%s" instance because it has no skipped properties.',
					implode(', ', $properties),
					$class,
				));
		}

		$skippedPropertiesContext = $object->getSkippedPropertiesContext();

		$type = $skippedPropertiesContext->getType();
		$options ??= $skippedPropertiesContext->getOptions();
		$mappedObjectContext = $this->createMappedObjectContext($options, $type, true);
		$skippedProperties = $skippedPropertiesContext->getSkippedProperties();

		$meta = $this->metaLoader->load($class);
		$holder = $this->createHolder($class, $meta->getClass(), $object);
		$callContext = $this->createProcessorRunContext($class, $meta, $holder);
		$propertiesMeta = $this->metaLoader->load($class)->getProperties();

		foreach ($properties as $propertyName) {
			// Property is initialized or does not exist
			if (!array_key_exists($propertyName, $skippedProperties)) {
				throw InvalidState::create()
					->withMessage(sprintf(
						'Cannot initialize property "%s" of "%s" instance because it is already initialized or does not exist.',
						$propertyName,
						$class,
					));
			}

			$skippedPropertyContext = $skippedProperties[$propertyName];
			$propertyMeta = $propertiesMeta[$propertyName];

			$fieldName = $skippedPropertyContext->getFieldName();
			$fieldContext = $this->createFieldContext($mappedObjectContext, $propertyMeta, $fieldName, $propertyName);

			// Process field value with property rules
			if ($skippedPropertyContext->isDefault()) {
				$processed = $skippedPropertyContext->getValue();
			} else {
				try {
					$processed = $this->processProperty(
						$skippedPropertyContext->getValue(),
						$fieldContext,
						$callContext,
						$propertyMeta,
					);
				} catch (ValueDoesNotMatch | InvalidData $exception) {
					$type->overwriteInvalidField($fieldName, $exception);

					continue;
				}
			}

			$object->$propertyName = $processed;
			$skippedPropertiesContext->removeSkippedProperty($propertyName);
		}

		// If any of fields is invalid, throw error
		if ($type->hasInvalidFields()) {
			throw InvalidData::create($type, Value::none());
		}

		// Object is fully initialized, remove partial context
		if ($skippedPropertiesContext->getSkippedProperties() === []) {
			$object->setSkippedPropertiesContext(null);
		}
	}

}
