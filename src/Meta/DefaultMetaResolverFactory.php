<?php declare(strict_types = 1);

namespace Orisai\ObjectMapper\Meta;

use Orisai\ObjectMapper\Rules\RuleManager;

final class DefaultMetaResolverFactory implements MetaResolverFactory
{

	private RuleManager $ruleManager;

	public function __construct(RuleManager $ruleManager)
	{
		$this->ruleManager = $ruleManager;
	}

	public function create(MetaLoader $loader): MetaResolver
	{
		return new MetaResolver($loader, $this->ruleManager);
	}

}
