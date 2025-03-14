/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/row-actions/relationship-categories', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList: function () {
            let list = Dep.prototype.getActionList.call(this);
            const model = this.model.relationModel

            if (model && !this.model.get('mainCategory') && this.getAcl().check('ProductCategory', 'edit')) {
                list.unshift({
                    action: 'setAsMainCategory',
                    label: this.translate('setAsMainCategory'),
                    data: {
                        id: model.get('id')
                    }
                });
            }
            return list;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.$el && this.model.get('ProductCategory__mainCategory')) {
                this.$el.parent().addClass('main-category');
            }
        },


    })
);