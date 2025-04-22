<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot13Dot54 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-04-23 17:00:00');
    }
    public function up(): void
    {
        rename('client', 'public/client');
        rename('apidocs', 'public/apidocs');

        copy('vendor/atrocore/core/copy/.htaccess', 'public/.htaccess');
        copy('vendor/atrocore/core/copy/index.php', 'public/index.php');
        copy('vendor/atrocore/core/copy/robots.txt', 'public/robots.txt');

        copy('vendor/atrocore/core/copy/console.php', 'console.php');

        file_put_contents('index.php', "<?php echo 'Webserver configuration has been deprecated. Please, reconfigure your webserver to use public/index.php as document root. How to configure virtual host you can find <a href=\"https://help.atrocore.com/installation-and-maintenance/installation/apache-web-server#5-creating-a-virtual-host-for-your-application\">here</a>.';exit;");

        @unlink('.htaccess');
    }
}
