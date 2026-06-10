/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/master-data-entity-source/fields/master-data-entity', 'views/fields/link',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail' || this.mode === 'list') {
                const $a = this.$el.find('a');
                if ($a.length) {
                    $a.replaceWith($('<span>').text($a.text()));
                }
            }
        },

    })
);
