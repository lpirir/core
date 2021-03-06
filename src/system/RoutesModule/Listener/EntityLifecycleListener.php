<?php
/**
 * Routes.
 *
 * @copyright Zikula contributors (Zikula)
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @author Zikula contributors <support@zikula.org>.
 * @link http://www.zikula.org
 * @link http://zikula.org
 * @version Generated by ModuleStudio 1.0.0 (https://modulestudio.de).
 */

namespace Zikula\RoutesModule\Listener;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Zikula\RoutesModule\Listener\Base\AbstractEntityLifecycleListener;

/**
 * Event subscriber implementation class for entity lifecycle events.
 */
class EntityLifecycleListener extends AbstractEntityLifecycleListener
{
    /**
     * @inheritDoc
     */
    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if (!$this->isEntityManagedByThisBundle($entity) || !method_exists($entity, 'get_objectType')) {
            return;
        }

        if (php_sapi_name() == 'cli') {
            return;
        }

        if (null === $this->container->get('request_stack')->getCurrentRequest()) {
            return;
        }

        return parent::postLoad($args);
    }
}
