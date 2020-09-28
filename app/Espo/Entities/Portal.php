<?php

namespace Espo\Entities;

class Portal extends \Espo\Core\ORM\Entity
{
    protected $settingsAttributeList = [
        'companyLogoId',
        'tabList',
        'quickCreateList',
        'dashboardLayout',
        'dashletsOptions',
        'theme',
        'language',
        'timeZone',
        'dateFormat',
        'timeFormat',
        'weekStart',
        'defaultCurrency'
    ];

    public function getSettingsAttributeList()
    {
        return $this->settingsAttributeList;
    }

}
