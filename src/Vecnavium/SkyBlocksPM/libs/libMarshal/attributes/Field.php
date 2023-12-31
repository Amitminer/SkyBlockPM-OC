<?php

declare(strict_types=1);

namespace Vecnavium\SkyBlocksPM\libs\libMarshal\attributes;

use Attribute;
use Vecnavium\SkyBlocksPM\libs\libMarshal\parser\Parseable;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Field {

	/** @var Parseable<mixed, mixed>|null */
	protected ?Parseable $parser = null;

	/**
	 * @param class-string<Parseable<mixed, mixed>>|null $parser - This is the class that will be used to parse & serialize the value.
	 */
	public function __construct(
		public string $name = "",
		?string $parser = null
	)
	{
		$this->parser = $parser ? new $parser : null;
	}

	/**
	 * @return Parseable<mixed, mixed>|null
	 */
	public function getParser(): ?Parseable
	{
		return $this->parser;
	}
}