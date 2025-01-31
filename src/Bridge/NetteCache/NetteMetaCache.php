<?php declare(strict_types = 1);

namespace Orisai\ObjectMapper\Bridge\NetteCache;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Orisai\ObjectMapper\MappedObject;
use Orisai\ObjectMapper\Meta\ClassModificationsChecker;
use Orisai\ObjectMapper\Meta\MetaCache;
use Orisai\ObjectMapper\Meta\Runtime\RuntimeMeta;

final class NetteMetaCache implements MetaCache
{

	private const Namespace = 'orisai.objectMapper.meta';

	private Cache $cache;

	private bool $debugMode;

	public function __construct(Storage $storage, bool $debugMode, string $namespace = self::Namespace)
	{
		$this->cache = new Cache($storage, $namespace);
		$this->debugMode = $debugMode;
	}

	public function load(string $class): ?RuntimeMeta
	{
		return $this->cache->load($class);
	}

	public function save(string $class, RuntimeMeta $meta): void
	{
		$this->cache->save($class, $meta, $this->getDependencies($class));
	}

	/**
	 * @param class-string<MappedObject> $class
	 * @return array<mixed>
	 */
	private function getDependencies(string $class): array
	{
		return $this->debugMode
			? [Cache::FILES => ClassModificationsChecker::getSourceFiles($class)]
			: [];
	}

}
