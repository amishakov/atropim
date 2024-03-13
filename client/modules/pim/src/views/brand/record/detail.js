/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/brand/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        delete: function () {
            Espo.TreoUi.confirmWithBody('', {
                confirmText: this.translate('Remove'),
                cancelText: this.translate('Cancel'),
                body: this.getBodyHtml()
            }, function () {
                this.trigger('before:delete');
                this.trigger('delete');

                this.notify('Removing...');

                var collection = this.model.collection;

                var self = this;
                this.model.destroy({
                    wait: true,
                    data: JSON.stringify({}),
                    error: function () {
                        this.notify('Error occured!', 'error');
                    }.bind(this),
                    success: function () {
                        if (collection) {
                            if (collection.total > 0) {
                                collection.total--;
                            }
                        }

                        this.notify('Removed', 'success');
                        this.trigger('after:delete');
                        this.exit('delete');
                    }.bind(this),
                });
            }, this);
        },

        getBodyHtml() {
            return '' +
                '<div class="row">' +
                    '<div class="col-xs-12">' +
                        '<span class="confirm-message">' + this.translate('removeRecordConfirmation', 'messages') + '</span>' +
                    '</div>' +
                '</div>';
        }

    })
);