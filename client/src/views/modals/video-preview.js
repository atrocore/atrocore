/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/modals/video-preview', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'video-preview',

        template: 'modals/video-preview',

        name: null,

        header: true,

        backdrop: true,

        data: function () {
            return {
                name: this.name ?? this.model.get('name'),
                url: this.options.url,
                fileId: this.options.fileId ?? null
            };
        },

        setup: function () {
            this.header = '&nbsp;';
        },
    });
});

