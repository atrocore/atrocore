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

namespace Atro\Console;

use Atro\Core\Utils\Util;

class CreateAction extends AbstractConsole
{
    public const DIR = 'data/custom-code/CustomActions';

    public static function getDescription(): string
    {
        return 'The system creates custom Action handler class. You can find the class in ' . self::DIR . '/ folder and modify the code.';
    }

    public function run(array $data): void
    {
        $className = $data['className'] ?? null;

        $fileName = self::DIR . "/{$className}.php";

        if (file_exists($fileName)){
            self::show('Such handler class already exists.', self::ERROR, true);
        }

        if (!preg_match('/^[A-Z][A-Za-z0-9_]*$/', $className)) {
            self::show('Class name must start with an uppercase letter and contain only letters, numbers, and underscores.', self::ERROR, true);
        }

        $content = <<<'EOD'
<?php

namespace CustomActions;

use Atro\ActionTypes\AbstractAction;
use Espo\ORM\Entity;

class {{name}} extends AbstractAction
{
    public static function getTypeLabel(): ?string
    {
        return '{{name}}';
    }

    public static function getName(): ?string
    {
        return 'Do {{name}}';
    }

    public static function getDescription(): ?string
    {
        return 'Describe {{name}}';
    }

    public function executeNow(Entity $action, \stdClass $input): bool
    {
        return true;
    }
}

EOD;

        Util::createDir(self::DIR);
        file_put_contents($fileName, str_replace('{{name}}', $className, $content));

        self::show("Action handler class '" . self::DIR . "/{$className}.php' has been created successfully.", self::SUCCESS);
    }
}
