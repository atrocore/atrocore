
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({
        setup: function() {
            Dep.prototype.setup.call(this);

            this.additionalButtons.push( {
                action: 'addItem',
                name: 'addItem',
                label: this.translate('addItem')
            });
        }
    });
});