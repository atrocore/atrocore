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

Espo.define('views/fields/user-with-avatar', 'views/fields/user', function (Dep) {

    return Dep.extend({

        listTemplate: 'fields/user-with-avatar/list',

        detailTemplate: 'fields/user-with-avatar/detail',

        data: function () {
            var o = _.extend({}, Dep.prototype.data.call(this));
            o.avatar = this.getAvatarHtml();

            return o;
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:' + this.idName, () => {
                this.clearView('tooltip');
            });
        },

        getAvatarHtml: function () {
            return this.getHelper().getAvatarHtml(this.model.get(this.idName), 'small', 28, 'avatar-link');
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.$el.length) {
                const streamCode = this.$el.closest('code.was, code.became');
                const avatar = this.$el.find('img.avatar');
                const link = this.$el.find('.user-link');

                if (streamCode.length && avatar.length) {
                    avatar.remove();
                }

                if (!this.getAcl().check('User', 'read')) {
                    return;
                }

                let tooltipTrigger = null;
                if (avatar.length) {
                    tooltipTrigger = avatar.get(0);
                } else if (link.length) {
                    tooltipTrigger = link.get(0);
                }

                if (tooltipTrigger) {
                    window.tippy(tooltipTrigger, {
                        allowHTML: true,
                        appendTo: () => document.body,
                        arrow: true,
                        content: '<img height="12" src="client/img/atro-loader.svg">',
                        delay: [700, 0],
                        hideOnClick: false,
                        interactive: true,
                        maxWidth: 350,
                        onShow: (instance, event) => {
                            if (this.hasView('tooltip')) {
                                return;
                            }

                            this.getModelFactory().create('User', (model) => {
                                model.set('id', this.model.get(this.idName));
                                model.fetch();

                                this.listenTo(model, 'sync', () => {
                                    this.createView('tooltip', 'views/fields/user-with-avatar/tooltip', {
                                        model: model
                                    }, view => view.getHtml(html => instance.setContent(html)));
                                })
                            })
                        },
                        trigger: 'mouseenter',
                    });
                }
            }
        }
    });
});
