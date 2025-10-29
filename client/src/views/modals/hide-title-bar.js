/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/modals/hide-title-bar', 'views/modal',
    Dep => Dep.extend({

        className: 'hide-title-bar',

        template: 'modals/hide-title-bar',

        header: "Tip",

        fullHeight: false,

        setup() {
            this.buttonList = [];
        },
    })
);
