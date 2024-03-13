/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/stream/record/edit', 'class-replace!treo-core:views/stream/record/edit', function (Dep) {

    return Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            delete this.events['focus textarea[name="post"]'];

            this.events['click textarea[name="post"]'] = e => {
                this.enablePostingMode();
            }
        },
    })
});