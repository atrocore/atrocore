<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

function updateComposer(string $package, string $version): void
{
    foreach (['composer.json', 'data/stable-composer.json'] as $filename) {
        if (!file_exists($filename)) {
            continue;
        }
        $data = json_decode(file_get_contents($filename), true);
        $data['require'] = array_merge($data['require'], [$package => $version]);
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

/**
 * Migrate Core to 1.6.36
 */
if (class_exists("\\Atro\\Core\\Application") && !\Atro\Core\Application::isSystemUpdating()) {
    try {
        /** @var \Espo\Core\Utils\Config $config */
        $config = (new \Atro\Core\Application())->getContainer()->get('config');

        $key = 'oneTimeExecutionScriptForCore1Dot6Dot36';
        if (empty($config->get($key))) {
            // reload daemons
            file_put_contents('data/process-kill.txt', '1');

            // copy to root
            copy('vendor/atrocore/core/copy/.htaccess', '.htaccess');
            copy('vendor/atrocore/core/copy/index.php', 'index.php');

            // prepare composer.json
            updateComposer('atrocore/core', '^1.6.36');

            $config->set($key, true);
            $config->save();
        }
    } catch (\Throwable $e) {
        // ignore all
    }
}
