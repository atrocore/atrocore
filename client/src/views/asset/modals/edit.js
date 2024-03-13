/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/modals/edit', 'views/modals/edit',
    Dep => Dep.extend({

        fullFormDisabled: true,

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:save', model => {
                if (this.getParentModel()) {
                    this.getParentModel().trigger('asset:saved');
                }
            });
        },

        getParentModel() {
            if (this.getParentView() && this.getParentView().model) {
                return this.getParentView().model;
            }

            return this.getParentView().getParentView().model;
        },

        actionSave() {
            this.notify('Saving...');

            if (this.getView('edit').validate()) {
                this.notify('Not valid', 'error');
                this.trigger('cancel:save');
                return;
            }

            let filesIds = [];
            if (this.model.get('fileId')) {
                filesIds.push(this.model.get('fileId'));
            } else if (this.model.get('filesIds') && this.model.get('filesIds').length > 0) {
                filesIds = this.model.get('filesIds');
            }

            let count = filesIds.length;

            let editView = this.getView('edit');
            let formData = editView.fetch();

            let attrs = formData;
            if (!this.model.isNew()) {
                let initialAttributes = editView.attributes;
                for (let name in formData) {
                    if (_.isEqual(initialAttributes[name], formData[name])) {
                        continue;
                    }
                    (attrs || (attrs = {}))[name] = formData[name];
                }
            }
            attrs['name'] = this.model.get('name');
            attrs['_silentMode'] = true;

            if (this.options.relate && this.options.relate.model) {
                this.model.defs['_relationName'] = this.options.relate.model.defs['_relationName'];
            }

            let hashParts = window.location.hash.split('/view/');
            if (typeof hashParts[1] !== 'undefined' && this.model.defs._relationName) {
                attrs._relationName = this.model.defs._relationName;
                attrs._relationEntity = hashParts[0].replace('#', '');
                attrs._relationEntityId = hashParts[1];
            }

            let self = this;
            if (count > 0) {
                this.trigger('before:save', attrs);
                this.model.save(attrs, {
                    patch: !this.model.isNew(),
                    success(response) {
                        new Promise(resolve => self.relateExistedAssets(resolve)).then(() => {
                            self.trigger('after:save', self.model);
                            self.dialog.close();
                            if (count > 20) {
                                Espo.Ui.notify(self.translate('assetsAdded', 'messages', 'Asset'), 'success', 1000 * 60, true);
                            } else {
                                self.notify('Saved', 'success');
                            }
                        });
                    },
                    error(e, xhr) {
                        if (xhr.status === 304) {
                            Espo.Ui.notify(self.translate('notModified', 'messages'), 'warning', 1000 * 60 * 60 * 2, true);
                        } else {
                            let statusReason = xhr.responseText || '';
                            Espo.Ui.notify(`${self.translate("Error")} ${xhr.status}: ${statusReason}`, "error", 1000 * 60 * 60 * 2, true);
                        }
                    }
                });
            } else if (this.options.relate) {
                new Promise(resolve => {
                    this.relateExistedAssets(resolve);
                }).then(() => {
                    this.trigger('after:save', this.model);
                    this.dialog.close();
                    this.notify('Linked', 'success');
                });
            } else {
                this.dialog.close();
                this.notify(false);
            }
        },

        relateExistedAssets(resolve) {
            if (this.options.relate && this.model.get('assetsForRelate')) {
                let ids = [];
                $.each(this.model.get('assetsForRelate'), (hash, id) => {
                    ids.push(id);
                });

                if (ids.length > 0) {
                    this.ajaxPostRequest(`${this.options.relate.model.urlRoot}/${this.options.relate.model.get('id')}/assets`, {"ids": ids}).then(success => {
                        resolve();
                    });
                } else {
                    resolve();
                }
            } else {
                resolve();
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let buttonLink = $('.main-btn-group > .btn-link');

            if (buttonLink) {
                let prev = buttonLink.prev('.btn');

                if (prev && !prev.hasClass('last')) {
                    prev.addClass('last');
                }
            }
        }
    })
);