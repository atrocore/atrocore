/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/edit', 'class-replace!treo-core:views/edit',
    Dep => Dep.extend({
        // getHeader: function () {
        //     const headerIconHtml = this.getHeaderIconHtml();
        //     const arr = [];
        //     let html = '';
        //
        //     if (this.options.noHeaderLinks) {
        //         arr.push(this.getLanguage().translate(this.scope, 'scopeNamesPlural'));
        //     } else {
        //         const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
        //         arr.push(headerIconHtml + '<a href="' + rootUrl + '" class="action" data-action="navigateToRoot">' + this.getLanguage().translate(this.scope, 'scopeNamesPlural') + '</a>');
        //     }
        //
        //     if (this.model.isNew()) {
        //         arr.push(this.getLanguage().translate('New'));
        //     } else {
        //         let name = Handlebars.Utils.escapeExpression(this.model.get('name'));
        //
        //         if (name === '') {
        //             name = this.model.id;
        //         }
        //
        //         if (this.options.noHeaderLinks) {
        //             arr.push(name);
        //         } else {
        //             arr.push('<a href="#' + this.scope + '/view/' + this.model.id + '" class="action">' + name + '</a>');
        //         }
        //     }
        //     return this.buildHeaderHtml(arr);
        // },
    })
);