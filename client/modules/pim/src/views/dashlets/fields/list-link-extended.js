/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/fields/list-link-extended', 'views/fields/base',
    Dep => Dep.extend({

        listLinkTemplate: 'pim:dashlets/fields/list-link-extended/list-link',

        data() {
            return _.extend({
                linkEntity: this.model.getFieldParam('name', 'linkEntity')
            }, Dep.prototype.data.call(this));
        }

    })
);

