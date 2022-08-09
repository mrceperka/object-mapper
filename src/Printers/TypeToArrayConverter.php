<?php declare(strict_types = 1);

namespace Orisai\ObjectMapper\Printers;

use Orisai\ObjectMapper\Types\TypeParameter;
use function array_key_last;

/**
 * @implements TypeToPrimitiveConverter<array<mixed>>
 */
final class TypeToArrayConverter implements TypeToPrimitiveConverter
{

	public function printMessage(string $message): array
	{
		return [
			'type' => 'message',
			'message' => $message,
		];
	}

	public function printSimpleValue(string $name, array $parameters): array
	{
		return [
			'type' => 'simple',
			'name' => $name,
			'parameters' => $parameters,
		];
	}

	public function printEnum(array $values): array
	{
		return [
			'type' => 'enum',
			'parameters' => $values,
		];
	}

	/**
	 * @param array<int|string, TypeParameter> $parameters
	 * @return array<mixed>
	 */
	public function printParameters(array $parameters): array
	{
		$processed = [];
		foreach ($parameters as $parameter) {
			$processed[] = [
				'key' => $parameter->getKey(),
				'value' => $parameter->getValue(),
			];
		}

		return $processed;
	}

	public function printCompound(string $operator, array $subtypes): array
	{
		return [
			'type' => 'compound',
			'operator' => $operator,
			'subtypes' => $subtypes,
		];
	}

	public function printArray(
		string $name,
		array $parameters,
		$keyType,
		$itemType,
		array $invalidPairs = []
	): array
	{
		return [
			'type' => $name,
			'parameters' => $this->printParameters($parameters),
			'key' => $keyType,
			'item' => $itemType,
			'invalidPairs' => $this->printInvalidPairs($invalidPairs),
		];
	}

	/**
	 * @param array<int|string, array{array<mixed>|null, array<mixed>|null}> $invalidPairs
	 * @return array<int|string, array{key: array<mixed>|null, value: array<mixed>|null}>
	 */
	private function printInvalidPairs(array $invalidPairs): array
	{
		$processed = [];
		foreach ($invalidPairs as $key => [$pairKey, $pairValue]) {
			$processed[$key] = [
				'key' => $pairKey,
				'value' => $pairValue,
			];
		}

		return $processed;
	}

	public function printShape(array $fields, array $errors = []): array
	{
		return [
			'type' => 'shape',
			'fields' => $fields,
			'errors' => $errors,
		];
	}

	public function printError(array $pathNodes, array $fields, array $errors): array
	{
		$printed = $this->printShape($fields, $errors);

		if ($pathNodes === []) {
			return $printed;
		}

		$tree = [];
		$lastNode = &$tree;
		$lastKey = array_key_last($pathNodes);
		foreach ($pathNodes as $key => $node) {
			if ($key === $lastKey) {
				$lastNode[$node] = $printed;
			} else {
				$lastNode[$node] = [];
				$lastNode = &$lastNode[$node];
			}
		}

		return $tree;
	}

}
