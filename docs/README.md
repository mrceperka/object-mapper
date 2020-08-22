# Object Mapper

Raw data mapping to validated objects

## Content

TODO

## Intro

TODO

## Setup

TODO
- basic
- nette

## Processing

TODO
- processor
- handling exceptions
    - orisai/exceptions
- formatters

## Rules

TODO

#### AllOfRule
- TODO

#### AnyOfRule
- TODO

#### ArrayOfRule
- TODO

#### BoolRule

Expects bool

```php
use Orisai\ObjectMapper\Annotation\Expect\BoolValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /** @BoolValue() */
    public bool $field;

}
```

Parameters:
- `castBoolLike`
    - accepts also `0` (int), `1` (int), `'true'` (string, any case), `'false'` (string, any case)
    - value is casted to respective bool value
    - default `false`

#### DateTimeRule
- TODO

#### FloatRule

Expects float or int
Int is casted to float

```php
use Orisai\ObjectMapper\Annotation\Expect\FloatValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /** @FloatValue() */
    public float $field;

}
```

Parameters:
- `castFloatLike`
    - accepts also numeric strings (float and int)
    - value is casted to respective float value
    - default `false`
    - TODO - zdokumentovat formát
- `unsigned`
    - accepts only positive numbers
    - default `true`
- `min`
    - minimal accepted value
    - default `null`
    - e.g. `10.0`
- `max`
    - maximal accepted value
    - default `null`
    - e.g. `100.0`

#### InstanceRule

Expects an instance of class
Use ObjectRule to accept any object

```php
use Orisai\ObjectMapper\Annotation\Expect\InstanceValue;
use Orisai\ObjectMapper\ValueObject;
use stdClass;

class VO extends ValueObject
{

    /** @InstanceValue(stdClass::class) */
    public stdClass $field;

}
```

Parameters:
- `type`
    - type of required instance
    - required
    - e.g. `stdClass::class`

#### IntRule

Expects int

```php
use Orisai\ObjectMapper\Annotation\Expect\IntValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /** @IntValue() */
    public int $field;

}
```

Parameters:
- `castIntLike`
    - accepts also numeric strings (int)
    - value is casted to respective int value
    - default `false`
    - TODO - zdokumentovat formát
- `unsigned`
    - accepts only positive numbers
    - default `true`
- `min`
    - minimal accepted value
    - default `null`
    - e.g. `10`
- `max`
    - maximal accepted value
    - default `null`
    - e.g. `100`

#### ListOfRule
- TODO

#### MixedRule

Expects any value

```php
use Orisai\ObjectMapper\Annotation\Expect\MixedValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /**
     * @var mixed
     * @MixedValue
     */
    public $field;

}
```

Parameters:
- no parameters

#### NullRule

Expects null

```php
use Orisai\ObjectMapper\Annotation\Expect\NullValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /**
     * @var null
     * @NullValue()
     */
    public $field;

}
```

Parameters:
- `castEmptyString`
    - accepts any string with only empty characters
    - value is casted to null
    - default `false`
    - e.g. `''`, `"\t"` ,`"\t\n\r""`

#### ObjectRule

Expects any object
Use InstanceRule to accept instance of specific type

```php
use Orisai\ObjectMapper\Annotation\Expect\ObjectValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /** @ObjectValue() */
    public object $field;

}
```

Parameters:
- no parameters

#### ScalarRule

Expects any scalar value - int|float|string|bool

```php
use Orisai\ObjectMapper\Annotation\Expect\ScalarValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /**
     * @var int|float|string|bool
     * @ScalarValue()
     */
    public $field;

}
```

Parameters:
- no parameters

#### StringRule

Expects string

```php
use Orisai\ObjectMapper\Annotation\Expect\StringValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /** @StringValue() */
    public string $field;

}
```

Parameters:
- `minLength`
    - minimal string length
    - default `null`
    - e.g. `10`
- `maxLength`
    - maximal string length
    - default `null`
    - e.g. `100`
- `notEmpty`
    - string must not contain *only* empty characters
    - default `false`
    - e.g. `''`, `"\t"` ,`"\t\n\r""`
- `pattern`
    - regex pattern which must match
    - default `null`
    - e.g. `/[\s\S]/`

#### StructureRule
- TODO

#### ValueEnumRule

Expects any of values from given list

```php
use Orisai\ObjectMapper\Annotation\Expect\ValueEnum;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    public const ENUM_VALUES = [
        'first',
        'second',
        'third'
    ];

    /**
     * @var mixed
     * @ValueEnum(VO::ENUM_VALUES)
     */
    public $field;

}
```

Parameters:
- `useKeys`
    - use keys for enumeration instead of values

#### UrlRule
- TODO

### Add rules
- rule manager, mapping

## Callbacks

TODO
- before
- after
- services
    - base
    - nette
- non-static/static
- class/method
- errors
- context
- return type

## ValueObject

- mapped fields (properties) - each need own rule
- rules/callbacks/docs inheritance
- magic

## Documentation

TODO
- annotations
- formatters
    - defaults
    - docs
    - type

## Metadata

TODO
- meta sources
    - annotations
