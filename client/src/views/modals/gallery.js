/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/modals/gallery', 'views/modal', function (Dep) {

    return Dep.extend({

        className: 'full-page-modal',

        header: null,

        template: 'modals/gallery',

        size: '',

        backdrop: true,

        name: null,

        fullHeight: true,

        events: {
            'click [data-action="close-modal"]': function () {
                this.actionClose();
            },
        },

        getImageUrl: function () {
            return this.options.thumbnailUrl ?? this.model.get('largeThumbnailUrl');
        },

        getOriginalImageUrl: function () {
            return this.options.downloadUrl ?? this.model.get('downloadUrl');
        },

        afterRender: function () {
            // TODO: render svelte component
        },
    });
});
