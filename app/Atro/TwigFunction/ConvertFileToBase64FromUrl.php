<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;

class ConvertFileToBase64FromUrl extends AbstractTwigFunction
{
    public function run(string $url, ?string $type = null)
    {
        $content = file_get_contents($url);

        if(empty($content)){
            return false;
        }

        $data = base64_encode($content);

        if($type){
            $data = "data:". $type . ';base64,' . $data;
        }

        return $data;
    }

}