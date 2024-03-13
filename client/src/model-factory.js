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

Espo.define('model-factory', [], function () {

    var ModelFactory = function (loader, metadata, user) {
        this.loader = loader;
        this.metadata = metadata;
        this.user = user;

        this.seeds = {};
    };

    _.extend(ModelFactory.prototype, {

        loader: null,

        metadata: null,

        seeds: null,

        dateTime: null,

        user: null,

        create: function (name, callback, context) {
            context = context || this;
            this.getSeed(name, function (seed) {
                var model = new seed();
                callback.call(context, model);
            }.bind(this));
        },

        getSeed: function (name, callback) {
            if ('name' in this.seeds) {
                callback(this.seeds[name]);
                return;
            }

            var className = this.metadata.get('clientDefs.' + name + '.model') || 'model';

            Espo.loader.require(className, function (modelClass) {
                this.seeds[name] = modelClass.extend({
                    name: name,
                    defs: this.metadata.get('entityDefs.' + name) || {},
                    dateTime: this.dateTime,
                    _user: this.user
                });
                callback(this.seeds[name]);
            }.bind(this));
        },
    });

    return ModelFactory;

});
