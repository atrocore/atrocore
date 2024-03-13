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

namespace Atro\Core\AssetValidation\Items;

use Atro\Core\AssetValidation\Base;
use Espo\Core\Exceptions\BadRequest;
use ReflectionClass;
use Espo\Core\Container;

class ColorSpace extends Base
{
    /**
     * @var array|bool
     */
    private $map = [];

    /**
     * ColorSpace constructor.
     * @param Container $container
     * @throws \ReflectionException
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->map = $this->createMap();
    }

    /**
     * @return bool
     * @throws \ImagickException
     */
    public function validate(): bool
    {
        $img = new \Imagick($this->getFilePath());

        $colorSpace = $img->getImageColorspace();

        return in_array($this->map[$colorSpace], $this->params);
    }

    /**
     * @throws BadRequest
     */
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
        $res     = [];
        foreach ($imagick->getConstants() as $constantName => $constantValue) {
            if (stripos($constantName, "COLORSPACE_") === false) {
                continue;
            }

            $res[$constantValue] = str_replace("COLORSPACE_", "", $constantName);
        }

        return $res;
    }
}