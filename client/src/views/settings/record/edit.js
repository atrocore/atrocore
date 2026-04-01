/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

Espo.define('views/settings/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        sideView: null,

        layoutName: 'settings',

        buttons: [
            {
                name: 'save',
                label: 'Save',
                style: 'primary',
            },
            {
                name: 'cancel',
                label: 'Cancel',
            }
        ],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:save', function () {
                this.getConfig().set(this.model.toJSON());
            }.bind(this));
        },

        saveModel(model, callback, skipExit, attrs) {
            this.notify('Saving...');
            let self = this;

            this.ajaxPatchRequest('settings', attrs).success(function (res) {
                model.set(res);

                this.attributes = model.getClonedAttributes();
                model._updatedById = self.getUser().id;
                self.afterSave();

                if (this.mode === 'edit') {
                    model.trigger('after:save');
                    self.trigger('after:save');
                }

                if (!callback) {
                    if (!skipExit) {
                        self.exit('save');
                    }
                } else {
                    callback(self);
                }
            }.bind(this)).error(function (xhr) {
                let statusReason = xhr.responseText || '';
                if (xhr.responseJSON && xhr.responseJSON.reason) {
                    statusReason = xhr.responseJSON.reason;
                }

                xhr.errorIsHandled = true;
                if (xhr.status === 409) {
                    self.notify(false);
                    self.enableButtons();
                    self.trigger('cancel:save');
                    Espo.Ui.confirm(statusReason || this.translate('unableToDuplicateRecord', 'messages'), {
                        confirmText: self.translate('Apply'),
                        cancelText: self.translate('Cancel')
                    }, function () {
                        attrs['_prev'] = null;
                        attrs['_ignoreConflict'] = true;
                        attrs['_silentMode'] = false;
                        self.saveModel(model, callback, skipExit, attrs);
                    });
                } else {
                    self.enableButtons();
                    self.trigger('cancel:save');
                    if (xhr.status === 304) {
                        Espo.Ui.notify(self.translate('notModified', 'messages'), 'warning', 1000 * 60 * 60 * 2, true);
                    } else {
                        Espo.Ui.notify(`${self.translate("Error")} ${xhr.status}: ${statusReason}`, "error", 1000 * 60 * 60 * 2, true);
                    }
                }
            }.bind(this));
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

        exit: function (after) {
            if (after == 'cancel') {
                this.getRouter().navigate('#Admin', {trigger: true});
            }
        },
    });
});

