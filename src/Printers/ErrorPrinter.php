<?php declare(strict_types = 1);

namespace Orisai\ObjectMapper\Printers;

use Orisai\ObjectMapper\Exception\InvalidData;

interface ErrorPrinter
{

	/**
	 * @param array<string> $pathNodes
	 * @return mixed
	 */
	public function printError(InvalidData $exception, array $pathNodes = []);

}
