/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/plate-item', 'view',
    Dep => Dep.extend({

        template: 'file/record/plate-item',

        setup() {
            Dep.prototype.setup.call(this);

            if (this.options.rowActionsView) {
                this.waitForView('rowActions');
                this.createView('rowActions', this.options.rowActionsView, {
                    el: `${this.options.el} .actions`,
                    model: this.model,
                    acl: this.options.acl
                });
            }
        },

        data() {
            return {
                version: moment(this.model.get('modifiedAt')).format("X"),
                thumbnailPath: this.model.get('mediumThumbnailUrl'),
                hasIcon: !(this.getMetadata().get('app.file.image.hasPreviewExtensions') || []).includes(this.model.get('extension')),
                extension: this.model.get('extension'),
                name: this.model.get('name'),
                type: this.model.get('typeName'),
                scope: this.model.name,
            };
        }

    })
);

