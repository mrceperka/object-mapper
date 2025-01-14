<?php declare(strict_types = 1);

namespace Tests\Orisai\ObjectMapper\Unit\Printers;

use Orisai\ObjectMapper\MappedObject;
use Orisai\ObjectMapper\Printers\TypeToStringConverter;
use Orisai\ObjectMapper\Printers\TypeVisualPrinter;
use Orisai\ObjectMapper\Types\ArrayType;
use Orisai\ObjectMapper\Types\CompoundType;
use Orisai\ObjectMapper\Types\EnumType;
use Orisai\ObjectMapper\Types\MappedObjectType;
use Orisai\ObjectMapper\Types\MessageType;
use Orisai\ObjectMapper\Types\SimpleValueType;
use PHPUnit\Framework\TestCase;

/**
 * @todo - test all options, check levels (may be not used) and separators usage (improvements)
 */
final class TypeVisualPrinterTest extends TestCase
{

	private TypeVisualPrinter $formatter;

	protected function setUp(): void
	{
		$this->formatter = new TypeVisualPrinter(new TypeToStringConverter());
	}

	public function testMessage(): void
	{
		$type = new MessageType('test');

		self::assertSame(
			'test',
			$this->formatter->printType($type),
		);
	}

	public function testSimple(): void
	{
		$type1 = new SimpleValueType('string');

		self::assertSame(
			'string',
			$this->formatter->printType($type1),
		);

		$type2 = new SimpleValueType('int');
		$type2->addKeyValueParameter('first', 'value');
		$type2->addKeyParameter('second');

		self::assertSame(
			"int(first: 'value', second)",
			$this->formatter->printType($type2),
		);
	}

	public function testEnum(): void
	{
		$cases = [
			'key' => 'foo',
			'key2' => 'bar',
			'key3' => 123,
			'key4' => 123.456,
			'key5' => true,
			'key6' => false,
		];
		$type = new EnumType($cases);

		self::assertSame(
			'enum(foo, bar, 123, 123.456, true, false)',
			$this->formatter->printType($type),
		);
	}

	public function testArray(): void
	{
		$type1Value = new SimpleValueType('test');
		$type1Value->addKeyParameter('parameter');
		$type1 = ArrayType::forArray(null, $type1Value);

		self::assertSame(
			'array<test(parameter)>',
			$this->formatter->printType($type1),
		);

		$type2 = ArrayType::forArray(new SimpleValueType('string'), new SimpleValueType('test'));

		self::assertSame(
			'array<string, test>',
			$this->formatter->printType($type2),
		);

		$type3 = ArrayType::forArray(new SimpleValueType('string'), new SimpleValueType('test'));
		$type3->addKeyValueParameter('foo', 'bar');
		$type3->addKeyValueParameter('baz', 123);

		self::assertSame(
			"array(foo: 'bar', baz: 123)<string, test>",
			$this->formatter->printType($type3),
		);

		$type4Key = CompoundType::createOrType();
		$type4Key->addSubtype(0, new SimpleValueType('string'));
		$type4Key->addSubtype(1, new SimpleValueType('int'));
		$type4 = ArrayType::forArray(
			$type4Key,
			ArrayType::forArray(new SimpleValueType('string'), new SimpleValueType('test')),
		);

		self::assertSame(
			'string||int',
			$this->formatter->printType($type4Key),
		);
		self::assertSame(
			'array<string||int, array<string, test>>',
			$this->formatter->printType($type4),
		);
	}

	public function testList(): void
	{
		$type1 = ArrayType::forList(null, new SimpleValueType('string'));

		self::assertSame(
			'list<string>',
			$this->formatter->printType($type1),
		);

		$type2 = ArrayType::forList(null, new SimpleValueType('string'));
		$type2->addKeyValueParameter('foo', 'bar');

		self::assertSame(
			"list(foo: 'bar')<string>",
			$this->formatter->printType($type2),
		);
	}

	public function testCompound(): void
	{
		//TODO - brackets
		$subtype1 = CompoundType::createAndType();
		$subtype1->addSubtype(0, new SimpleValueType('int'));
		$subtype1->addSubtype(1, new SimpleValueType('float'));

		$subtype2 = CompoundType::createAndType();
		$subtype2->addSubtype(0, new SimpleValueType('foo'));
		$subtype2->addSubtype(1, new SimpleValueType('bar'));

		$type1 = CompoundType::createOrType();
		$type1->addSubtype(0, $subtype1);
		$type1->addSubtype(1, $subtype2);

		self::assertSame(
			'int&&float||foo&&bar',
			$this->formatter->printType($type1),
		);
	}

	public function testStructure(): void
	{
		$type1 = new MappedObjectType(MappedObject::class);
		$type1->addField('0', new SimpleValueType('t'));
		$type1->addField('a', new SimpleValueType('t'));

		self::assertSame(
			'shape{
	0: t
	a: t
}',
			$this->formatter->printType($type1),
		);
	}

}
