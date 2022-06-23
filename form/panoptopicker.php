<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Panopto video picker form element.
 *
 * @package    repository_panopto
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("HTML/QuickForm/input.php");

/**
 * Panopto video picker form element class.
 *
 * @package    repository_panopto
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MoodleQuickForm_panoptopicker extends HTML_QuickForm_input {

    /**
     * @var string html for help button, if empty then no help
     */
    public $_helpbutton = '';

    /**
     * @var bool if true label will be hidden
     */
    protected $_hiddenLabel = false;

    /**
     * Constructor
     *
     * @param string $elementName Element name
     * @param mixed $elementLabel Label(s) for an element
     * @param mixed $attributes Either a typical HTML attribute string or an associative array.
     * @param array $options data which need to be posted.
     */
    public function __construct($elementName = null, $elementLabel = null, $attributes = null, $options = null) {
        global $CFG;
        require_once("$CFG->dirroot/repository/lib.php");
        $options = (array)$options;
        foreach ($options as $name => $value) {
            $this->_options[$name] = $value;
        }
        parent::__construct($elementName, $elementLabel, $attributes);
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function MoodleQuickForm_panoptopicker($elementName=null, $elementLabel=null, $attributes=null, $options=null) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($elementName, $elementLabel, $attributes, $options);
    }

    /**
     * Sets label to be hidden
     *
     * @param bool $hiddenLabel sets if label should be hidden
     */
    public function setHiddenLabel($hiddenLabel) {
        $this->_hiddenLabel = $hiddenLabel;
    }

    /**
     * Returns HTML for this form element.
     *
     * @return string
     */
    public function toHtml() {
        global $PAGE, $OUTPUT;

        $str = '';
        if ($this->_hiddenLabel) {
            $this->_generateId();
            $str = '<label class="accesshide" for="'.$this->getAttribute('id').'" >'.
                $this->getLabel().'</label>';
        }

        // Initialise filepicker.
        $clientid = uniqid();
        $args = new stdClass();
        $args->return_types = FILE_EXTERNAL;
        $args->context = $PAGE->context;
        $args->client_id = $clientid;
        $args->env = 'panoptopicker';
        $fp = new file_picker($args);

        // Override repositories list and make Panopto repository the only listed.
        $fp->options->repositories = array();
        $repositories = repository::get_instances(array(
            'currentcontext' => $PAGE->context,
            'type' => 'panopto',
            'onlyvisible' => false,
        ));
        foreach ($repositories as $repository) {
            $meta = $repository->get_meta();
            $fp->options->repositories[$repository->id] = $meta;
        }

        $options = $fp->options;
        $str .= '<input type="hidden" name="' . $this->getName() . '" id="' . $this->getAttribute('id') . '" value="' .
                $this->getValue() . '" />';
        if (count($options->repositories) > 0) {
            $straddlink = get_string('chooseavideo', 'panopto');
            $str .= <<<EOD
<button id="filepicker-button-js-{$clientid}" class="visibleifjs panoptopicker-button">
$straddlink
</button>
<div class="repository_panopto" id="panoptopicker-area"></div>
EOD;
        }

        // Print out file picker.
        $str .= $OUTPUT->render($fp);

        // Initialise filepicker JS.
        // TODO: This code have to go to amd, but let's wait till filepicker
        // will get into amd in the core first. Passing $options to amd seems
        // problematic as it contains objects.
        $options->element_id = $this->getAttribute('id');
        $module = array('name' => 'form_panoptopicker',
            'fullpath' => '/repository/panopto/form/panoptopicker.js',
            'requires' => array('core_filepicker')
        );
        $PAGE->requires->js_init_call('M.form_panoptopicker.init', array($options), true, $module);

        // Initialise JS amd that performs AJAX calls to retrieve session data using Panopto API on backend.
        $params = array(
            'elementid' => $options->element_id,
            'contextid' => $PAGE->context->id,
        );
        $PAGE->requires->js_call_amd('repository_panopto/panoptopicker', 'init', array($params));

        return $str;
    }

    /**
     * Get html for help button
     *
     * @return string html for help button
     */
    public function getHelpButton() {
        return $this->_helpbutton;
    }

    /**
     * Slightly different container template when frozen. Don't want to use a label tag
     * with a for attribute in that case for the element label but instead use a div.
     * Templates are defined in renderer constructor.
     *
     * @return string
     */
    public function getElementTemplateType() {
        if ($this->_flagFrozen) {
            return 'static';
        } else {
            return 'default';
        }
    }
}

MoodleQuickForm::registerElementType('panoptopicker', $CFG->dirroot.'/repository/panopto/form/panoptopicker.php',
        'MoodleQuickForm_panoptopicker');
