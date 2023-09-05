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

class_alias("\\Atro\\Composer\\PostUpdate", "\\Treo\\Composer\\PostUpdate");
class_alias("\\Atro\\Core\\Migration\\Base", "\\Treo\\Core\\Migration\\Base");
class_alias("\\Atro\\Core\\FileStorage\\Storages\\Base", "\\Treo\\Core\\FileStorage\\Storages\\Base");
class_alias("\\Atro\\Core\\FileStorage\\Storages\\UploadDir", "\\Treo\\Core\\FileStorage\\Storages\\UploadDir");
class_alias("\\Atro\\Core\\Exceptions\\NotModified", "\\Treo\\Core\\Exceptions\\NotModified");
class_alias("\\Atro\\Core\\ModuleManager\\AbstractModule", "\\Treo\\Core\\ModuleManager\\AbstractModule");
class_alias("\\Atro\\Core\\ModuleManager\\AfterInstallAfterDelete", "\\Treo\\Core\\ModuleManager\\AbstractEvent");
class_alias("\\Atro\\Core\\ModuleManager\\AfterInstallAfterDelete", "\\Treo\\Core\\ModuleManager\\AfterInstallAfterDelete");
class_alias("\\Atro\\Core\\ModuleManager\\Manager", "\\Treo\\Core\\ModuleManager\\Manager");
class_alias("\\Atro\\Core\\Utils\\Condition\\Condition", "\\Treo\\Core\\Utils\\Condition\\Condition");
class_alias("\\Atro\\Core\\Utils\\Condition\\ConditionGroup", "\\Treo\\Core\\Utils\\Condition\\ConditionGroup");
class_alias("\\Atro\\Core\\Utils\\Database\\Schema\\Schema", "\\Treo\\Core\\Utils\\Database\\Schema\\Schema");
class_alias("\\Atro\\Core\\Container", "\\Espo\\Core\\Container");
class_alias("\\Atro\\Core\\Application", "\\Espo\\Core\\Application");
class_alias("\\Atro\\Core\\Twig\\AbstractTwigFilter", "\\Espo\\Core\\Twig\\AbstractTwigFilter");
class_alias("\\Atro\\Core\\Twig\\AbstractTwigFunction", "\\Espo\\Core\\Twig\\AbstractTwigFunction");
class_alias("\\Atro\\Core\\Thumbnail\\Image", "\\Espo\\Core\\Thumbnail\\Image");
class_alias("\\Atro\\Core\\QueueManager", "\\Espo\\Core\\QueueManager");
class_alias("\\Atro\\Core\\PseudoTransactionManager", "\\Espo\\Core\\PseudoTransactionManager");



$migrationsPath = 'vendor/atrocore/core/app/Atro/Migrations';
if (file_exists($migrationsPath)) {
    foreach (scandir($migrationsPath) as $file) {
        $migration = str_replace('.php', '', $file);
        if (class_exists("\\Atro\\Migrations\\$migration")) {
            class_alias("\\Atro\\Migrations\\$migration", "\\Treo\\Migrations\\$migration");
        }
    }
}
