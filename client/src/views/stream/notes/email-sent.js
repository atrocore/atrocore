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

Espo.define('views/stream/notes/email-sent', 'views/stream/note', function (Dep) {

    return Dep.extend({

        template: 'stream/notes/email-sent',

        isRemovable: false,

        data: function () {
            return _.extend({
                emailIconClassName: this.getMetadata().get(['clientDefs', 'EmailTemplate', 'iconClass']) || ''
            }, Dep.prototype.data.call(this));
        },

        events: {
            'click a[data-action="expandDetails"]': function (e) {
                if (this.$el.find('.details').hasClass('hidden')) {
                    this.$el.find('.details').removeClass('hidden');
                    $(e.currentTarget).find('span').removeClass('fa-angle-down').addClass('fa-angle-up');
                } else {
                    this.$el.find('.details').addClass('hidden');
                    $(e.currentTarget).find('span').addClass('fa-angle-down').removeClass('fa-angle-up');
                }
            }
        },

        setup: function () {
            var data = this.model.get('data') || {};

            this.model.set('subject', data.subject);
            this.model.set('body', data.body);
            this.model.set('emailTo', data.emailTo);
            this.model.set('emailCc', data.emailCc);

            if (
                this.parentModel
                &&
                (this.model.get('parentType') == this.parentModel.name && this.model.get('parentId') == this.parentModel.id)
            ) {
                this.createView('emailTo', 'views/fields/array', {
                    name: 'emailTo',
                    model: this.model,
                    scope: this.scope,
                    defs: {
                        name: 'emailTo'
                    },
                    mode: 'detail',
                    inlineEditDisabled: true
                }, view => view.render());

                this.createView('emailCc', 'views/fields/array', {
                    name: 'emailCc',
                    model: this.model,
                    scope: this.scope,
                    defs: {
                        name: 'emailCc'
                    },
                    mode: 'detail',
                    inlineEditDisabled: true
                }, view => view.render());

                this.createView('subject', 'views/fields/text', {
                    name: 'subject',
                    model: this.model,
                    scope: this.scope,
                    defs: {
                        name: 'subject'
                    },
                    mode: 'detail',
                    inlineEditDisabled: true
                }, view => view.render());

                this.createView('body', 'views/fields/text', {
                    name: 'body',
                    model: this.model,
                    scope: this.scope,
                    defs: {
                        name: 'body'
                    },
                    mode: 'detail',
                    inlineEditDisabled: true
                }, view => view.render());
            }

            this.messageData['createdAt'] = 'field:createdAt'
            this.messageData['subject'] = this.model.get('subject')
            this.messageData['emailTo'] = (this.model.get('emailTo') ?? []).join(";")
            this.messageName = 'emailSent';

            if (this.isThis) {
                this.messageName += 'This';
            }

            this.createMessage();
        },

    });
});
