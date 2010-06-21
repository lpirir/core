<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) 2002, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_System_Modules
 * @subpackage Search
 * @author Mark West
 */

class Search_Admin extends Zikula_Controller
{
    /**
     * the main administration function
     *
     * This function is the default function, and is called whenever the
     * module is called without defining arguments.
     * As such it can be used for a number of things, but most commonly
     * it either just shows the module menu and returns or calls whatever
     * the module designer feels should be the default function (often this
     * is the view() function)
     *
     * @author       The Zikula Development Team
     * @return       output       The main module admin page.
     */
    public function main()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Search::', '::', ACCESS_EDIT)) {
            return LogUtil::registerPermissionError();
        }

        // Create output object
        $renderer = Renderer::getInstance('Search');

        // Return the output that has been generated by this function
        return $renderer->fetch('search_admin_main.htm');
    }

    /**
     * Modify configuration
     *
     * This is a standard function to modify the configuration parameters of the
     * module
     *
     * @author       The Zikula Development Team
     * @return       output       The configuration page
     */
    public function modifyconfig()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Search::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // get the list of available plugins
        $plugins = ModUtil::apiFunc('Search', 'user', 'getallplugins', array('loadall' => true));

        // get the disabled status
        foreach ($plugins as $key => $plugin) {
            if (isset($plugin['title'])) {
                $plugins[$key]['disabled'] = ModUtil::getVar('Search', "disable_$plugin[title]");
            }
        }

        // Create output object
        $renderer = Renderer::getInstance('Search', false);

        // assign all module vars
        $renderer->assign(ModUtil::getVar('Search'));

        // assign the plugins
        $renderer->assign('plugins', $plugins);

        // Return the output that has been generated by this function
        return $renderer->fetch('search_admin_modifyconfig.htm');
    }

    /**
     * Update the configuration
     *
     * This is a standard function to update the configuration parameters of the
     * module given the information passed back by the modification form
     * Modify configuration
     *
     * @author       Jim McDonald
     * @param        bold           print items in bold
     * @param        itemsperpage   number of items per page
     */
    public function updateconfig()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Search::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Confirm authorisation code.
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Search','admin','main'));
        }

        // Update module variables.
        $itemsperpage = (int)FormUtil::getPassedValue('itemsperpage', 10, 'POST');
        ModUtil::setVar('Search', 'itemsperpage', $itemsperpage);
        $limitsummary = (int)FormUtil::getPassedValue('limitsummary', 255, 'POST');
        ModUtil::setVar('Search', 'limitsummary', $limitsummary);

        $disable = FormUtil::getPassedValue('disable', null, 'REQUEST');
        // get the list of available plugins
        $plugins = ModUtil::apiFunc('Search', 'user', 'getallplugins', array('loadall' => true));
        // loop round the plugins
        foreach ($plugins as $searchplugin) {
            // set the disabled flag
            if (isset($disable[$searchplugin['title']])) {
                ModUtil::setVar('Search', "disable_$searchplugin[title]", true);
            } else {
                ModUtil::setVar('Search', "disable_$searchplugin[title]", false);
            }
        }

        // Let any other modules know that the modules configuration has been updated
        $this->callHooks('module','updateconfig','Search', array('module' => 'Search'));

        // the module configuration has been updated successfuly
        LogUtil::registerStatus($this->__('Done! Saved module configuration.'));

        // This function generated no output, and so now it is complete we redirect
        // the user to an appropriate page for them to carry on their work
        return System::redirect(ModUtil::url('Search', 'admin', 'main'));
    }
}