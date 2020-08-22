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

Expects all rules to match
After first failure is validation terminated, other rules are skipped
Rules are executed from first to last

```php
use Orisai\ObjectMapper\Annotation\Expect\AllOf;
use Orisai\ObjectMapper\Annotation\Expect\IntValue;
use Orisai\ObjectMapper\Annotation\Expect\NullValue;
use Orisai\ObjectMapper\Annotation\Expect\StringValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /**
     * @var string|int|null
     * @AllOf(
     *      @StringValue(),
     *      @IntValue(),
     *      @NullValue(),
     * )
     */
    public $field;

}
```

Parameters:
- `rules`
    - accepts list of rules by which is the field validated
    - required

#### AnyOfRule

Expects any of rules to match
Rules are executed from first to last
Result of first rule which match is used, other rules are skipped

```php
use Orisai\ObjectMapper\Annotation\Expect\AnyOf;
use Orisai\ObjectMapper\Annotation\Expect\IntValue;
use Orisai\ObjectMapper\Annotation\Expect\NullValue;
use Orisai\ObjectMapper\Annotation\Expect\StringValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /**
     * @var string|int|null
     * @AnyOf(
     *      @StringValue(),
     *      @IntValue(),
     *      @NullValue(),
     * )
     */
    public $field;

}
```

Parameters:
- `rules`
    - accepts list of rules by which is the field validated
    - required

#### ArrayOfRule

Expects array

```php
use Orisai\ObjectMapper\Annotation\Expect\ArrayOf;
use Orisai\ObjectMapper\Annotation\Expect\IntValue;
use Orisai\ObjectMapper\Annotation\Expect\MixedValue;
use Orisai\ObjectMapper\Annotation\Expect\StringValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /**
     * @var array<mixed>
     * @ArrayOf(
     *      @MixedValue()
     * )
     */
    public array $field;

    /**
     * @var array<string, int>
     * @ArrayOf(
     *      keyType=@StringValue(),
     *      itemType=@IntValue(),
     * )
     */
    public array $anotherField;

}
```

Parameters:
- `itemType`
    - accepts rule which is used to validate items
    - required
- `keyType`
    - accepts rule which is used to validate items
    - default `null` - keys are not validated
- `minItems`
    - minimal count of items
    - default `null` - no limit
    - e.g. `10`
- `maxItems`
    - maximal count of items
    - if limit is exceeded then no other validations of array are performed
    - default `null` - no limit
    - e.g. `100`
- `mergeDefaults`
    - merge default value into array after it is validated
    - default `false` - default is not merged

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
    - default `false` - bool like are not casted

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
    - default `false` - float like are not casted
    - TODO - zdokumentovat formát
- `unsigned`
    - accepts only positive numbers
    - default `true` - only positive numbers are accepted
- `min`
    - minimal accepted value
    - default `null` - no limit
    - e.g. `10.0`
- `max`
    - maximal accepted value
    - default `null` - no limit
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
    - default `false` - int like are not casted
    - TODO - zdokumentovat formát
- `unsigned`
    - accepts only positive numbers
    - default `true` - only positive numbers are accepted
- `min`
    - minimal accepted value
    - default `null` - no limit
    - e.g. `10`
- `max`
    - maximal accepted value
    - default `null` - no limit
    - e.g. `100`

#### ListOfRule

Expects list
All keys must be incremental integers

```php
use Orisai\ObjectMapper\Annotation\Expect\ListOf;
use Orisai\ObjectMapper\Annotation\Expect\MixedValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /**
     * @var array<int, mixed>
     * @ListOf(
     *      @MixedValue()
     * )
     */
    public array $field;

}
```

Parameters:
- `itemType`
    - accepts rule which is used to validate items
    - required
- `minItems`
    - minimal count of items
    - default `null` - no limit
    - e.g. `10`
- `maxItems`
    - maximal count of items
    - if limit is exceeded then no other validations of array are performed
    - default `null` - no limit
    - e.g. `100`
- `mergeDefaults`
    - merge default value into array after it is validated
    - default `false` - default is not merged

#### MixedRule

Expects any value

```php
use Orisai\ObjectMapper\Annotation\Expect\MixedValue;
use Orisai\ObjectMapper\ValueObject;

class VO extends ValueObject
{

    /**
     * @var mixed
     * @MixedValue()
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
    - default `false` - empty strings are not casted
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
    - default `null` - no limit
    - e.g. `10`
- `maxLength`
    - maximal string length
    - default `null` - no limit
    - e.g. `100`
- `notEmpty`
    - string must not contain *only* empty characters
    - default `false` - empty strings are allowed
    - e.g. `''`, `"\t"` ,`"\t\n\r""`
- `pattern`
    - regex pattern which must match
    - default `null` - no validation by pattern
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
    - default `false` - values are used for enumeration

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
