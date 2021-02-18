/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('treo-core:views/record/base', 'class-replace!treo-core:views/record/base', function (Dep) {

    return Dep.extend({

        populateDefaults: function () {
            Dep.prototype.populateDefaults.call(this);

            let defaultHash = {};

            if (!this.getUser().get('portalId')) {
                if (this.model.hasField('ownerUser')) {
                    let fillOwnerUser = true;
                    if (this.getPreferences().get('doNotFillOwnerUserIfNotRequired')) {
                        fillOwnerUser = false;
                        if (this.model.getFieldParam('ownerUser', 'required')) {
                            fillOwnerUser = true;
                        }
                    }
                    if (fillOwnerUser) {
                        defaultHash['ownerUserId'] = this.getUser().id;
                        defaultHash['ownerUserName'] = this.getUser().get('name');
                    }
                }
            }

            for (let attr in defaultHash) {
                if (this.model.has(attr)) {
                    delete defaultHash[attr];
                }
            }
            this.model.set(defaultHash, {silent: true});
        },

        save: function (callback, skipExit) {
            this.beforeBeforeSave();

            var data = this.fetch();

            var self = this;
            var model = this.model;

            var initialAttributes = this.attributes;

            var beforeSaveAttributes = this.model.getClonedAttributes();

            data = _.extend(Espo.Utils.cloneDeep(beforeSaveAttributes), data);

            var attrs = false;
            if (model.isNew()) {
                attrs = data;
            } else {
                for (var name in data) {
                    if (_.isEqual(initialAttributes[name], data[name])) {
                        continue;
                    }
                    (attrs || (attrs = {}))[name] = data[name];
                }
            }

            if (!attrs) {
                this.trigger('cancel:save');
                this.afterNotModified();
                return true;
            }

            model.set(attrs, {silent: true});

            if (this.validate()) {
                model.attributes = beforeSaveAttributes;
                this.trigger('cancel:save');
                this.afterNotValid();
                return;
            }

            this.beforeSave();

            this.trigger('before:save');
            model.trigger('before:save');

            // get current page url
            let url = window.location.href;

            // parse
            let urlParts = url.split('/view/');

            if (typeof urlParts[1] !== 'undefined') {
                attrs._mainEntityId = urlParts[1];
            }

            let _prev = {};
            $.each(attrs, function (field, value) {
                _prev[field] = initialAttributes[field];
            });

            attrs['_prev'] = _prev;
            attrs['_silentMode'] = true;

            model.save(attrs, {
                success: function () {
                    self.afterSave();
                    let isNew = self.isNew;
                    if (self.isNew) {
                        self.isNew = false;
                    }
                    self.trigger('after:save');
                    model.trigger('after:save');

                    if (!callback) {
                        if (!skipExit) {
                            if (isNew) {
                                self.exit('create');
                            } else {
                                self.exit('save');
                            }
                        }
                    } else {
                        callback(self);
                    }
                },
                error: function (e, xhr) {
                    if (xhr.status === 409) {
                        self.notify(false);
                        self.enableButtons();
                        self.trigger('cancel:save');
                        Espo.Ui.confirm(self.translate('editedByAnotherUser', 'exceptions', 'Global'), {
                            confirmText: self.translate('Apply'),
                            cancelText: self.translate('Cancel')
                        }, function () {
                            attrs['_prev'] = null;
                            attrs['_silentMode'] = false;
                            model.save(attrs, {
                                success: function () {
                                    self.afterSave();
                                    self.isNew = false;
                                    self.trigger('after:save');
                                    model.trigger('after:save');
                                    if (!callback) {
                                        if (!skipExit) {
                                            self.exit('save');
                                        }
                                    } else {
                                        callback(self);
                                    }
                                },
                                patch: true
                            });
                        })
                    } else {
                        self.enableButtons();
                        self.trigger('cancel:save');

                        let statusReason = xhr.getResponseHeader('X-Status-Reason') || '';
                        Espo.Ui.notify(`${self.translate("Error")} ${xhr.status}: ${statusReason}`, "error", 1000 * 60 * 60 * 2, true);
                    }
                },
                patch: !model.isNew()
            });

            return true;
        }

    });
});