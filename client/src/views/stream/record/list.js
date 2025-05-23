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

Espo.define('views/stream/record/list', 'views/record/list-expanded', function (Dep) {

    return Dep.extend({

        type: 'listStream',

        setup: function () {
            this.itemViews = this.getMetadata().get('clientDefs.Note.itemViews') || {};
            Dep.prototype.setup.call(this);
        },

        buildRow: function (i, model, callback) {
            var key = model.id;
            this.rowList.push(key);

            var type = model.get('type');
            var viewName = this.itemViews[type] || 'views/stream/notes/' + Espo.Utils.camelCaseToHyphen(type);

            this.createView(key, viewName, {
                model: model,
                parentModel: this.model,
                acl: {
                    edit: this.getAcl().checkModel(this.model, 'edit')
                },
                isUserStream: this.options.isUserStream,
                noEdit: this.options.noEdit,
                optionsToPass: ['acl'],
                name: this.type + '-' + model.name,
                el: this.options.el + ' li[data-id="' + model.id + '"]',
                setViewBeforeCallback: this.options.skipBuildRows && !this.isRendered()
            }, callback);
        },

        buildRows: function (callback) {
            this.checkedList = [];
            this.rowList = [];

            if (this.collection.length > 0) {
                this.wait(true);

                var count = this.collection.models.length;
                var built = 0;
                for (var i in this.collection.models) {
                    var model = this.collection.models[i];
                    this.buildRow(i, model, function () {
                        built++;
                        if (built == count) {
                            if (typeof callback == 'function') {
                                callback();
                            }
                            this.wait(false);
                            this.trigger('after:build-rows');
                        }
                    }.bind(this));
                }
            } else {
                if (typeof callback == 'function') {
                    callback();
                    this.trigger('after:build-rows');
                }
            }
        },

        showNewRecords: function () {
            var collection = this.collection;
            var initialCount = collection.length;

            var $list = this.$el.find(this.listContainerEl);

            var success = function () {
                if (initialCount === 0) {
                    this.reRender();
                    return;
                }
                var rowCount = collection.length - initialCount;
                var rowsReady = 0;
                for (var i = rowCount - 1; i >= 0; i--) {
                    var model = collection.at(i);

                    this.buildRow(i, model, function (view) {
                        view.getHtml(function (html) {
                            var $row = $(this.getRowContainerHtml(model.id));
                            $row.append(html);
                            $list.prepend($row);
                            rowsReady++;
                            view._afterRender();
                            if (view.options.el) {
                                view.setElement(view.options.el);
                            }
                        }.bind(this));
                    });
                }
                this.noRebuild = true;
            }.bind(this);

            collection.fetchNew({
                success: success,
            });
        }

    });

});
