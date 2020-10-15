

/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://treolabs.com
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

            model.save(attrs, {
                success: function () {
                    this.afterSave();
                    var isNew = self.isNew;
                    if (self.isNew) {
                        self.isNew = false;
                    }
                    this.trigger('after:save');
                    model.trigger('after:save');

                    if (!callback) {
                        if (!skipExit) {
                            if (isNew) {
                                this.exit('create');
                            } else {
                                this.exit('save');
                            }
                        }
                    } else {
                        callback(this);
                    }
                }.bind(this),
                error: function (e, xhr) {
                    var r = xhr.getAllResponseHeaders();
                    var response = null;

                    if (~[409, 500].indexOf(xhr.status)) {
                        var statusReasonHeader = xhr.getResponseHeader('X-Status-Reason');
                        if (statusReasonHeader) {
                            try {
                                var response = JSON.parse(statusReasonHeader);
                            } catch (e) {
                                console.error('Could not parse X-Status-Reason header');
                            }
                        }
                    }

                    if (response && response.reason) {
                        var methodName = 'errorHandler' + Espo.Utils.upperCaseFirst(response.reason.toString());
                        if (methodName in this) {
                            xhr.errorIsHandled = true;
                            this[methodName](response.data);
                        }
                    }

                    this.afterSaveError();

                    model.attributes = beforeSaveAttributes;
                    self.trigger('cancel:save');

                }.bind(this),
                patch: !model.isNew()
            });
            return true;
        }

    });
});