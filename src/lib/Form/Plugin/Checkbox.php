<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Form
 * @subpackage Form_Plugin
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * Check box plugin
 *
 * Plugin to generate a checkbox for true/false selection.
 */
class Form_Plugin_Checkbox extends Form_StyledPlugin
{
    /**
     * Checked value.
     *
     * Set to true when checkbox is checked, false otherwise.
     *
     * @var boolean
     */
    public $checked;

    /**
     * Enable or disable read only mode.
     *
     * @var boolean
     */
    public $readOnly;

    /**
     * Data field name for looking up initial data.
     *
     * The name stored here is used to lookup initial data for the plugin in the render's variables.
     * Defaults to the ID of the plugin. See also tutorials on the Zikula site.
     *
     * @var string
     */
    public $dataField;

    /**
     * Enable or disable use of $dataField.
     *
     * @var boolean
     */
    public $dataBased;

    /**
     * Group name for this input.
     *
     * The group name is used to locate data in the render (when databased) and to restrict which
     * plugins to do validation on (to be implemented).
     *
     * @var string
     * @see   Form_View::getValues(), Form_View::isValid()
     */
    public $group;

    /**
     * HTML input name for this plugin. Defaults to the ID of the plugin.
     *
     * @var string
     */
    public $inputName;

    /**
     * Get filename for this plugin.
     *
     * A requirement from the framework - must be implemented like this. Used to restore plugins on postback.
     *
     * @internal
     * @return string
     */
    function getFilename()
    {
        return __FILE__;
    }

    /**
     * Create event handler.
     *
     * @param Form_View $render Reference to Form render object.
     * @param array       $params  Parameters passed from the Smarty plugin function.
     *
     * @see    Form_Plugin
     * @return void
     */
    function create($render, $params)
    {
        // Load all special and non-string parameters
        // - the rest are fetched automatically


        $this->checked = (array_key_exists('checked', $params) ? $params['checked'] : false);

        $this->inputName = (array_key_exists('inputName', $params) ? $params['inputName'] : $this->id);
        $this->readOnly = (array_key_exists('readOnly', $params) ? $params['readOnly'] : false);

        $this->dataBased = (array_key_exists('dataBased', $params) ? $params['dataBased'] : true);
        $this->dataField = (array_key_exists('dataField', $params) ? $params['dataField'] : $this->id);
    }

    /**
     * Load event handler.
     *
     * @param Form_View $render Reference to pnForm render object.
     * @param array       &$params Parameters passed from the Smarty plugin function.
     *
     * @return void
     */
    function load($render, &$params)
    {
        $this->loadValue($render, $render->get_template_vars());
    }

    /**
     * Load values.
     *
     * Called internally by the plugin itself to load values from the render.
     * Can also by called when some one is calling the render object's pnFormSetValues.
     *
     * @param Form_View $render Reference to pnForm render object.
     * @param array       &$values Values to load.
     *
     * @return void
     */
    function loadValue($render, &$values)
    {
        if ($this->dataBased) {
            $value = null;

            if ($this->group == null) {
                if (array_key_exists($this->dataField, $values)) {
                    if (isset($values[$this->dataField])) {
                        $value = $values[$this->dataField];
                    } else {
                        $value = null;
                    }
                }
            } else {
                if (array_key_exists($this->group, $values)) {
                    if (isset($values[$this->group][$this->dataField])) {
                        $value = $values[$this->group][$this->dataField];
                    } else {
                        $value = null;
                    }
                }
            }

            if ($value !== null) {
                $this->checked = $value;
            }
        }
    }

    /**
     * Render event handler.
     *
     * @param Form_View $render Reference to Form render object.
     *
     * @return string The rendered output
     */
    function render($render)
    {
        $idHtml = $this->getIdHtml();

        $nameHtml = " name=\"{$this->inputName}\"";
        $readOnlyHtml = ($this->readOnly ? " disabled=\"disabled\"" : '');
        $checkedHtml = ($this->checked ? " checked=\"checked\"" : '');

        $attributes = $this->renderAttributes($render);

        $result = "<input type=\"checkbox\" value=\"1\" class=\"cbx\"{$idHtml}{$nameHtml}{$readOnlyHtml}{$checkedHtml}{$attributes}/>";

        return $result;
    }

    /**
     * Decode event handler.
     *
     * @param Form_View $render Reference to Form render object.
     *
     * @return void
     */
    function decode($render)
    {
        // Do not read new value if readonly (evil submiter might have forged it)
        if (!$this->readOnly) {
            $this->checked = (FormUtil::getPassedValue($this->inputName, null, 'REQUEST') == null ? false : true);
        }
    }

    /**
     * Saves value in data object.
     *
     * Called by the render when doing $render->getValues()
     * Uses the group parameter to decide where to store data.
     *
     * @param Form_View $render Reference to Form render object.
     * @param array       &$data   Data object.
     *
     * @return void
     */
    function saveValue($render, &$data)
    {
        if ($this->dataBased) {
            if ($this->group == null) {
                $data[$this->dataField] = $this->checked;
            } else {
                if (!array_key_exists($this->group, $data)) {
                    $data[$this->group] = array();
                }
                $data[$this->group][$this->dataField] = $this->checked;
            }
        }
    }
}

