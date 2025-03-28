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

namespace Pim\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Services\Base;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class Attribute extends Base
{
    protected $mandatorySelectAttributeList = ['sortOrder', 'extensibleEnumId', 'data', 'measureId', 'defaultUnit'];

    public function addAttributeValue(string $entityName, string $entityId, ?array $where, ?array $ids): bool
    {
        if ($where !== null) {
            $selectParams = $this
                ->getSelectManager()
                ->getSelectParams(['where' => json_decode(json_encode($where), true)], true);
            $attributes = $this->getRepository()->find($selectParams);
        } elseif ($ids !== null) {
            $attributes = $this->getRepository()->where(['id' => $ids])->find();
        }

        if (empty($attributes)) {
            return false;
        }

        $conn = $this->getEntityManager()->getConnection();
        $name = Util::toUnderScore(lcfirst($entityName));

        foreach ($attributes as $attribute) {
            try {
                $conn->createQueryBuilder()
                    ->insert("{$name}_attribute_value")
                    ->setValue('id', ':id')
                    ->setValue('attribute_id', ':attributeId')
                    ->setValue("{$name}_id", ':entityId')
                    ->setValue("attribute_type", ':attributeType')
                    ->setParameter('id', Util::generateId())
                    ->setParameter('attributeId', $attribute->get('id'))
                    ->setParameter('attributeType', $attribute->get('type'))
                    ->setParameter('entityId', $entityId)
                    ->executeQuery();
            } catch (UniqueConstraintViolationException $e) {
            }
        }

        return true;
    }

    public function removeAttributeValue(string $entityName, string $id): bool
    {
        $name = Util::toUnderScore(lcfirst($entityName));

        $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->delete("{$name}_attribute_value")
            ->where('id=:id')
            ->setParameter('id', $id)
            ->executeQuery();

        return true;
    }

