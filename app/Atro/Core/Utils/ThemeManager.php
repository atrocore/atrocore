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
use Espo\Entities\User;

class ThemeManager
{
    protected Config $config;

    protected ?User $user;

    public function __construct(Config $config, ?User $user)
    {
        $this->config = $config;
        $this->user = $user;
    }

    public function getCustomStylesheet(): ?string
    {

        if(!empty($style = $this->getStyle()) && !empty($style['customStylesheetPath']) && file_exists($style['customStylesheetPath'])) {
            return $style['customStylesheetPath'];
        }

        return null;
    }

    public function getCustomHeadCode(): ?string
    {
        $html = '';

        if(!empty($this->config->get('customHeadCodePath')) && file_exists($this->config->get('customHeadCodePath'))) {
            $html = file_get_contents($this->config->get('customHeadCodePath'));
        }

        if(!empty($style = $this->getStyle()) && !empty($style['customHeadCodePath'])) {
            if (file_exists($path = $style['customHeadCodePath'])) {
                $html .= PHP_EOL . file_get_contents($path);
            }
        }

        return $html;
    }

    public function getGlobalCustomStylesheet(): ?string
    {
        if(!empty($this->config->get('customStylesheetPath')) && file_exists($this->config->get('customStylesheetPath'))) {
            return $this->config->get('customStylesheetPath');
        }

        return  null;
    }

    public function getStyle(): ?array
    {
        $styleId = $this->user?->get('styleId') ?? $this->config->get('defaultStyleId');;
        if(!empty($styleId)) {
            $data = $this->config->get('referenceData.Style');
            if(!empty($data[$styleId])) {
                return $data[$styleId];
            }
        }
        return null;
    }
}