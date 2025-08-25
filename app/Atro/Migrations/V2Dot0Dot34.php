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
use Atro\Core\Utils\Util;

class V2Dot0Dot34 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-26 10:00:00');
    }

    public function up(): void
    {
        $stylePath = 'data/reference-data/Style.json';

        $styles = @json_decode(file_get_contents($stylePath), true) ?? [];
        $res = [];
        @mkdir('public/client/custom/css', 0777, true);
        @mkdir('public/client/custom/html', 0777, true);
        $toUpdate = false;
        foreach ($styles as $code => $style) {
            $res[$code] = $style;
            if(!empty($style['customHeadCodePath']) && !str_starts_with($style['customHeadCodePath'], 'public/')&& is_file($style['customHeadCodePath']) ) {
                @rename($style['customHeadCodePath'], 'public/'.$style['customHeadCodePath']);
                $res[$code]['customHeadCodePath'] ='public/'.$style['customHeadCodePath'];
                $toUpdate = true;
            }

            if(!empty($style['customStylesheetPath']) && !str_starts_with($style['customStylesheetPath'], 'public/')&& is_file($style['customStylesheetPath']) ) {
                @rename($style['customStylesheetPath'], 'public/'.$style['customStylesheetPath']);
                $res[$code]['customStylesheetPath'] ='public/'.$style['customStylesheetPath'];
                $toUpdate = true;
            }
        }

        Util::removeDir ('client/custom');

        if($toUpdate) {
            @file_put_contents($stylePath, @json_encode($res));
        }
    }
}
