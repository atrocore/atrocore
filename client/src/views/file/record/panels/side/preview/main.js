/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/panels/side/preview/main', 'views/fields/file', Dep => {
        return Dep.extend({

            setup() {
                Dep.prototype.setup.call(this);

                this.idName = 'id';
                this.nameName = 'name';

                this.mode = 'list';
                this.previewSize = 'large';

                this.listenTo(this.model, 'reuploaded', () => {
                    this.model.fetch().then(() => this.reRender());
                });
            },

            getFilePathsData: function () {
                return {
                    download: this.model.get('downloadUrl'),
                    thumbnails: {
                        small: this.model.get('smallThumbnailUrl'),
                        medium: this.model.get('mediumThumbnailUrl'),
                        large: this.model.get('largeThumbnailUrl'),
                    }
                };
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if (this.mode === 'detail') {
                    this.$el.find('.attachment-preview').css({'display': 'block'});
                    this.$el.find('img').css({'display': 'block', 'margin': '0 auto'});
                }
            },

        });
    }
);