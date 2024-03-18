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

namespace Atro\Core\FileValidation\Items;

use Atro\Core\FileValidation\Base;
use Atro\Core\Exceptions\BadRequest;
use Atro\Entities\File;
use Espo\ORM\Entity;
use ReflectionClass;
use Atro\Core\Container;

class ColorSpace extends Base
{
    /**
     * @var array|bool
     */
    private $map = [];

    public function __construct(Container $container, Entity $rule)
    {
        parent::__construct($container, $rule);

        $this->map = $this->createMap();
    }

    public function validate(File $file): bool
    {
        $img = new \Imagick($file->getFilePath());

        $colorSpace = $img->getImageColorspace();

        return in_array($this->map[$colorSpace], $this->rule->get('colorSpace'));
    }

    public function onValidateFail()
    {
        throw new BadRequest(sprintf($this->exception('colorSpaceValidationFailed'), implode(", ", $this->params)));
    }

    /**
     * @return array|bool
     * @throws \ReflectionException
     */
    private function createMap()
    {
        $imagick = new ReflectionClass(\Imagick::class);
        $res = [];
        foreach ($imagick->getConstants() as $constantName => $constantValue) {
            if (stripos($constantName, "COLORSPACE_") === false) {
                continue;
            }

            $res[$constantValue] = str_replace("COLORSPACE_", "", $constantName);
        }

        return $res;
    }
}