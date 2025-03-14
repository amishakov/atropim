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
use Atro\Core\Utils\Util;
use Atro\Listeners\AbstractLayoutListener;

class FileLayout extends AbstractLayoutListener
{

    public function detail(Event $event)
    {
        $relatedEntity = $this->getRelatedEntity($event);
        $result = $event->getArgument('result');

        if ($relatedEntity === 'Product' && !str_contains(json_encode($result), '"ProductFile__isMainImage"')) {
            $result[0]['rows'][] = [['name' => 'ProductFile__isMainImage'], ['name' => 'ProductFile__sorting']];
        }

        if ($relatedEntity === 'Category' && !str_contains(json_encode($result), '"CategoryFile__isMainImage"')) {
            $result[0]['rows'][] = [['name' => 'CategoryFile__isMainImage'], ['name' => 'CategoryFile__sorting']];
        }

        $event->setArgument('result', $result);
    }
}
