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

namespace Pim\Entities;

use Atro\Core\Templates\Entities\Hierarchy;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Core\Exceptions\Error;

/**
 * Entity Category
 */
class Category extends Hierarchy
{
    public bool $recursiveSave = false;

    /**
     * @var string
     */
    protected $entityType = "Category";

    /**
     * @return Entity
     * @throws Error
     */
    public function getRoot(): Entity
    {
        // validation
        $this->isEntity();

        $categoryRoute = explode('|', (string)$this->get('categoryRoute'));

        return (isset($categoryRoute[1])) ? $this->getEntityManager()->getEntity('Category', $categoryRoute[1]) : $this;
    }

    public function getParentsIds(): array
    {
        // validation
        $this->isEntity();

        $parentsIds = explode('|', (string)$this->get('categoryRoute'));
        array_shift($parentsIds);
        array_pop($parentsIds);

        return $parentsIds;
    }

    /**
     * @return bool
     * @throws Error
     */
    public function hasChildren(): bool
    {
        // validation
        $this->isEntity();

        $children = $this->get('children');

        return !empty($children) && count($children) > 0;
    }

    /**
     * @return EntityCollection
     * @throws Error
     */
    public function getTreeProducts(): EntityCollection
    {
        // validation
        $this->isEntity();

        // prepare where
        $where = [
            'categories.id' => [$this->get('id')]
        ];

        $childIds = $this->getEntityManager()->getRepository('Category')->getChildrenRecursivelyArray($this->get('id'));

        if (count($childIds) > 0) {
            $where['categories.id'] = array_merge($where['categories.id'], $childIds);
        }

        return $this
            ->getEntityManager()
            ->getRepository('Product')
            ->distinct()
            ->join('categories')
            ->where($where)
            ->find();
    }

    /**
     * @return bool
     * @throws Error
     */
    protected function isEntity(): bool
    {
        if (empty($id = $this->get('id'))) {
            throw new Error('Category is not exist');
        }

        return true;
    }
}
