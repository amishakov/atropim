<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Pim\Acl;

use Espo\Core\Acl\Base;
use Espo\Entities\User;
use Espo\ORM\Entity;

class ProductAttributeValue extends Base
{
    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->getAclManager()->checkScope($user, 'AttributeTab', $action) || $this->getAclManager()->checkScope($user, 'Attribute', $action);
    }

    public function checkEntity(User $user, Entity $entity, $data, $action)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (empty($entity->get('attributeId'))) {
            return false;
        }

        $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));

        if (!empty($attribute->get('attributeTabId')) && !empty($tab = $attribute->get('attributeTab'))) {
            return $this->getAclManager()->checkEntity($user, $tab, $action) && $this->getAclManager()->checkEntity($user, $attribute, $action);
        }

        return $this->getAclManager()->checkEntity($user, $attribute, $action);
    }
}
