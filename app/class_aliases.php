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
class_alias("\\Atro\\Core\\EventManager\\Event", "\\Espo\\Core\\EventManager\\Event");
class_alias("\\Atro\\Core\\EventManager\\Manager", "\\Espo\\Core\\EventManager\\Manager");
class_alias("\\Atro\\Listeners\\AbstractListener", "\\Espo\\Listeners\\AbstractListener");
class_alias("\\Atro\\Core\\OpenApiGenerator", "\\Espo\\Core\\OpenApiGenerator");
class_alias("\\Atro\\Core\\Templates\\Services\\HasContainer", "\\Espo\\Core\\Templates\\Services\\HasContainer");
class_alias("\\Atro\\Core\\Templates\\Controllers\\Base", "\\Espo\\Core\\Templates\\Controllers\\Base");
class_alias("\\Atro\\Core\\Templates\\Entities\\Base", "\\Espo\\Core\\Templates\\Entities\\Base");
class_alias("\\Atro\\Core\\Templates\\Repositories\\Base", "\\Espo\\Core\\Templates\\Repositories\\Base");
class_alias("\\Atro\\Core\\Templates\\Services\\Base", "\\Espo\\Core\\Templates\\Services\\Base");
class_alias("\\Atro\\Core\\Templates\\Controllers\\Hierarchy", "\\Espo\\Core\\Templates\\Controllers\\Hierarchy");
class_alias("\\Atro\\Core\\Templates\\Entities\\Hierarchy", "\\Espo\\Core\\Templates\\Entities\\Hierarchy");
class_alias("\\Atro\\Core\\Templates\\Repositories\\Hierarchy", "\\Espo\\Core\\Templates\\Repositories\\Hierarchy");
class_alias("\\Atro\\Core\\Templates\\Services\\Hierarchy", "\\Espo\\Core\\Templates\\Services\\Hierarchy");
class_alias("\\Atro\\Core\\Templates\\Controllers\\Relationship", "\\Espo\\Core\\Templates\\Controllers\\Relationship");
class_alias("\\Atro\\Core\\Templates\\Entities\\Relationship", "\\Espo\\Core\\Templates\\Entities\\Relationship");
class_alias("\\Atro\\Core\\Templates\\Repositories\\Relationship", "\\Espo\\Core\\Templates\\Repositories\\Relationship");
class_alias("\\Atro\\Core\\Templates\\Services\\Relationship", "\\Espo\\Core\\Templates\\Services\\Relationship");

// to remove after 01.09.2024
$migrationsPath = 'vendor/atrocore/core/app/Atro/Migrations';
if (file_exists($migrationsPath)) {
    foreach (scandir($migrationsPath) as $file) {
        $migration = str_replace('.php', '', $file);
        if (class_exists("\\Atro\\Migrations\\$migration")) {
            class_alias("\\Atro\\Migrations\\$migration", "\\Treo\\Migrations\\$migration");
        }
    }
}
