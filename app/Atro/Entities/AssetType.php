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

namespace Atro\Entities;

use Espo\Core\Templates\Entities\Base;
use Espo\Core\Utils\Util;

/**
 * Class AssetType
 */
class AssetType extends Base
{
    /**
     * @var string
     */
    protected $entityType = "AssetType";

    /**
     * @return array
     */
    public function getValidations(): array
    {
        $result = [];

        $validations = $this->get('validationRules');
        if ($validations->count() > 0) {
            foreach ($validations as $validation) {
                if (empty($validation->get('isActive'))) {
                    continue 1;
                }

                $type = self::prepareType($validation->get('type'));

                $data = [];
                switch ($type) {
                    case 'mime':
                        if ($validation->get('validateBy') == 'List') {
                            $data['list'] = $validation->get('mimeList');
                        } elseif ($validation->get('validateBy') == 'Pattern') {
                            $data['pattern'] = $validation->get('pattern');
                        }
                        break;
                    case 'size':
                        $data['private'] = [
                            'min' => $validation->get('min'),
                            'max' => $validation->get('max'),
                        ];
                        $data['public'] = [
                            'min' => $validation->get('min'),
                            'max' => $validation->get('max'),
                        ];
                        break;
                    case 'quality':
                        $data['min'] = $validation->get('min');
                        $data['max'] = $validation->get('max');
                        break;
                    case 'colorDepth':
                        $data = $validation->get('colorDepth');
                        break;
                    case 'colorSpace':
                        $data = $validation->get('colorSpace');
                        break;
                    case 'extension':
                        $data = $validation->get('extension');
                        break;
                    case 'ratio':
                        $data = $validation->get('ratio');
                        break;
                    case 'scale':
                        $data['min'] = [
                            'width'  => $validation->get('minWidth'),
                            'height' => $validation->get('minHeight'),
                        ];
                        break;
                }

                $result[$type] = $data;
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getRenditions(): array
    {
        return [];
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected static function prepareType(string $type): string
    {
        return Util::toCamelCase(strtolower(str_replace(' ', '_', $type)));
    }
}
