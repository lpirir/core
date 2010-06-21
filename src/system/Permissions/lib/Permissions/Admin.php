<?php
/**
 * Zikula Application Framework
 * @copyright (c) 2002, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_System_Modules
 * @subpackage Permissions
 * @license http://www.gnu.org/copyleft/gpl.html
 */

class Permissions_Admin extends Zikula_Controller
{
    /**
     * the main administration function
     * This function is the default function, and is called whenever the
     * module is initiated without defining arguments.  As such it can
     * be used for a number of things, but most commonly it either just
     * shows the module menu and returns or calls whatever the module
     * designer feels should be the default function (often this is the
     * view() function)
     * @author Jim McDonald
     * @return mixed HTML string or true
     */
    public function main()
    {
        // Security check will be done in view()
        return $this->view();
    }

    /**
     * view permissions
     * @author Jim McDonald
     * @return string HTML string
     */
    public function view()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Get parameters from whatever input we need.
        $permgrp = FormUtil::getPassedValue('permgrp', -1, 'REQUEST');
        $testuser = FormUtil::getPassedValue('test_user', null, 'POST');
        $testcomponent = FormUtil::getPassedValue('test_component', null, 'POST');
        $testinstance = FormUtil::getPassedValue('test_instance', null, 'POST');
        $testlevel = FormUtil::getPassedValue('test_level', null, 'POST');

        // Create output object
        $renderer = Renderer::getInstance('Permissions', false);

        $testresult = '';
        if (!empty($testuser) &&
                !empty($testcomponent) &&
                !empty($testinstance)
        // && !empty($testlevel)
        ) {
            // we have everything we need for an effective permission check
            $testuid    = UserUtil::getIdFromName($testuser);
            if ($testuid <> false) {
                if (SecurityUtil::checkPermission($testcomponent, $testinstance, $testlevel, $testuid)) {
                    $testresult = '<span id="permissiontestinfogreen">' . $this->__('permission granted.') . '</span>';
                } else {
                    $testresult = '<span id="permissiontestinfored">' . $this->__('permission not granted.') . '</span>';
                }
            } else {
                $testresult = '<span id="permissiontestinfored">' . $this->__('unknown user.') . '</span>';
            }
        }
        $renderer->assign('testuser', $testuser);
        $renderer->assign('testcomponent', $testcomponent);
        $renderer->assign('testinstance', $testinstance);
        $renderer->assign('testlevel', $testlevel);
        $renderer->assign('testresult', $testresult);

        // decide the default view
        $enableFilter = ModUtil::getVar('Permissions', 'filter', 1);
        $rowview      = ModUtil::getVar('Permissions', 'rowview', 25);

        // Work out which tables to operate against, and
        // various other bits and pieces
        $pntable = System::dbGetTables();
        $permcolumn = $pntable['group_perms_column'];
        $ids = $this->getGroupsInfo();

        //if (isset($dbg)) $dbg->v($ids,'Group ids');
        //if (isset($dbg)) $dbg->v((int)$permgrp,'PermGrp');
        $where = '';
        if($enableFilter == 1) {
            $permgrpparts = explode('+', $permgrp);
            if ($permgrpparts[0] == 'g') {
                if (is_array($permgrpparts) && $permgrpparts[1] != PNPERMS_ALL) {
                    $where = "WHERE (".$permcolumn['gid']."='".PNPERMS_ALL."' OR ".$permcolumn['gid']."='".DataUtil::formatForStore($permgrpparts[1])."')";
                    $permgrp = $permgrpparts[1];
                    $renderer->assign('filtertype', 'group');
                } else {
                    $permgrp = PNPERMS_ALL;
                    $where = '';
                }
            } else if ($permgrpparts[0] =='c') {
                if (is_array($permgrpparts) && $permgrpparts[1] != PNPERMS_ALL) {
                    $where = "WHERE (".$permcolumn['component']."='.*' OR ".$permcolumn['component']." LIKE '".DataUtil::formatForStore($permgrpparts[1])."%')";
                    $permgrp = $permgrpparts[1];
                    $renderer->assign('filtertype', 'component');
                } else {
                    $permgrp = PNPERMS_ALL;
                    $where = '';
                }
            } else {
                $renderer->assign('filtertype', '');
            }
            $renderer->assign('permgrps', $ids);
            $renderer->assign('permgrp', $permgrp);
            $renderer->assign('enablefilter', true);
        } else {
            $renderer->assign('enablefilter', false);
            $renderer->assign('filtertype', '');
            $renderer->assign('permgrp', PNPERMS_ALL);
        }

