/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/default-unit', 'views/fields/base', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            // varchar, email, color

            // text, wysiwyg {
            //     "name": "default",
            //     "type": "text"
            // },


            // int {
            //     "name": "default",
            //     "type": "int"
            // },


            // markdown {
            //     "name": "default",
            //     "type": "markdown"
            // },

        // link
        //     {
        //         "name": "default",
        //         "type": "link",
        //         "view": "views/admin/field-manager/fields/link/default"
        //     }

            // measure ,
            //     {
            //       "name": "default",
            //       "type": "varchar",
            //       "view": "views/admin/field-manager/fields/link/measure-default"
            //     }


            // extensibleEnum {
            //     "name": "default",
            //     "type": "link",
            //     "view": "views/admin/field-manager/fields/link/extensible-enum-default"
            // },


            // rangeInt {
            //     "name": "default",
            //     "type": "rangeInt"
            // },



            // rangeFloat {
            //     "name": "default",
            //     "type": "rangeFloat"
            // },

        // file
        //     {
        //         "name": "default",
        //         "type": "link",
        //         "view": "views/admin/field-manager/fields/file/default"
        //     }


            // extensibleMultiEnum {
            //     "name": "default",
            //     "type": "linkMultiple",
            //     "view": "views/admin/field-manager/fields/linkMultiple/extensible-multi-enum-default"
            // },

        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
        },

    });

});

