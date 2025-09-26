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

namespace Atro\Seeders;

use Atro\Core\Templates\Repositories\ReferenceData;

class StyleSeeder extends AbstractSeeder
{
    public function run(): void
    {
        if (file_exists(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'Style.json')) {
            return;
        }

        @mkdir(ReferenceData::DIR_PATH);
        $styles = $this->getDefaultStyles();
        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'Style.json', json_encode($styles));
    }

    private function getDefaultStyles(): array
    {
        return [
            "dark"    => [
                "id"                            => "dark",
                "name"                          => "Dark",
                "code"                          => "dark",
                "customStylesheet"              => null,
                "primaryColor"                  => "#fff",
                "secondaryColor"                => "#85b75f",
                "navigationManuBackgroundColor" => "#424242",
                "toolbarBackgroundColor"        => "#424242",
                "navigationMenuFontColor"       => "#fff",
                "linkFontColor"                 => "#06c",
                "primaryFontColor"              => "#000000",
                "secondaryFontColor"            => "#000000",
                "labelColor"                    => "#7C848B",
                "anchorNavigationBackground"    => "#fafafa",
                "iconColor"                     => "#06c",
                "primaryBorderColor"            => "#dcdcdc",
                "secondaryBorderColor"          => "#f5f5f5",
                "panelTitleColor"               => "#000000",
                "headerTitleColor"              => "#000000",
                "actionIconColor"               => "#333",
                "success"                       => "#dff0d8",
                "notice"                        => "#fcf8e3",
                "information"                   => "#e0efff",
                "error"                         => "#f2dede",
                "customHeadCode"                => null,
                "logo"                          => "client/modules/treo-core/img/core_logo_white.svg",
                "createdAt"                     => date('Y-m-d H:i:s'),
                "modifiedAt"                    => date('Y-m-d H:i:s'),
                "createdById"                   => "1",
                "createdByName"                 => "System",
            ],
            "light"   => [
                "id"                            => "light",
                "name"                          => "Light",
                "code"                          => "light",
                "customStylesheet"              => null,
                "navigationManuBackgroundColor" => "#fff",
                "toolbarBackgroundColor"        => "#fff",
                "navigationMenuFontColor"       => "#111111",
                "primaryColor"                  => "#fff",
                "secondaryColor"                => "#85b75f",
                "linkFontColor"                 => "#0081d1",
                "primaryFontColor"              => "#000000",
                "secondaryFontColor"            => "#000000",
                "labelColor"                    => "#7C848B",
                "anchorNavigationBackground"    => "#fafafa",
                "iconColor"                     => "#777777",
                "primaryBorderColor"            => "#e0e0e0",
                "secondaryBorderColor"          => "#eeeeee",
                "panelTitleColor"               => "#000000",
                "headerTitleColor"              => "#000000",
                "actionIconColor"               => "#333",
                "success"                       => "#dff0d8",
                "notice"                        => "#fcf8e3",
                "information"                   => "#e0efff",
                "error"                         => "#f2dede",
                "customHeadCode"                => null,
                "navbarStaticItemsHeight"       => 83,
                "createdAt"                     => date('Y-m-d H:i:s'),
                "modifiedAt"                    => date('Y-m-d H:i:s'),
                "createdById"                   => "1",
                "createdByName"                 => "System",
            ],
            "classic" => [
                "id"                            => "classic",
                "name"                          => "Standard",
                "code"                          => "classic",
                "customStylesheet"              => null,
                "navigationManuBackgroundColor" => "#f5f5f5",
                "toolbarBackgroundColor"        => "#f5f5f5",
                "navigationMenuFontColor"       => "#000000",
                "linkFontColor"                 => "#06c",
                "primaryColor"                  => "#fff",
                "secondaryColor"                => "#85b75f",
                "primaryFontColor"              => "#000000",
                "secondaryFontColor"            => "#000000",
                "labelColor"                    => "#7C848B",
                "anchorNavigationBackground"    => "#fafafa",
                "iconColor"                     => "#06c",
                "primaryBorderColor"            => "#dcdcdc",
                "secondaryBorderColor"          => "#f5f5f5",
                "panelTitleColor"               => "#000000",
                "headerTitleColor"              => "#000000",
                "actionIconColor"               => "#333",
                "success"                       => "#dff0d8",
                "notice"                        => "#fcf8e3",
                "information"                   => "#e0efff",
                "error"                         => "#f2dede",
                "customHeadCode"                => null,
                "createdAt"                     => date('Y-m-d H:i:s'),
                "modifiedAt"                    => date('Y-m-d H:i:s'),
                "createdById"                   => "1",
                "createdByName"                 => "System",
            ]
        ];
    }
}