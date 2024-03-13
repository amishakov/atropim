/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/list', 'views/record/list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            (this.getMetadata().get(['clientDefs', this.scope, 'disabledMassActions']) || []).forEach(item => this.removeMassAction(item));
        }
    })
);

