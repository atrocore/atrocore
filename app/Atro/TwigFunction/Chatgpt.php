<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\TwigFunction;

use Atro\Core\ChatGpt\ChatGptClient;
use Atro\Core\Twig\AbstractTwigFunction;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;

class Chatgpt extends AbstractTwigFunction
{
    protected ChatGptClient $chatGptClient;

    public function __construct(ChatGptClient $chatGptClient)
    {
        $this->chatGptClient = $chatGptClient;
    }

    public function run(string $prompt): string
    {
        return $this->chatGptClient->createCompletion($prompt);
    }
}
