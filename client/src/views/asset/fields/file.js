/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/fields/file', 'views/fields/file',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, "change:fileId", () => {
                if (this.mode === 'edit') {
                    this.model.set("name", this.model.get("fileName"));
                }
            });

            this.listenTo(this.model, "change:fileName", () => {
                this.reRender();
            });

            this.listenTo(this.model, "after:save", () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            if (this.model.get('massCreate')) this.hide()
        },

        initDownloadIcon: function () {
            if (this.model.get('hasOpen')) {
                this.initOpenIcon();
            }

            Dep.prototype.initDownloadIcon.call(this);
        },

        initOpenIcon: function () {
            const $cell = this.getCellElement();

            $cell.find('.fa-external-link-alt').parent().remove();

            const id = this.model.get(this.idName);
            if (!id) {
                return;
            }

            const $editLink = $('<a href="' + this.getDownloadUrl(id) + '" target="_blank" class="pull-right hidden" style="margin-right: 5px"><span class="fas fa-external-link-alt fa-sm"></span></a>');

            $cell.prepend($editLink);

            $cell.on('mouseenter', function (e) {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }
                if (this.mode === 'detail') {
                    $editLink.removeClass('hidden');
                }
            }.bind(this)).on('mouseleave', function (e) {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $editLink.addClass('hidden');
                }
            }.bind(this));
        },

    })
);