        $accesslevels = SecurityUtil::accesslevelnames();

        $orderBy = "ORDER BY $permcolumn[sequence]";
        $objArray = DBUtil::selectObjectArray('group_perms', $where, $orderBy, -1, -1, false);
        $numrows = DBUtil::_getFetchedObjectCount();

        $permissions = array();
        $components = array(-1 => $this->__('All components'));
        if ($numrows>0) {
            $authid = SecurityUtil::generateAuthKey('Permissions');
            $rownum = 1;
            $ak = array_keys($objArray);
            foreach($ak as $v) {
                $obj = $objArray[$v];
                $id = $obj['gid'];
                $up = array('url' => ModUtil::url('Permissions', 'admin', 'inc',
                        array('pid'      => $obj['pid'],
                        'permgrp'  => $permgrp,
                        'authid'   => $authid)),
                        'title' => $this->__('Up'));
                $down = array('url' => ModUtil::url('Permissions', 'admin', 'dec',
                        array('pid'      => $obj['pid'],
                        'permgrp'  => $permgrp,
                        'authid'   => $authid)),
                        'title' => $this->__('Down'));
                switch($rownum) {
                    case 1:
                        $arrows = array('up' => 0, 'down' => 1);
                        break;
                    case $numrows:
                        $arrows = array('up' => 1, 'down' => 0);
                        break;
                    default:
                        $arrows = array('up' => 1, 'down' => 1);
                        break;
                }
                $rownum++;

                // MMaes, 2003-06-20: Added authid to modify-url
                // MMaes, 2003-06-25: Changed URL to new modify-function
                // MMaes, 2003-06-20: Direct Insert Capability
                $options = array();
                $inserturl = ModUtil::url('Permissions', 'admin', 'listedit',
                        array('permgrp'  => $permgrp,
                        'action'   => 'insert',
                        'insseq'   => $obj['sequence']));
                $editurl = ModUtil::url('Permissions', 'admin', 'listedit',
                        array('chgpid'   => $obj['pid'],
                        'permgrp'  => $permgrp,
                        'action'   => 'modify'));
                $deleteurl = ModUtil::url('Permissions', 'admin', 'delete',
                        array('pid'      => $obj['pid'],
                        'permgrp'  => $permgrp));

                $permissions[] = array('sequence'    => $obj['sequence'],
                        'arrows'      => $arrows,
                        // Realms not currently functional so hide the output - jgm
                        //'realms'    => $realms[$realm],
                        'group'       => $ids[$id],
                        'groupid'     => $id,
                        'component'   => $obj['component'],
                        'instance'    => $obj['instance'],
                        'accesslevel' => $accesslevels[$obj['level']],
                        'accesslevelid'=> $obj['level'],
                        'options'     => $options,
                        'up'          => $up,
                        'down'        => $down,
                        'permid'      => $obj['pid'],
                        'inserturl'   => $inserturl,
                        'editurl'     => $editurl,
                        'deleteurl'   => $deleteurl);
            }
        }

        // read all perms to extract components
        $allPerms = DBUtil::selectObjectArray('group_perms', '', $orderBy, -1, -1, false);
        foreach ($allPerms as $singlePerm) {
            // extract components, we keep everything up to the first colon
            $compparts = explode(':', $singlePerm['component']);
            $components[$compparts[0]] = $compparts[0];
        }

        $renderer->assign('groups', $this->getGroupsInfo());
        $renderer->assign('permissions', $permissions);
        $renderer->assign('components', $components);
        $lockadmin = (ModUtil::getVar('Permissions', 'lockadmin')) ? 1 : 0;
        $renderer->assign('lockadmin', $lockadmin);
        $renderer->assign('adminid', ModUtil::getVar('Permissions', 'adminid'));

        // Assign the permission levels
        $renderer->assign('permissionlevels', SecurityUtil::accesslevelnames());

