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

use Atro\Core\Utils\Language;

class RefreshTranslations extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Refresh translations.';
    }

    public function run(array $data): void
    {
        $this->getLanguage()->refreshTranslations();

        self::show('Translations refreshed successfully.', self::SUCCESS);
    }

    protected function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }
}
