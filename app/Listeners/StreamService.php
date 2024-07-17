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

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Espo\ORM\EntityCollection;

class StreamService extends AbstractEntityListener
{
    public function prepareCollectionForOutput(Event $event): void
    {
        /** @var EntityCollection $collection */
        $collection = $event->getArgument('collection');

        $associations = [];
        $products = [];

        foreach ($collection as $entity) {
            if (in_array($entity->get('type'), ['CreateProductAssociation', 'DeleteProductAssociation'])) {
                $associations[$entity->get('data')->associationId] = null;
                $products[$entity->get('data')->relatedProductId] = null;
            }
        }

        foreach ($this->getEntityManager()->getRepository('Association')->where(['id' => array_keys($associations)])->find() as $association) {
            $associations[$association->get('id')] = $association;
        }

        foreach ($this->getEntityManager()->getRepository('Product')->where(['id' => array_keys($products)])->find() as $product) {
            $products[$product->get('id')] = $product;
        }

        foreach ($collection as $entity) {
            if (in_array($entity->get('type'), ['CreateProductAssociation', 'DeleteProductAssociation'])) {
                $entity->get('data')->associationName = $entity->get('data')->associationId;
                if (!empty($associations[$entity->get('data')->associationId])) {
                    $entity->get('data')->associationName = $associations[$entity->get('data')->associationId]->get('name');
                }
                $entity->get('data')->relatedProductName = $entity->get('data')->relatedProductId;
                if (!empty($products[$entity->get('data')->relatedProductId])) {
                    $entity->get('data')->relatedProductName = $products[$entity->get('data')->relatedProductId]->get('name');
                }
            }
        }
    }

    public function prepareNoteFieldDefs(Event $event): void
    {
        $entity = $event->getArgument('entity');

        $attributeId = $entity->get('data')->attributeId ?? null;

        if (empty($attributeId)) {
            return;
        }

        $attribute = $this->getEntityManager()->getRepository('Attribute')->get($attributeId);

        // for backward compatibility
        if (empty($attribute)) {
            $pav = $this->getEntityManager()->getRepository('ProductAttributeValue')->get($attributeId);
            if (!empty($pav)) {
                $attribute = $pav->get('attribute');
            }
        }

        if (empty($attribute)) {
            return;
        }

        $fieldDefs = [
            'type'  => $attribute->get('type'),
            'label' => $attribute->get('name'),
        ];
        if (!empty($attribute->get('extensibleEnumId'))) {
            $fieldDefs['extensibleEnumId'] = $attribute->get('extensibleEnumId');
        }

        switch ($event->getArgument('field')) {
            case 'valueFrom':
                $fieldDefs['type'] = 'float';
                $fieldDefs['label'] .= ' ' . $this->getLanguage()->translate('From');
                break;
            case 'valueTo':
                $fieldDefs['type'] = 'float';
                $fieldDefs['label'] .= ' ' . $this->getLanguage()->translate('To');
                break;
            case 'valueId':
                $fieldDefs['type'] = 'link';
                $fieldDefs['entity'] = 'File';
                break;
            case 'valueUnit':
                $fieldDefs['type'] = 'link';
                $fieldDefs['entity'] = 'Unit';
                $fieldDefs['label'] .= ' ' . $this->getLanguage()->translate('unitPart');
                break;
        }

        $event->setArgument('fieldDefs', $fieldDefs);
    }
}
