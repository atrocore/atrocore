/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/selection/record/detail/detail-comparison-view', 'views/record/right-side-view', function (Dep) {

    return Dep.extend({
        bottomView: false,

        layoutName: 'list',

        prepareLayoutData(data) {
            let layout = [
                {
                    label: "",
                    style: "",
                    rows: []
                }
            ];

            for (const fieldData of data.layout) {
                layout[0].rows.push([{
                    name: fieldData.name,
                    fullWidth: true
                }])
            }

            data.layout = layout;
        }
    });
});
