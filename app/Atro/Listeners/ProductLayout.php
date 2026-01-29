<?php
/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore GmbH.
 *
 * This Software is the property of AtroCore GmbH and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;

class ProductLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        if ($this->isCustomLayout($event)) {
            return;
        }

        $result = $event->getArgument('result');

        $result[] = [
            "label" => "BMECat",
            "rows"  => [
                [
                    [
                        "name" => "manufacturerName"
                    ],
                    [
                        "name" => "manufacturersItemNumber"
                    ]
                ],
                [
                    [
                        "name" => "manufacturerTypeDesignation"
                    ],
                    [
                        "name" => "keywords"
                    ]
                ],
                [
                    [
                        "name" => "remarks"
                    ],
                    [
                        "name" => "bmecatProductStatus"
                    ]

                ]
            ]
        ];

        $event->setArgument('result', $result);
    }
}