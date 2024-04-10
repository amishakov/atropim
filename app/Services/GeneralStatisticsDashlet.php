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

class GeneralStatisticsDashlet extends AbstractDashletService
{
    public function getDashlet(): array
    {
        $result['list'] = [
            [
                'id'     => 'product',
                'name'   => 'product',
                'amount' => $this->getAmountForScope('Product')
            ],
            [
                'id'     => 'category',
                'name'   => 'category',
                'amount' => $this->getAmountForScope('Category')
            ],
            [
                'id'     => 'classification',
                'name'   => 'classification',
                'amount' => $this->getAmountForScope('Classification')
            ],
            [
                'id'     => 'attribute',
                'name'   => 'attribute',
                'amount' => $this->getAmountForScope('Attribute')
            ],
            [
                'id'     => 'productWithoutAssociatedProduct',
                'name'   => 'productWithoutAssociatedProduct',
                'amount' => $this->getAmountProductWithoutAssociatedProduct()
            ],
            [
                'id'     => 'productWithoutCategory',
                'name'   => 'productWithoutCategory',
                'amount' => $this->getAmountProductWithoutCategory()
            ],
            [
                'id'     => 'productWithoutAttribute',
                'name'   => 'productWithoutAttribute',
                'amount' => $this->getAmountProductWithoutAttribute()
            ]
        ];

        if (!empty($this->getInjection('metadata')->get('entityDefs.Product.fields.image'))) {
            $result['list'][] = [
                'id'     => 'productWithoutFiles',
                'name'   => 'productWithoutFiles',
                'amount' => $this->getAmountProductWithoutFiles()
            ];
        }

        $result['total'] = count($result['list']);

        return $result;
    }

    public function getQueryProductWithoutFiles($count = false): string
    {
        $select = $count ? 'COUNT(p.id)' : 'p.id AS id';
        $sql
            = "SELECT " . $select . " 
                FROM product as p 
                WHERE p.id NOT IN (SELECT product_id FROM product_file WHERE deleted=:false) 
                  AND p.deleted=:false";

        return $sql;
    }

    /**
     * Get query for Product without AssociatedProduct
     *
     * @param bool $count
     *
     * @return string
     */
    public function getQueryProductWithoutAssociatedProduct($count = false): string
    {
        $select = $count ? 'COUNT(p.id)' : 'p.id AS id';
        $sql = "SELECT " . $select . " 
                FROM product as p 
                WHERE 
                    (SELECT COUNT(ap.id)                  
                    FROM associated_product AS ap
                      JOIN product AS p_rel 
                        ON p_rel.id = ap.related_product_id AND p_rel.deleted = :false
                      JOIN product AS p_main 
                        ON p_main.id = ap.related_product_id AND p_main.deleted = :false
                      JOIN association 
                        ON association.id = ap.association_id AND association.deleted = :false
                    WHERE ap.deleted = :false AND  ap.main_product_id = p.id) = :false 
                AND p.deleted = :false";

        return $sql;
    }

    /**
     * Get query for Product without Category
     *
     * @param bool $count
     *
     * @return string
     */
    public function getQueryProductWithoutCategory($count = false): string
    {
        // prepare select
        $select = $count ? 'COUNT(p.id)' : 'p.id as id';

        return "SELECT $select 
                FROM product p 
                LEFT JOIN product_category pc ON pc.product_id=p.id AND pc.deleted=:false
                WHERE p.deleted=:false 
                  AND pc.id IS NULL";
    }

    /**
     * @return string
     */
    protected function getQueryProductWithoutAttribute(): string
    {
        return "SELECT COUNT(p.id)
                FROM product p
                LEFT JOIN product_attribute_value pav ON pav.product_id = p.id AND pav.deleted = :false
                WHERE p.deleted = :false AND pav.id IS NULL";
    }

    protected function getAmountProductWithoutFiles(): int
    {
        if (!$this->getInjection('acl')->check('Product', 'read')) {
            return 0;
        }

        $sth = $this->getEntityManager()->getPDO()->prepare($this->getQueryProductWithoutFiles(false));
        $sth->bindValue(':false', false, \PDO::PARAM_BOOL);
        $sth->execute();

        $ids = $sth->fetchAll(\PDO::FETCH_COLUMN);

        $result = $this
            ->getInjection('serviceFactory')
            ->create('Product')
            ->findEntities(['maxSize' => 1, 'where' => [['type' => 'in', 'attribute' => 'id', 'value' => $ids]]]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    /**
     * Get Amount Product without AssociatedProduct
     *
     * @return int
     */
    protected function getAmountProductWithoutAssociatedProduct(): int
    {
        if (!$this->getInjection('acl')->check('Product', 'read')) {
            return 0;
        }

        $sth = $this->getEntityManager()->getPDO()->prepare($this->getQueryProductWithoutAssociatedProduct(false));
        $sth->bindValue(':false', false, \PDO::PARAM_BOOL);
        $sth->execute();

        $ids = $sth->fetchAll(\PDO::FETCH_COLUMN);

        $result = $this
            ->getInjection('serviceFactory')
            ->create('Product')
            ->findEntities(['maxSize' => 1, 'where' => [['type' => 'in', 'attribute' => 'id', 'value' => $ids]]]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    /**
     * Get amount Product without category
     *
     * @return int
     */
    protected function getAmountProductWithoutCategory(): int
    {
        if (!$this->getInjection('acl')->check('Product', 'read')) {
            return 0;
        }

        $sth = $this->getEntityManager()->getPDO()->prepare($this->getQueryProductWithoutCategory(false));
        $sth->bindValue(':false', false, \PDO::PARAM_BOOL);
        $sth->execute();

        $ids = $sth->fetchAll(\PDO::FETCH_COLUMN);

        $result = $this
            ->getInjection('serviceFactory')
            ->create('Product')
            ->findEntities(['maxSize' => 1, 'where' => [['type' => 'in', 'attribute' => 'id', 'value' => $ids]]]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    /**
     * @return int
     */
    protected function getAmountProductWithoutAttribute(): int
    {
        if (!$this->getInjection('acl')->check('Product', 'read')) {
            return 0;
        }

        $sth = $this->getEntityManager()->getPDO()->prepare($this->getQueryProductWithoutAttribute(false));
        $sth->bindValue(':false', false, \PDO::PARAM_BOOL);
        $sth->execute();

        $ids = $sth->fetchAll(\PDO::FETCH_COLUMN);

        $result = $this
            ->getInjection('serviceFactory')
            ->create('Product')
            ->findEntities(['maxSize' => 1, 'where' => [['type' => 'in', 'attribute' => 'id', 'value' => $ids]]]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    protected function getAmountForScope(string $entityName): int
    {
        if (!$this->getInjection('acl')->check($entityName, 'read')) {
            return 0;
        }

        $result = $this->getInjection('serviceFactory')->create($entityName)->findEntities(['maxSize' => 1]);

        return !empty($result['total']) ? (int)$result['total'] : 0;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
        $this->addDependency('acl');
    }
}
