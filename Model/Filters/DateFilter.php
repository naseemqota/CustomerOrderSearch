<?php

declare(strict_types=1);

namespace Qota\CustomerOrderSearch\Model\Filters;

use Qota\CustomerOrderSearch\Api\FiltersInterface;

class DateFilter implements FiltersInterface
{
    public function isFilterable($post): bool
    {
        return  (!empty($post['from_date']) || !empty($post['to_date']));
    }

    public function filter($order, $post)
    {
        if (!empty($post['from_date']) && !empty($post['to_date'])) {
            $date = ['from' => date("Y-m-d H:i:s", strtotime($post['from_date'] . ' 00:00:00')),
                'to' => date("Y-m-d H:i:s", strtotime($post['to_date'] . ' 24:00:00')) ];
            $order->addFieldToFilter(
                'main_table.created_at',
                $date
            );
        } elseif (!empty($post['from_date'])) {
            $order->addFieldToFilter(
                'main_table.created_at',
                ['like' => date("Y-m-d ", strtotime($post['from_date'])) . '%']
            );
        } elseif (!empty($post['to_date'])) {
            $order->addFieldToFilter(
                'main_table.created_at',
                ['like' => date("Y-m-d", strtotime($post['to_date'])) . '%']
            );
        }

        return $order;
    }
}
