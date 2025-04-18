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

        galleryComponent: null,

        events: {
            'click [data-action="close-modal"]': function () {
                this.actionClose();
            },
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.options.canLoadMore) {
                this.listenTo(this, 'gallery:load-more:success', (data) => {
                    this.options.mediaList = data.mediaList;
                    this.options.canLoadMore = data.canLoadMore;

                    window.dispatchEvent(new CustomEvent('gallery:load-more:success', {
                        detail: {
                            mediaList: data.mediaList,
                            canLoadMore: data.canLoadMore
                        }
                    }));
                });
            }
        },

        afterRender: function () {
            if (this.galleryComponent) return;
            this.galleryComponent = new Svelte.Gallery({
                target: document.querySelector('.modal .gallery-container'),
                props: {
                    mediaList: this.options.mediaList ?? [],
                    currentMediaId: this.options.id ?? null,
                    canLoadMore: this.options.canLoadMore ?? false,
                    onLoadMore: () => {
                        this.trigger('gallery:load-more');
                    }
                }
            });
        },
    });
});
