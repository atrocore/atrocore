<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot3Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-02 18:00:00');
    }

    public function up(): void
    {
        $dbal = $this->getDbal();

        $lists = [
            'locationType'       => [
                'entityNames' => ['Location'],
                'fieldName'   => 'type',
                'options'     => [
                    "warehouseID",
                    "areaId",
                    "positionId"
                ]
            ],
            'deliveryStatus'     => [
                'entityNames' => ['Delivery'],
                'fieldName'   => 'status',
                'options'     => [
                    "newDeliveryStatus",
                    "dispatchedDeliveryStatus",
                    "shippedDeliveryStatus"
                ]
            ],
            'fileTags'           => [
                'entityNames' => ['File'],
                'fieldName'   => 'tags',
                'options'     => ['no-such-id']
            ],
            'addressType'        => [
                'entityNames' => ['Address'],
                'fieldName'   => 'type',
                'options'     => [
                    'billing',
                    'delivery'
                ]
            ],
            'role'               => [
                'entityNames' => ['Account', 'Prospect'],
                'fieldName'   => 'role',
                'options'     => [
                    'supplier',
                    'customer'
                ]
            ],
            'quotationStatus'    => [
                'entityNames' => ['Quotation'],
                'fieldName'   => 'status',
                'options'     => [
                    "newQuotationStatus",
                    "approvedQuotationStatus",
                    "negotiatingQS",
                    "acceptedQS",
                    "rejectedQS",
                    "cancelledQuotationStatus"
                ]
            ],
            'recurringPeriod'    => [
                'entityNames' => ['Subscription', 'RecurringPrice'],
                'fieldName'   => 'recurringPeriod',
                'options'     => [
                    "days",
                    "weeks",
                    "months",
                    "years"
                ]
            ],
            'paymentSchedule'    => [
                'entityNames' => ['Subscription'],
                'fieldName'   => 'paymentSchedule',
                'options'     => [
                    "advancedPSchedule",
                    "deferredPSchedule"
                ]
            ],
            'prospectStatus'     => [
                'entityNames' => ['Prospect'],
                'fieldName'   => 'status',
                'options'     => [
                    "newPS",
                    "contactedPS",
                    "engagedPS",
                    "qualifiedPS",
                    "processingPS",
                    "quotedPS",
                    "followUpPS",
                    "wonPS",
                    "lostPS",
                    "disqualifiedPS"
                ]
            ],
            'saleReturnStatus'   => [
                'entityNames' => ['SaleReturn'],
                'fieldName'   => 'status',
                'options'     => [
                    "newSaleReturnStatus",
                    "dispatchedSRS",
                    "returnedSRS"
                ]
            ],
            'saleStatus'         => [
                'entityNames' => ['Sale'],
                'fieldName'   => 'status',
                'options'     => [
                    "new",
                    "approved",
                    "in_progress",
                    "fulfilled",
                    "provided",
                    "partly_invoiced",
                    "invoiced",
                    "cancelled"
                ]
            ],
            'subscriptionStatus' => [
                'entityNames' => ['Subscription'],
                'fieldName'   => 'status',
                'options'     => [
                    "newSubscriptionStatus",
                    "activeSS",
                    "cancelledSS",
                    "pausedSS"
                ]
            ],
            'saleItemStatus'     => [
                'entityNames' => ['SaleItem'],
                'fieldName'   => 'status',
                'options'     => [
                    "newSIS",
                    "approvedSIS",
                    "inProgressSIS",
                    "fulfilledSIS",
                    "invoicedSIS",
                    "cancelledSIS"
                ]
            ],
            'billingStatus'      => [
                'entityNames' => ['Sale'],
                'fieldName'   => 'billingStatus',
                'options'     => [
                    "openBillingStatus",
                    "inProgressBS",
                    "cancelledBS",
                    "partlyBilledBS",
                    "billedBS"
                ]
            ],
            'shippingStatus'     => [
                'entityNames' => ['Sale'],
                'fieldName'   => 'shippingStatus',
                'options'     => [
                    "openShippingStatus",
                    "cancelledShippingStatus",
                    "returnedShippingStatus",
                    "shippedShippingStatus"
                ]
            ],
            'shippingMethod'     => [
                'entityNames' => ['Sale'],
                'fieldName'   => 'shippingMethod',
                'options'     => ['no-such-id']
            ],
            'confirmationStatus' => [
                'entityNames' => ['OrderConfirmation'],
                'fieldName'   => 'status',
                'options'     => [
                    "newCS",
                    "sentCS"
                ]
            ],
            'documentType'       => [
                'entityNames' => ['QuotationItem', 'SaleItem', 'SubscriptionItem'],
                'fieldName'   => 'type',
                'options'     => [
                    "documentTypeSection",
                    "documentTypeGroup",
                    "documentTypeItem",
                    "documentTypeNote",
                    "documentTypeSubtotal"
                ]
            ]
        ];

        $translations = @json_decode(file_get_contents('data/reference-data/Translation.json'), true);

        foreach ($lists as $listId => $row) {
            try {
                $extensibleEnumOptions = $dbal->createQueryBuilder()
                    ->select('o.*')
                    ->from('extensible_enum_option', 'o')
                    ->innerJoin('o', 'extensible_enum_extensible_enum_option', 'lo', 'o.id=lo.extensible_enum_option_id AND lo.deleted=:false')
                    ->innerJoin('lo', 'extensible_enum', 'l', 'l.id=lo.extensible_enum_id AND l.deleted=:false')
                    ->where('o.deleted=:false')
                    ->andWhere('l.id=:listId')
                    ->andWhere('o.id NOT IN (:optionsIds)')
                    ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                    ->setParameter('listId', $listId)
                    ->setParameter('optionsIds', $row['options'], $dbal::PARAM_STR_ARRAY)
                    ->fetchAllAssociative();
            } catch (\Throwable) {
                $extensibleEnumOptions = [];
            }

            if (!empty($extensibleEnumOptions)) {
                $enumOptions = array_column($extensibleEnumOptions, 'id');
                $enumLabels = array_column($extensibleEnumOptions, 'name');

                foreach ($row['entityNames'] as $entityName) {
                    $metadataFileName = "data/metadata/entityDefs/{$entityName}.json";

                    $entityDefs = [];
                    if (file_exists($metadataFileName)) {
                        $tmpData = @json_decode(file_get_contents($metadataFileName), true);
                        if (is_array($tmpData)) {
                            $entityDefs = $tmpData;
                        }
                    }

                    $entityDefs['fields'][$row['fieldName']]['options'] = array_merge($row['options'], $enumOptions);

                    file_put_contents($metadataFileName, json_encode($entityDefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                    foreach ($enumOptions as $k => $optionId) {
                        $key = "{$entityName}.options.{$row['fieldName']}.{$optionId}";

                        $translations[$key]['id'] = md5($key);
                        $translations[$key]['code'] = $key;
                        $translations[$key]['module'] = 'custom';
                        $translations[$key]['isCustomized'] = true;
                        $translations[$key]['createdAt'] = date('Y-m-d H:i:s');
                        $translations[$key]['enUs'] = $enumLabels[$k];
                    }
                }
            }
        }

        file_put_contents('data/reference-data/Translation.json', json_encode($translations));
    }
}