    public function getRecordAttributes(string $entityName, string $entityId): array
    {
        $tableName = Util::toUnderScore(lcfirst($entityName));

        $conn = $this->getEntityManager()->getConnection();
        $res = $conn->createQueryBuilder()
            ->select('a.*, v.id as v_id')
            ->from("{$tableName}_attribute_value", 'v')
            ->leftJoin('v', $conn->quoteIdentifier('attribute'), 'a', 'a.id=v.attribute_id')
            ->where('v.deleted=:false')
            ->andWhere('a.deleted=:false')
            ->andWhere('v.' . "{$tableName}_id=:entityId")
            ->orderBy('a.sort_order', 'ASC')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('entityId', $entityId)
            ->fetchAllAssociative();

        $result = [];

        $languages = [];
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $code) {
                $languages[$code] = $code;
                foreach ($this->getConfig()->get('referenceData.Language', []) as $v) {
                    if ($code === $v['code']) {
                        $languages[$code] = $v['name'];
                        break;
                    }
                }
            }
        }

        foreach ($res as $item) {
            $data = @json_decode($item['data'], true);

            $row = [
                'id'                            => $item['v_id'],
                'attributeId'                   => $item['id'],
                'name'                          => "attr_{$item['v_id']}",
                'label'                         => $item['name'],
                'type'                          => $item['type'],
                'trim'                          => !empty($item['trim']),
                'required'                      => !empty($item['is_required']),
                'notNull'                       => !empty($item['not_null']),
                'useDisabledTextareaInViewMode' => !empty($item['use_disabled_textarea_in_view_mode']),
                'amountOfDigitsAfterComma'      => $item['amount_of_digits_after_comma'] ?? null,
                'prohibitedEmptyValue'          => !empty($item['prohibited_empty_value']),
                'extensibleEnumId'              => $item['extensible_enum_id'] ?? null
            ];

            if (!empty($data['maxLength'])) {
                $row['maxLength'] = $data['maxLength'];
            }

            if (!empty($data['countBytesInsteadOfCharacters'])) {
                $row['countBytesInsteadOfCharacters'] = $data['countBytesInsteadOfCharacters'];
            }

            if (isset($data['min'])) {
                $row['min'] = $data['min'];
            }

            if (isset($data['max'])) {
                $row['max'] = $data['max'];
            }

            if (isset($item['measure_id'])) {
                $row['measureId'] = $item['measure_id'];
                $row['view'] = "views/fields/unit-{$item['type']}";
            }

            $dropdownTypes = $this->getMetadata()->get(['app', 'attributeDropdownTypes'], []);
            if (!empty($item['dropdown']) && isset($dropdownTypes[$item['type']])) {
                $row['view'] = $dropdownTypes[$item['type']];
            }

            $result[] = $row;

            if (!empty($item['is_multilang'])) {
                foreach ($languages as $language => $languageName) {
                    $result[] = array_merge($row, [
                        'name'  => $row['id'] . ucfirst(Util::toCamelCase(strtolower($language))),
                        'label' => $item['name'] . ' / ' . $languageName
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getEntity($id = null)
    {
        $id = $this
            ->dispatchEvent('beforeGetEntity', new Event(['id' => $id]))
            ->getArgument('id');

        $entity = $this->getRepository()->get($id);

        if (!empty($entity) && $this->getConfig()->get('isMultilangActive', false) && $entity->get('isMultilang')) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $camelCaseLocale = Util::toCamelCase(strtolower($locale), '_', true);
                if (!empty($ownerUserId = $entity->get("ownerUser{$camelCaseLocale}Id"))) {
                    $ownerUser = $this->getEntityManager()->getEntity('User', $ownerUserId);
                    $entity->set("ownerUser{$camelCaseLocale}Name", $ownerUser->get('name'));
                } else {
                    $entity->set("ownerUser{$camelCaseLocale}Name", null);
                }

                if (!empty($assignedUserId = $entity->get("assignedUser{$camelCaseLocale}Id"))) {
                    $assignedUser = $this->getEntityManager()->getEntity('User', $assignedUserId);
                    $entity->set("assignedUser{$camelCaseLocale}Name", $assignedUser->get('name'));
                } else {
                    $entity->set("assignedUser{$camelCaseLocale}Name", null);
                }
            }
        }

        if (!empty($entity) && !empty($id)) {
            $this->loadAdditionalFields($entity);

            if (!$this->getAcl()->check($entity, 'read')) {
                throw new Forbidden();
            }
        }
        if (!empty($entity)) {
            $this->prepareEntityForOutput($entity);
        }

        return $this
            ->dispatchEvent('afterGetEntity', new Event(['id' => $id, 'entity' => $entity]))
            ->getArgument('entity');
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $dropdownTypes = $this->getMetadata()->get(['app', 'attributeDropdownTypes'], []);
        if (in_array($entity->get('type'), array_keys($dropdownTypes)) && $entity->get('dropdown') === null) {
            $entity->set('dropdown', false);
        }

        if (in_array($entity->get('type'),
                ['extensibleEnum', 'extensibleMultiEnum']) && $entity->get('extensibleEnum') !== null) {
            $entity->set('listMultilingual', $entity->get('extensibleEnum')->get('multilingual'));
        }

        if (!empty($entity->get('htmlSanitizerId'))) {
            $htmlSanitizer = $this->getEntityManager()->getRepository('HtmlSanitizer')->get($entity->get('htmlSanitizerId'));
            if (!empty($htmlSanitizer)) {
                $entity->set('htmlSanitizerName', $htmlSanitizer->get('name'));
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function updateEntity($id, $data)
    {
        if (property_exists($data, 'sortOrder') && property_exists($data, '_sortedIds')) {
            $this->getRepository()->updateSortOrderInAttributeGroup($data->_sortedIds);
            return $this->getEntity($id);
        }

        return parent::updateEntity($id, $data);
    }

    protected function duplicateProductAttributeValues(Entity $entity, Entity $duplicatingEntity)
    {
        foreach ($duplicatingEntity->get('productAttributeValues') as $item) {
            $record = $this->getEntityManager()->getEntity('ProductAttributeValue');
            $record->set($item->toArray());
            $record->id = null;

            $record->clear('createdAt');
            $record->clear('modifiedAt');
            $record->clear('createdById');
            $record->clear('modifiedById');

            $record->clear('boolValue');
            $record->clear('dateValue');
            $record->clear('datetimeValue');
            $record->clear('intValue');
            $record->clear('floatValue');
            $record->clear('varcharValue');
            $record->clear('textValue');
            $record->clear('referenceValue');

            $record->set('attributeId', $entity->get('id'));
            $record->set('attributeName', $entity->get('name'));
            $this->getEntityManager()->saveEntity($record);
        }
    }

    protected function init()
    {
        parent::init();

        // add dependencies
        $this->addDependency('language');
    }

    /**
     * Get multilang fields
     *
     * @return array
     */
    protected function getMultilangFields(): array
    {
        // get config
        $config = $this->getConfig()->get('modules');

        return (!empty($config['multilangFields'])) ? array_keys($config['multilangFields']) : [];
    }

    /**
     * @param Entity $entity
     */
    protected function afterDeleteEntity(Entity $entity)
    {
        // call parent action
        parent::afterDeleteEntity($entity);

        // unlink
        $this->unlinkAttribute([$entity->get('id')]);
    }

    /**
     * Unlink attribute from Classification and Product
     *
     * @param array $ids
     *
     * @return bool
     */
    protected function unlinkAttribute(array $ids): bool
    {
        // prepare data
        $result = false;

        if (!empty($ids)) {
            // remove from product families
            $this
                ->getEntityManager()
                ->getRepository('ClassificationAttribute')
                ->where([
                    'attributeId' => $ids
                ])
                ->removeCollection();

            // remove from products
            $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->where([
                    'attributeId' => $ids
                ])
                ->removeCollection();

            // prepare result
            $result = true;
        }

        return $result;
    }

    public function getDefaultValue(string $id): array
    {
        $attribute = $this->getRepository()->get($id);

        if (empty($attribute)) {
            throw new NotFound();
        }

        if ($attribute->get('type') !== 'varchar') {
            throw new BadRequest('Invalid Type for default value');
        }

        $value = "";

        if (!empty($default = $attribute->get('defaultValue'))) {
            if (strpos($default, '{{') >= 0 && strpos($default, '}}') >= 0) {
                // use twig
                $default = $this->getInjection('twig')->renderTemplate($default, []);
            }
            $value = $default;
        }

        return [
            "value" => $value
        ];
    }

    public function findEntities($params)
    {
        if (!empty(\Atro\Services\AbstractService::getLanguagePrism())) {
            return parent::findEntities($params);
        }

        $shouldUseLanguagePrism = true;

        if (!empty($params['select']) && $this->getConfig()->get('isMultilangActive')) {
            $userLanguage = $this->getInjection('user')->getLanguage();
            if (!empty($userLanguage) && in_array($userLanguage, $this->getConfig()->get('inputLanguageList'))) {
                foreach ($this->getConfig()->get('inputLanguageList') as $language) {
                    if ($language === 'main' || $language == $this->getConfig()->get('mainLanguage')) {
                        continue;
                    }
                    if (in_array(Util::toCamelCase('name_' . strtolower($language)), $params['select'])) {
                        $shouldUseLanguagePrism = false;
                    }
                }
            } else {
                $shouldUseLanguagePrism = false;
            }

            if ($shouldUseLanguagePrism) {
                $GLOBALS['languagePrism'] = $userLanguage;
            }
        }

        return parent::findEntities($params);
    }

    protected function checkFieldsWithPattern(Entity $entity): void
    {
        $attributeList = array_keys($this->getInjection('metadata')->get(['attributes']));
        if (!in_array($entity->get('type'), $attributeList)) {
            throw new Forbidden(str_replace('{type}', $entity->get('type'),
                $this->getInjection('language')->translate('invalidType', 'exceptions', 'Attribute')));
        }

        parent::checkFieldsWithPattern($entity);
    }

    protected function getTranslatedNameField(): string
    {
        $language = \Espo\Core\Services\Base::getLanguagePrism();
        if (empty($language)) {
            $language = $this->getInjection('user')->getLanguage();
        }
        if (!empty($language) && $language !== 'main') {
            if ($this->getConfig()->get('isMultilangActive') && in_array($language,
                    $this->getConfig()->get('inputLanguageList', []))) {
                return Util::toCamelCase('name_' . strtolower($language));
            }
        }

        return 'name';
    }
}
