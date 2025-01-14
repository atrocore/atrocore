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

namespace Atro\Core\Utils;

use Espo\Core\Utils\Config;
use Espo\Entities\Preferences;

class ThemeManager
{
    protected Config $config;

    protected ?Preferences $preferences;


    public function __construct(Config $config, ?Preferences $preferences)
    {
        $this->config = $config;
        $this->preferences = $preferences;
    }

    public function getCustomStylesheet(): ?string
    {
        if(!empty($style = $this->getStyle()) && !empty($style['customStylesheetPath'])) {
            return $style['customStylesheetPath'];
        }
        return null;
    }

    public function getCustomHeadCode(): ?string
    {
        if(!empty($style = $this->getStyle()) && !empty($style['customHeadCodePath'])) {
            if (file_exists($path = $style['customHeadCodePath'])) {
                return file_get_contents($path);
            }
        }

        return null;
    }


    public function getStyle(): ?array
    {
        $styleId = $this->preferences?->get('styleId') ?? $this->config->get('defaultStyleId');;
        if(!empty($styleId)) {
            $data = $this->config->get('referenceData.Style');
            if(!empty($data[$styleId])) {
                return $data[$styleId];
            }
        }
        return null;
    }
}