        return $renderer->fetch('permissions_admin_view.htm');
    }

    /**
     * increment a permission
     * @author Jim McDonald
     * @param int 'pid' permissions id
     * @return bool true
     */
    public function inc()
    {
        // MMaes,2003-06-23: Added sec.check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Confirm authorisation code
        // MMaes,2003-06-23: Redirect to base if the AuthKey doesn't compute.
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Permissions','admin','main'));
        }

        // Get parameters
        // MMaes,2003-06-23: View permissions applying to single group; added permgrp
        $pid = FormUtil::getPassedValue('pid', null, 'GET');
        $permgrp = FormUtil::getPassedValue('permgrp', null, 'GET');

        if (empty($permgrp)) {
            // For group-permissions, make sure we return something sensible.
            // Doesn't matter if we're looking at user-permissions...
            $permgrp = PNPERMS_ALL;
        }

        // Pass to API
        if (ModUtil::apiFunc('Permissions', 'admin', 'inc',
        array('pid'     => $pid,
        'permgrp' => $permgrp))) {
            // Success
            LogUtil::registerStatus($this->__('Done! Incremented permission rule.'));
        }

        // Redirect
        return System::redirect(ModUtil::url('Permissions', 'admin', 'view',
                array('permgrp'  => $permgrp)));
    }

    /**
     * decrement a permission
     * @author Jim McDonald
     * @param int 'pid' permissions id
     * @return bool true
     */
    public function dec()
    {
        // MMaes,2003-06-23: Added sec.check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }
        // Confirm authorisation code
        // MMaes,2003-06-23: Redirect to base if the AuthKey doesn't compute.
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Permissions','admin','main'));
        }

        // Get parameters
        // MMaes,2003-06-23: View permissions applying to single group; added permgrp
        $pid = FormUtil::getPassedValue('pid', null, 'GET');
        $permgrp = FormUtil::getPassedValue('permgrp', null, 'GET');

        if (!isset($permgrp) || $permgrp == '') {
            // For group-permissions, make sure we return something sensible.
            // This doesn't matter if we're looking at user-permissions...
            $permgrp = PNPERMS_ALL;
        }

        // Pass to API
        if (ModUtil::apiFunc('Permissions', 'admin', 'dec',
        array('pid'     => $pid,
        'permgrp' => $permgrp))) {
            // Success
            LogUtil::registerStatus($this->__('Done! Decremented permission rule.'));
        }

        // Redirect
        // MMaes,2003-06-23: View permissions applying to single group; added permgrp
        return System::redirect(ModUtil::url('Permissions', 'admin', 'view',
                array('permgrp'  => $permgrp)));
    }

    /**
     * Edit / Create permissions in the mainview
     *
     * @return bool
     */
    public function listedit()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Get parameters from whatever input we need.
        $chgpid = FormUtil::getPassedValue('chgpid', null, 'GET');
        $action = FormUtil::getPassedValue('action', null, 'GET');
        $insseq = FormUtil::getPassedValue('insseq', null, 'GET');
        $permgrp = FormUtil::getPassedValue('permgrp', null, 'REQUEST');

        // decide default view
        $rowview = is_null(ModUtil::getVar('Permissions', 'rowview')) ? '25' : ModUtil::getVar('Permissions', 'rowview');

        // Create output object
        $renderer = Renderer::getInstance('Permissions', false);

        // Assign the permission levels
        $renderer->assign('permissionlevels', SecurityUtil::accesslevelnames());

        // Work out which tables to operate against, and
        // various other bits and pieces
        $pntable = System::dbGetTables();
        $permcolumn = $pntable['group_perms_column'];
        $mlpermtype = $this->__('Group');
        $viewperms = ($action == 'modify') ? $this->__('Modify permission rule') : $this->__('Create new permission rule');
        $ids = $this->getGroupsInfo();

        $orderBy = "ORDER BY $permcolumn[sequence]";
        $objArray = DBUtil::selectObjectArray('group_perms', '', $orderBy);
        if (!$objArray && $action != 'add') {
            LogUtil::registerError($this->__('Error! No permission rules of this kind were found. Please add some first.'));
            return System::redirect(ModUtil::url('modules', 'admin', 'main'));
        }

        $renderer->assign('title', $viewperms);
        $renderer->assign('mlpermtype', $mlpermtype);

        $accesslevels = SecurityUtil::accesslevelnames();
        $numrows = count($objArray);

        $renderer->assign('idvalues', $ids);

        if ($action == 'modify') {
            // Form-start
            $renderer->assign('formurl', ModUtil::url('Permissions', 'admin', 'update'));
            $renderer->assign('permgrp', $permgrp);
            $renderer->assign('chgpid', $chgpid);
            // Realms hard-code4d - jgm
            $renderer->assign('realm', 0);
            $renderer->assign('insseq', $chgpid);
            $renderer->assign('submit', $this->__('Edit permission rule'));

        } else if ($action == 'insert') {
            $renderer->assign('formurl', ModUtil::url('Permissions', 'admin', 'create'));
            $renderer->assign('permgrp', $permgrp);
            $renderer->assign('insseq', $insseq);
            // Realms hard-coded - jgm
            $renderer->assign('realm', 0);
            $renderer->assign('submit', $this->__('Create new permission rule'));

        } else if ($action == 'add') {
            // Form-start
            $renderer->assign('formurl', ModUtil::url('Permissions', 'admin', 'create'));
            $renderer->assign('permgrp', $permgrp);
            $renderer->assign('insseq', -1);
            // Realms hard-coded - jgm
            $renderer->assign('realm', 0);
            $renderer->assign('submit', $this->__('Create new permission rule'));
        }

        $renderer->assign('action', $action);

        $permissions = array();
        $ak = array_keys($objArray);
        foreach($ak as $v) {
            $obj =& $objArray[$v];
            $id = $obj['gid']; //get's uid or gid accordingly
            $permissions[] = array(// Realms not currently functional so hide the output - jgm
                    //'realms' => $realms[$realm],
                    'pid'         => $obj['pid'],
                    'group'       => $ids[$id],
                    'component'   => $obj['component'],
                    'instance'    => $obj['instance'],
                    'accesslevel' => $accesslevels[$obj['level']],
                    'level'       => $obj['level'],
                    'sequence'    => $obj['sequence']);
            if ($action == 'modify' && $obj['pid'] == $chgpid) {
                $renderer->assign('selectedid', $id);
            }

        }
        $renderer->assign('permissions', $permissions);

        return $renderer->fetch('permissions_admin_listedit.htm');
    }

    /**
     * @author Jim McDonald <jim@mcdee.net>
     * @link http://www.mcdee.net
     * @param int 'pid' permissions id
     * @param int 'id' group or user id
     * @param int 'realm' realm to which the permission belongs
     * @param string 'component' component string
     * @param string 'instance' instance string
     * @param int 'level' permission level
     * @return bool true
     */
    public function update()
    {
        // MMaes,2003-06-23: Added sec.check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Confirm authorisation code
        // MMaes,2003-06-23: Redirect to base if the AuthKey doesn't compute.
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Permissions','admin','main'));
        }

        // Get parameters
        $pid = FormUtil::getPassedValue('pid', null, 'POST');
        $seq = FormUtil::getPassedValue('seq', null, 'POST');
        $oldseq = FormUtil::getPassedValue('oldseq', null, 'POST');
        $realm = FormUtil::getPassedValue('realm', null, 'POST');
        $id = FormUtil::getPassedValue('id', null, 'POST');
        $component = FormUtil::getPassedValue('component', null, 'POST');
        $instance = FormUtil::getPassedValue('instance', null, 'POST');
        $level = FormUtil::getPassedValue('level', null, 'POST');

        // Since we're using TextAreas, make sure no carriage-returns etc get through unnoticed.
        $warnmsg = '';
        if (preg_match("/[\n\r\t\x0B]/", $component)) {
            $component = trim(preg_replace("/[\n\r\t\x0B]/", "", $component));
            $instance = trim(preg_replace("/[\n\r\t\x0B]/", "", $instance));
            $warnmsg .= $this->__('[Illegal input in component!]');
        }
        if (preg_match("/[\n\r\t\x0B]/", $instance)) {
            $component = trim(preg_replace("/[\n\r\t\x0B]/", "", $component));
            $instance = trim(preg_replace("/[\n\r\t\x0B]/", "", $instance));
            $warnmsg .= $this->__('[Illegal input in instance!]');
        }

        // Pass to API
        if (ModUtil::apiFunc('Permissions', 'admin', 'update',
        array('pid'       => $pid,
        'seq'       => $seq,
        'oldseq'    => $oldseq,
        'realm'     => $realm,
        'id'        => $id,
        'component' => $component,
        'instance'  => $instance,
        'level'     => $level))) {
            // Success
            if ($warnmsg == '') {
                LogUtil::registerStatus($this->__('Done! Saved permission rule.'));
            } else {
                LogUtil::registerError($warnmsg);
            }
        }

        return System::redirect(ModUtil::url('Permissions', 'admin', 'view'));
    }


    /**
     * create a new permission
     * @author Jim McDonald
     * @param int 'id' group
     * @param int 'realm' realm to which the permission belongs
     * @param string 'component' component string
     * @param string 'instance' instance string
     * @param int 'level' permission level
     * @return bool true
     */
    public function create()
    {
        // MMaes,2003-06-23: Added sec.check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Confirm authorisation code
        // MMaes,2003-06-23: Redirect to base if the AuthKey doesn't compute.
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Permissions','admin','main'));
        }

        // Get parameters
        $realm = FormUtil::getPassedValue('realm', null, 'POST');
        $id = FormUtil::getPassedValue('id', null, 'POST');
        $component = FormUtil::getPassedValue('component', null, 'POST');
        $instance = FormUtil::getPassedValue('instance', null, 'POST');
        $level = FormUtil::getPassedValue('level', null, 'POST');
        $insseq = FormUtil::getPassedValue('insseq', null, 'POST');

        // Since we're using TextAreas, make sure no carriage-returns etc get through unnoticed.
        $warnmsg = '';
        if (preg_match("/[\n\r\t\x0B]/", $component)) {
            $component = trim(preg_replace("/[\n\r\t\x0B]/", "", $component));
            $instance = trim(preg_replace("/[\n\r\t\x0B]/", "", $instance));
            $warnmsg .= $this->__('[Illegal input in component!]');
        }
        if (preg_match("/[\n\r\t\x0B]/", $instance)) {
            $component = trim(preg_replace("/[\n\r\t\x0B]/", "", $component));
            $instance = trim(preg_replace("/[\n\r\t\x0B]/", "", $instance));
            $warnmsg .= $this->__('[Illegal input in instance!]');
        }

        // Pass to API
        if (ModUtil::apiFunc('Permissions', 'admin', 'create',
        array('realm'     => $realm,
        'id'        => $id,
        'component' => $component,
        'instance'  => $instance,
        'level'     => $level,
        'insseq'    => $insseq))) {
            // Success
            if ($warnmsg == '') {
                LogUtil::registerStatus($this->__('Done! Created permission rule.'));
            } else {
                LogUtil::registerError($warnmsg);
            }
        }

        return System::redirect(ModUtil::url('Permissions',
                'admin',
                'view'));
    }


    /**
     * delete a permission
     * @author Jim McDonald
     * @param int 'pid' permissions id
     * @return bool true
     */
    public function delete()
    {
        // MMaes,2003-06-23: Added sec.check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Get parameters
        $permgrp = FormUtil::getPassedValue('permgrp', null, 'REQUEST');
        $pid = FormUtil::getPassedValue('pid', null, 'REQUEST');
        $confirmation = FormUtil::getPassedValue('confirmation', null, 'REQUEST');

        // Check for confirmation.
        if (empty($confirmation)) {
            // No confirmation yet

            // Create output object
            $renderer = Renderer::getInstance('Permissions', false);

            // Add a hidden field for the item ID to the output
            $renderer->assign('pid', $pid);

            // assign the permission type and group
            $renderer->assign('permgrp', $permgrp);

            // Return the output that has been generated by this function
            return $renderer->fetch('permissions_admin_delete.htm');
        }

        // If we get here it means that the user has confirmed the action

        // Confirm authorisation code
        // MMaes,2003-06-23: Redirect to base if the AuthKey doesn't compute.
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Permissions','admin','main'));
        }

        // Pass to API
        if (ModUtil::apiFunc('Permissions',  'admin', 'delete',
        array('pid'  => $pid))) {
            // Success
            LogUtil::registerStatus($this->__('Done! Deleted permission rule.'));
        }

        return System::redirect(ModUtil::url('Permissions', 'admin', 'view',
                array('permgrp'  => $permgrp)));
    }

    /**
     * getGroupsInfo - get groups information
     * Takes no parameters
     * @author Jim McDonald
     * @return array groups array
     * @todo remove calls to this function in favour of calls to the groups module
     */
    function getGroupsInfo()
    {
        $pntable = System::dbGetTables();

        $groupcolumn = $pntable['groups_column'];

        $orderBy = "ORDER BY $groupcolumn[name]";
        $objArrray = DBUtil::selectObjectArray('groups', '', $orderBy);
        $groups[PNPERMS_ALL] = $this->__('All groups');
        $groups[PNPERMS_UNREGISTERED] = $this->__('Unregistered');

        $ak = array_keys($objArrray);
        foreach($ak as $v) {
            $gid = $objArrray[$v]['gid'];
            $groups[$gid] = $objArrray[$v]['name'];
        }

        return($groups);
    }

    /**
     * showInstanceInformation  - Show instance information gathered
     *                             from blocks and modules
     * Takes no parameters
     * @author Jim McDonald
     * @return bool
     */
    public function viewinstanceinfo()
    {
        // MMaes,2003-06-23: This function generates output -> added sec.check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Create output object
        $renderer = Renderer::getInstance('Permissions', false);

        // Get all permissions schemas, sort and assign to the template
        $renderer->assign('schemas', ModUtil::apiFunc('Permissions', 'admin', 'getallschemas'));

        // we don't return the output back to the core here since this template is a full page
        // template i.e. we don't want this output wrapped in the theme.
        $renderer->display('permissions_admin_viewinstanceinfo.htm');
        return true;
    }


    /**
     * Set configuration parameters of the module
     *
     * @return bool
     */
    public function modifyconfig()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Create output object - this object will store all of our output so that
        // we can return it easily when required
        $renderer = Renderer::getInstance('Permissions', false);

        // assign the module vars
        $renderer->assign(ModUtil::getVar('Permissions'));

        // return the output
        return $renderer->fetch('permissions_admin_modifyconfig.htm');
    }
    /**
     * Save new settings
     *
     * @return bool
     */
    public function updateconfig()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Permissions::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Confirm authorisation code
        // MMaes,2003-06-23: Redirect to base if the AuthKey doesn't compute.
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Permissions','admin','main'));
        }

        $error = false;
        $filter = (bool)FormUtil::getPassedValue('filter', false, 'POST');
        ModUtil::setVar('Permissions', 'filter', $filter);

        $rowview = (int)FormUtil::getPassedValue('rowview', 25, 'POST');
        ModUtil::setVar('Permissions', 'rowview', $rowview);

        $rowedit = (int)FormUtil::getPassedValue('rowedit', 35, 'POST');
        ModUtil::setVar('Permissions', 'rowedit', $rowedit);

        $lockadmin = (bool)FormUtil::getPassedValue('lockadmin', false, 'POST');
        ModUtil::setVar('Permissions', 'lockadmin', $lockadmin);

        $adminid = (int)FormUtil::getPassedValue('adminid', 1, 'POST');
        if ($adminid<>0) {
            $perm = DBUtil::selectObjectByID('group_perms', $adminid, 'pid');
            if ($perm==false) {
                $adminid = 0;
                $error = true;
            }
        }
        ModUtil::setVar('Permissions', 'adminid', $adminid);

        // Let any other modules know that the modules configuration has been updated
        $this->callHooks('module','updateconfig', 'Permissions', array('module' => 'Permissions'));

        // the module configuration has been updated successfuly
        if ($error==true) {
            LogUtil::registerStatus($this->__('Error! Could not save configuration: unknown permission rule ID.'));
            return System::redirect(ModUtil::url('Permissions', 'admin', 'modifyconfig'));
        }
        LogUtil::registerStatus($this->__('Done! Saved module configuration.'));
        return System::redirect(ModUtil::url('Permissions', 'admin', 'main'));


    }

    /**
     * Check permissions
     *
     * @return bool
     */
    public function checkpermissions()
    {
        $username = FormUtil::getPassedValue('username', null, 'POST');
        $returnto = FormUtil::getPassedValue('returnto', System::getCurrentUri(), 'POST');
        return System::redirect($returnto);
    }

}