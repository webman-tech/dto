<?php

namespace WebmanTech\DTO\Attributes;

use Attribute;
use WebmanTech\DTO\Enums\RequestPropertyInEnum;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class RequestPropertyInBody extends RequestPropertyIn
{
    public function __construct(
        public ?string $name = null,
    )
    {
        parent::__construct(RequestPropertyInEnum::Body->value, name: $this->name);
    }
}
