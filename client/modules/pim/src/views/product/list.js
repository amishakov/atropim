/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/list', 'views/list-tree',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            // move create button to the end
            this.menu.buttons.push(this.menu.buttons.shift());
        },

    })
);
