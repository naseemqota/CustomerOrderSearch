<?php

declare(strict_types=1);

namespace Qota\CustomerOrderSearch\Api;

interface FiltersInterface
{
    public function isFilterable($post);
    public function filter($order, $post);
}
