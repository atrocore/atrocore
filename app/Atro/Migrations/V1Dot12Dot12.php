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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;

class V1Dot12Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-10 10:00:00');
    }

    public function up(): void
    {
        @mkdir(ReferenceData::DIR_PATH);

        $filePath = ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'Style.json';

        $styles = static::getDefaultStyles();

        foreach ($styles as $key => $value) {
            $oldCustomFileName = 'css/treo/treo-' . $key . '-theme-custom.css';
            $newDir = "css/atro/$key";
            if (is_file($oldCustomFileName) || $key === 'light') {
                @mkdir($newDir, 0777, true);
                @rename($oldCustomFileName, $newFilePath = $newDir . DIRECTORY_SEPARATOR . 'custom.css');
                $styles[$key]['customStylesheetPath'] = $newFilePath;
                if ($key === 'light') {
                    $finalContent = $this->getLightStyleCustomContent();
                    if (is_file($newFilePath)) {
                        $finalContent .= PHP_EOL . file_get_contents($newFilePath);
                    }
                    file_put_contents($newFilePath, $finalContent);
                }
            }

            $oldCodeHeadFile = 'code/atro/atro-head-code.html';
            if (is_file($oldCodeHeadFile)) {
                @mkdir("code/atro/$key", 0777, true);
                @copy($oldCodeHeadFile, $finalPath = "code/atro/$key/atro-head-code.html");
                $styles[$key]['customHeadCodePath'] = $finalPath;
            }

            $config = $this->getConfig();
            $theme = $config->get('theme');
            if ($theme === 'Treo' . ucfirst($key) . 'Theme') {
                $oldConfig = $config->get('customStylesheetsList', []);
                if(!empty($oldConfig[$theme])) {
                    foreach ($styles[$key] as $param => $_) {
                        if(in_array($param, ['customStylesheetPath', 'customHeadCodePath'])) {
                            continue;
                        }
                        if(!empty($oldConfig[$theme][$param])) {
                            $styles[$key][$param] = $oldConfig[$theme][$param];
                        }
                    }
                }
                $config->set('defaultStyleId', $value['id']);
                $config->set('defaultStyleName', $value['name']);
                $config->save();
            }
        }

        file_put_contents($filePath, json_encode($styles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function getDefaultStyles(): array
    {
        return [
            "dark" => [
                "id" => "dark",
                "name" => "Dark",
                "code" => "dark",
                "customStylesheet" => null,
                "primaryColor" => "#fff",
                "secondaryColor" => "#85b75f",
                "navigationManuBackgroundColor" => "#424242",
                "navigationMenuFontColor" => "#fff",
                "linkFontColor" => "#06c",
                "primaryFontColor" => "#000000",
                "secondaryFontColor" => "#000000",
                "labelColor" => "#000000",
                "anchorNavigationBackground" => "#fafafa",
                "iconColor" => "#06c",
                "primaryBorderColor" => "#dcdcdc",
                "secondaryBorderColor" => "#f5f5f5",
                "panelTitleColor" => "#000000",
                "headerTitleColor" => "#000000",
                "success" => "#dff0d8",
                "notice" => "#fcf8e3",
                "information" => "#e0efff",
                "error" => "#f2dede",
                "customHeadCode" => null,
                "logo" => "client/modules/treo-core/img/core_logo_white.svg",
                "createdAt" => date('Y-m-d H:i:s'),
                "modifiedAt" => date('Y-m-d H:i:s'),
                "createdById" => "1",
                "modifiedById" => "1"
            ],
            "light" => [
                "id" => "light",
                "name" => "Light",
                "code" => "light",
                "customStylesheet" => null,
                "navigationManuBackgroundColor" => "#fff",
                "navigationMenuFontColor" => "#111111",
                "primaryColor" => "#fff",
                "secondaryColor" => "#85b75f",
                "linkFontColor" => "#0081d1",
                "primaryFontColor" => "#000000",
                "secondaryFontColor" => "#000000",
                "labelColor" => "#000000",
                "anchorNavigationBackground" => "#fafafa",
                "iconColor" => "#777777",
                "primaryBorderColor" => "#e0e0e0",
                "secondaryBorderColor" => "#eeeeee",
                "panelTitleColor" => "#000000",
                "headerTitleColor" => "#000000",
                "success" => "#dff0d8",
                "notice" => "#fcf8e3",
                "information" => "#e0efff",
                "error" => "#f2dede",
                "customHeadCode" => null,
                "navbarStaticItemsHeight" => 83,
                "createdAt" => date('Y-m-d H:i:s'),
                "modifiedAt" => date('Y-m-d H:i:s'),
                "createdById" => "1",
                "modifiedById" => "1"
            ],
            "classic" => [
                "id" => "classic",
                "name" => "Standard",
                "code" => "classic",
                "customStylesheet" => null,
                "navigationManuBackgroundColor" => "#f5f5f5",
                "navigationMenuFontColor" => "#000000",
                "linkFontColor" => "#06c",
                "primaryColor" => "#fff",
                "secondaryColor" => "#85b75f",
                "primaryFontColor" => "#000000",
                "secondaryFontColor" => "#000000",
                "labelColor" => "#000000",
                "anchorNavigationBackground" => "#fafafa",
                "iconColor" => "#06c",
                "primaryBorderColor" => "#dcdcdc",
                "secondaryBorderColor" => "#f5f5f5",
                "panelTitleColor" => "#000000",
                "headerTitleColor" => "#000000",
                "success" => "#dff0d8",
                "notice" => "#fcf8e3",
                "information" => "#e0efff",
                "error" => "#f2dede",
                "customHeadCode" => null,
                "createdAt" => date('Y-m-d H:i:s'),
                "modifiedAt" => date('Y-m-d H:i:s'),
                "createdById" => "1",
                "modifiedById" => "1"
            ]
        ];
    }

    private function getLightStyleCustomContent()
    {
        return ".modal-header { 
  background-color: #ececec;
}

.modal-header a, .modal-title {
    color: #000;
}

.modal-header .close > span {
    color: #000;
}

.panel-title {
    font-size: 14px;
    line-height: 22px;
    font-weight: 700;
    text-transform: uppercase;
}
.panel-default > .panel-heading {
    padding: 13px 10px 13px 14px;
    color: #101010;
    border-top: 5px solid #efefef;
   border-bottom-width: 0;
   background-color: #fff;
   border-color: #dcdcdc;
}
.panel-default > .panel-body {
    margin-bottom: 15px;
}
.middle > .panel-default > .panel-heading, .bottom > .panel-default > .panel-heading, .side > .panel-default > .panel-heading {
    height: auto;
}

.cell label, .filter label {
    color: #777777;
    font-size: 14px;
    line-height: 22px;
}
#content .progress-bar, .modal-content .progress-bar, #content .progress, .modal-content .progress {
    -webkit-border-radius: 3px;
    border-radius: 3px;
}";
    }
}
