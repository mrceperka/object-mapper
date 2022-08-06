<?php declare(strict_types = 1);

namespace Orisai\ObjectMapper\Attributes\Docs;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\Common\Annotations\Annotation\Target;
use Orisai\ObjectMapper\Attributes\AnnotationFilter;
use Orisai\ObjectMapper\Docs\DescriptionDoc;

/**
 * @Annotation
 * @NamedArgumentConstructor()
 * @Target({"CLASS", "PROPERTY"})
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
final class Description implements DocumentationAttribute
{

	private string $message;

	public function __construct(string $message)
	{
		$this->message = AnnotationFilter::filterMultilineDocblock($message);
	}

	public function getType(): string
	{
		return DescriptionDoc::class;
	}

	public function getArgs(): array
	{
		return [
			'message' => $this->message,
		];
	}

}
