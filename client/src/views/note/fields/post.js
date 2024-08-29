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

Espo.define('views/note/fields/post', ['views/fields/markdown', 'lib!TextComplete'], function (Dep, Lib) {

    return Dep.extend({

        seeMoreText: false,

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        afterRender: function () {
            this.once('before:editor:rendered', textarea => {
                textarea.attr('placeholder', this.translate('writeMessage', 'messages', 'Note'));
            });

            Dep.prototype.afterRender.call(this);

            const assignmentPermission = this.getAcl().get('assignmentPermission');
            const buildUserListUrl = function (term) {
                let url = 'User?orderBy=name&limit=7&q=' + term + '&' + $.param({'primaryFilter': 'active'});
                if (assignmentPermission === 'team') {
                    url += '&' + $.param({'boolFilterList': ['onlyMyTeam']})
                }
                return url;
            }.bind(this);

            if (assignmentPermission !== 'no') {
                const cmWrapper = new Lib.CodeMirrorEditor(this.editor.codemirror);
                const textcomplete = new Lib.Textcomplete(cmWrapper, [{
                    match: /(^|\s)@((\w|\.)*)$/,
                    search: function (term, callback, match) {
                        if (!match[2]) {
                            callback([]);
                            return;
                        }
                        $.ajax({
                            url: buildUserListUrl(match[2])
                        }).done(function (data) {
                            callback(data.list)
                        });
                    },
                    template: function (mention) {
                        return mention.name + ' <span class="text-muted">@' + mention.userName + '</span>';
                    },
                    replace: function (o) {
                        return '$1@' + o.userName + ' ';
                    }
                }], {
                    dropdown: {
                        className: "dropdown-menu textcomplete-dropdown",
                        maxCount: 7,
                        placement: "auto",
                        style: {zIndex: 1100},
                        item: {
                            className: "textcomplete-item",
                            activeClassName: "textcomplete-item active",
                        }
                    },
                });

                this.once('remove', function () {
                    textcomplete?.destroy();
                }, this);
            }
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if ((this.model.get('attachmentsIds') || []).length) {
                    return false;
                }
            }
            return Dep.prototype.validateRequired.call(this);
        },


    });

});