/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore GmbH.
 *
 * This Software is the property of AtroCore GmbH and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

Espo.define('views/modals/send-email', 'views/modal',
    Dep => Dep.extend({

        template: 'modals/send-email',

        setup() {
            this.scope = 'EmailTemplate';

            this.buttonList = [
                {
                    name: 'send',
                    label: 'send',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.header = this.getLanguage().translate('sendEmail', 'labels', 'Action');

            this.setupFields();
        },

        data() {
            return {
                allowAttachments: this.model.get('allowAttachments')
            }
        },

        setupFields() {
            this.createView('emailTo', 'views/fields/array-email', {
                name: 'emailTo',
                el: `${this.options.el} .field[data-name="emailTo"]`,
                model: this.model,
                scope: this.scope,
                defs: {
                    name: 'emailTo'
                },
                mode: 'edit',
                prohibitedEmptyValue: true,
                inlineEditDisabled: true
            }, view => view.render());

            this.createView('emailCc', 'views/fields/array-email', {
                name: 'emailCc',
                el: `${this.options.el} .field[data-name="emailCc"]`,
                model: this.model,
                scope: this.scope,
                defs: {
                    name: 'emailCc'
                },
                mode: 'edit',
                prohibitedEmptyValue: true,
                inlineEditDisabled: true
            }, view => view.render());

            this.createView('subject', 'views/fields/text', {
                name: 'subject',
                el: `${this.options.el} .field[data-name="subject"]`,
                model: this.model,
                scope: this.scope,
                defs: {
                    name: 'subject'
                },
                mode: 'edit',
                prohibitedEmptyValue: true,
                inlineEditDisabled: true
            }, view => view.render());

            this.createView('body', 'views/fields/text', {
                name: 'body',
                el: `${this.options.el} .field[data-name="body"]`,
                model: this.model,
                scope: this.scope,
                defs: {
                    name: 'body'
                },
                mode: 'edit',
                prohibitedEmptyValue: true,
                inlineEditDisabled: true
            }, view => view.render());

            if (this.model.get('allowAttachments')) {
                this.createView('attachments', 'views/fields/link-multiple', {
                    name: 'attachments',
                    el: `${this.options.el} .field[data-name="attachments"]`,
                    model: this.model,
                    scope: this.scope,
                    foreignScope: 'File',
                    defs: {
                        name: 'attachments'
                    },
                    mode: 'edit',
                    prohibitedEmptyValue: true,
                    inlineEditDisabled: true
                }, view => view.render());
            }
        },

        actionSend: function () {
            const data = {
                subject: this.model.get('subject'),
                body: this.model.get('body'),
                emailTo: this.model.get('emailTo'),
                emailCc: this.model.get('emailCc')
            }
            if (this.model.get('allowAttachments')) {
                data.attachmentsIds = this.model.get('attachmentsIds')
            }
            if (!data.subject) {
                Espo.ui.error("Please enter a subject")
                return
            }
            if (!data.emailTo || data.emailTo.length === 0) {
                Espo.ui.error("Please enter an email address")
                return
            }

            this.options.callback(data);
        },

    })
);
