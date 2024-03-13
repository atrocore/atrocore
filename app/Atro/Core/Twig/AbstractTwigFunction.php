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

namespace Atro\Core\Twig;

use Espo\Core\Injectable;

abstract class AbstractTwigFunction extends Injectable
{
    protected array $templateData = [];

    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }

    public function getTemplateData(string $name)
    {
        return $this->templateData[$name] ?? null;
    }
}
