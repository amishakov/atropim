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

declare(strict_types=1);

namespace Pim\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\AclManager;
use Espo\ORM\Entity;

class RoleScopeAttributePanel extends Base
{
    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('attributePanelId') || $entity->isAttributeChanged('roleScopeId')) {
            $exists = $this
                ->where([
                    'roleScopeId' => $entity->get('roleScopeId'),
                    'attributePanelId' => $entity->get('attributePanelId')
                ])
                ->findOne();

            if (!empty($exists)) {
                $fieldName = $this->getLanguage()->translate('attributePanel', 'fields', 'RoleScopeAttributePanel');
                $message = $this->getLanguage()->translate('notUniqueRecordField', 'exceptions');
                throw new NotUnique(sprintf($message, $fieldName));
            }

            $attributePanel = $this->getEntityManager()->getRepository('AttributePanel')->get($entity->get('attributattributePanelId'));
            $roleScope = $this->getEntityManager()->getRepository('RoleScope')->get($entity->get('roleScopeId'));

            if ($roleScope->get('name') !== $attributePanel->get('entityId')) {
                throw new BadRequest("The Attribute Panel {$attributePanel->get('name')} could not be chosen for the Scope {$roleScope->get('name')}");
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this
            ->getAclManager()
            ->clearAclCache();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this
            ->getAclManager()
            ->clearAclCache();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }
}
